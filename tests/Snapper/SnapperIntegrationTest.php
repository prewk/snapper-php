<?php

namespace Prewk\Snapper;

use PDO;
use PHPUnit\Framework\TestCase;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;
use Prewk\Snapper\Ingredients\Json\JsonRecipe;
use Prewk\Snapper\Ingredients\Json\MatchedJson;
use Prewk\Snapper\Ingredients\Json\TextReplacer;
use Prewk\Snapper\Ingredients\Match\MatchMapper;
use Prewk\Snapper\Ingredients\Morph\MorphMapper;
use Prewk\Snapper\Serializer\SerializationBookKeeper;

class SnapperIntegrationTest extends TestCase
{
    public function getTestRows()
    {
        return [
            ["roots", ["id" => 1, "name" => "Lorem ipsum", "favorite_node_id" => 1]],
            ["nodes", ["id" => 1, "root_id" => 1, "parent" => 0]],
            ["nodes", ["id" => 2, "root_id" => 1, "parent" => 1]],
            ["nodes", ["id" => 3, "root_id" => 1, "parent" => 1]],
            ["polys", ["id" => 1, "polyable_type" => "ROOT", "polyable_id" => 1, "json" => json_encode([
                "deep" => ["child_id" => 1],
                "some_child" => "Lorem ipsum <<\"1\">> dolor <<\"2\">> amet",
                "a" => ["deeply" => ["nested" => ["child" => "<<\"3\">>"]]],
            ], JSON_UNESCAPED_SLASHES)]],
            ["polys", ["id" => 2, "polyable_type" => "NODE", "polyable_id" => 3, "json" => json_encode([
                "deep" => ["child_id" => null],
            ], JSON_UNESCAPED_SLASHES)]],
            ["children", ["id" => 1, "type" => "ROOT_POINTER", "variable_pointer_id" => 1]],
            ["children", ["id" => 2, "type" => "NODE_POINTER", "variable_pointer_id" => 2]],
            ["children", ["id" => 3, "type" => "NODE_POINTER", "variable_pointer_id" => 3]],
        ];
    }

    public function getMemoryDb()
    {

        $db = new PDO("sqlite::memory:");

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $db->exec("CREATE TABLE roots (
            id INTEGER PRIMARY KEY,
            name VARCHAR(255),
            favorite_node_id INTEGER
        )");

        $db->exec("CREATE TABLE nodes (
            id INTEGER PRIMARY KEY,
            root_id INTEGER,
            parent INTEGER,
            FOREIGN KEY(root_id) REFERENCES roots(id)
        )");

        $db->exec("CREATE TABLE polys (
            id INTEGER PRIMARY KEY,
            polyable_type VARCHAR(255),
            polyable_id INTEGER,
            json TEXT
        )");

        $db->exec("CREATE TABLE children (
            id INTEGER PRIMARY KEY,
            type varchar(255),
            variable_pointer_id INTEGER
        )");

        return $db;
    }

    public function getTestRecipes()
    {
        $recipe = new Recipe;

        return [
            "roots" => $recipe
                ->primary("id")
                ->ingredients([
                    "name" => $recipe->value(),
                    "favorite_node_id" => $recipe->circular($recipe->ref("nodes")->optional(0), $recipe->raw(0)),
                ]),
            "nodes" => $recipe
                ->primary("id")
                ->ingredients([
                    "root_id" => $recipe->ref("roots"),
                    "parent" => $recipe->ref("nodes")->optional(0),
                ]),
            "polys" => $recipe
                ->primary("id")
                ->ingredients([
                    "polyable_type" => $recipe->value(),
                    "polyable_id" => $recipe->morph("polyable_type", function(MorphMapper $mapper) {
                        return $mapper
                            ->map("ROOT", "roots")
                            ->map("NODE", "nodes");
                    }),
                    "json" => $recipe->json(function(JsonRecipe $jsonRecipe) {
                        return $jsonRecipe
                            ->path("deep.child_id", function(MatchedJson $matched) {
                                return $matched->ref("children")->optional(null);
                            })
                            ->pattern("/child$/", function(MatchedJson $matched) {
                                return $matched->regexp("/<<\"(.*?)\">>/", function(TextReplacer $replacer, array $matches, string $replacement) {
                                    list(, $id) = $matches;

                                    return $replacer->replace("children", $id, "<<\"$id\">>", "<<\"$replacement\">>");
                                });
                            });
                    })
                ]),
            "children" => $recipe
                ->primary("id")
                ->ingredients([
                    "type" => $recipe->value(),
                    "variable_pointer_id" => $recipe->match("type", function(MatchMapper $mapper) use ($recipe) {
                        return $mapper
                            ->on("ROOT_POINTER", $recipe->ref("roots"))
                            ->pattern("/^NODE_/", $recipe->ref("nodes"))
                            ->default($recipe->value());
                    }),
                ]),
        ];
    }

    protected function getAll($db, $table)
    {
        $result = $db->query("SELECT * FROM $table");
        $items = [];
        foreach ($result as $row) {
            $item = [];
            foreach ($row as $field => $value) {
                if (is_string($field)) $item[$field] = $value;
            }
            $items[] = $item;
        }
        return $items;
    }

    public function test_validator()
    {
        $recipes = $this->getTestRecipes();
        $testRows = $this->getTestRows();

        $serializer = new Serializer(new Sorter, new SerializationBookKeeper, $recipes);

        foreach ($testRows as list($type, $row)) {
            $serializer->add($type, $row);
        }

        $serialization = $serializer->compile();
        $validator = new Validator(new DeserializationBookKeeper, $recipes);

        // This serialization should validate
        $this->assertTrue($validator->validate($serialization));

        // Break it
        $serialization[0]["row"]["id"] = "BROKEN";

        // This serialization shouldn't validate
        $this->assertFalse($validator->validate($serialization));
    }

    public function test_full_recipe()
    {
        $recipes = $this->getTestRecipes();
        $testRows = $this->getTestRows();

        $serializer = new Serializer(new Sorter, new SerializationBookKeeper, $recipes);

        foreach ($testRows as list($type, $row)) {
            $serializer->add($type, $row);
        }

        $serialization = $serializer->compile();

        $db = $this->getMemoryDb();

        $inserters = [
            "roots" => function(array $row) use ($db) {
                $insert = "INSERT INTO roots (name, favorite_node_id) VALUES (:name, :favorite_node_id)";
                $stmt = $db->prepare($insert);
                $stmt->execute([
                    ":name" => $row["name"],
                    ":favorite_node_id" => $row["favorite_node_id"],
                ]);

                return intval($db->lastInsertId());
            },
            "nodes" => function(array $row) use ($db) {
                $insert = "INSERT INTO nodes (root_id, parent) VALUES (:root_id, :parent)";
                $stmt = $db->prepare($insert);
                $stmt->execute([
                    ":root_id" => $row["root_id"],
                    ":parent" => $row["parent"],
                ]);

                return intval($db->lastInsertId());
            },
            "polys" => function(array $row) use ($db) {
                $insert = "INSERT INTO polys (polyable_type, polyable_id, json) VALUES (:polyable_type, :polyable_id, :json)";
                $stmt = $db->prepare($insert);
                $stmt->execute([
                    ":polyable_type" => $row["polyable_type"],
                    ":polyable_id" => $row["polyable_id"],
                    ":json" => $row["json"],
                ]);

                return intval($db->lastInsertId());
            },
            "children" => function(array $row) use ($db) {
                $insert = "INSERT INTO children (type, variable_pointer_id) VALUES (:type, :variable_pointer_id)";
                $stmt = $db->prepare($insert);
                $stmt->execute([
                    ":type" => $row["type"],
                    ":variable_pointer_id" => $row["variable_pointer_id"],
                ]);

                return intval($db->lastInsertId());
            },
        ];

        $updaters = [
            "roots" => function($id, array $row) use ($db) {
                $sets = [];
                $vars = [":id" => $id];
                foreach ($row as $field => $value) {
                    $sets[] = "SET $field=:$field";
                    $vars[":$field"] = $value;
                }

                $update = "UPDATE roots " . implode(", ", $sets) . " WHERE id = :id";
                $stmt = $db->prepare($update);
                $stmt->execute($vars);
            },
            "nodes" => function($id, array $row) use ($db) {
                $sets = [];
                $vars = [":id" => $id];
                foreach ($row as $field => $value) {
                    $sets[] = "SET $field=:$field";
                    $vars[":$field"] = $value;
                }

                $update = "UPDATE nodes " . implode(", ", $sets) . " WHERE id = :id";
                $stmt = $db->prepare($update);
                $stmt->execute($vars);
            },
            "polys" => function($id, array $row) use ($db) {
                $sets = [];
                $vars = [":id" => $id];
                foreach ($row as $field => $value) {
                    $sets[] = "SET $field=:$field";
                    $vars[":$field"] = $value;
                }

                $update = "UPDATE polys " . implode(", ", $sets) . " WHERE id = :id";
                $stmt = $db->prepare($update);
                $stmt->execute($vars);
            },
            "children" => function($id, array $row) use ($db) {
                $sets = [];
                $vars = [":id" => $id];
                foreach ($row as $field => $value) {
                    $sets[] = "SET $field=:$field";
                    $vars[":$field"] = $value;
                }

                $update = "UPDATE children " . implode(", ", $sets) . " WHERE id = :id";
                $stmt = $db->prepare($update);
                $stmt->execute($vars);
            },
        ];

        $deserializer = new Deserializer(new DeserializationBookKeeper, $recipes, $inserters, $updaters);
        $deserializer->deserialize($serialization);

        $getRowsOfType = function($type) use ($testRows) {
            return array_map(function($tuple) {
                return $tuple[1];
            }, array_values(array_filter($testRows, function($tuple) use ($type) {
                return $tuple[0] === $type;
            })));
        };

        $this->assertEquals($getRowsOfType("roots"), $this->getAll($db, "roots"));
        $this->assertEquals($getRowsOfType("nodes"), $this->getAll($db, "nodes"));
        $this->assertEquals($getRowsOfType("polys"), $this->getAll($db, "polys"));
        $this->assertEquals($getRowsOfType("children"), $this->getAll($db, "children"));
    }
}