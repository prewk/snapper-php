<?php

namespace Prewk\Snapper;

use JsonSchema\Validator as JsonValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use Prewk\Snapper;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;
use Prewk\Snapper\Ingredients\Json\JsonRecipe;
use Prewk\Snapper\Ingredients\Json\MatchedJson;
use Prewk\Snapper\Ingredients\Json\PatternReplacer;
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
                                return $matched->pattern("/<<\"(.*?)\">>/", function(PatternReplacer $replacer, string $replacement) {
                                    return $replacer->replace("children", 1, "<<\"$replacement\">>");
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

    public function test_reference_validator()
    {
        $recipes = $this->getTestRecipes();
        $testRows = $this->getTestRows();

        $serializer = new Serializer(new Sorter, new SerializationBookKeeper, $recipes);

        foreach ($testRows as list($type, $row)) {
            $serializer->add($type, $row);
        }

        $serialization = $serializer->compile()->getOps();
        $validator = new Validator(new DeserializationBookKeeper, $recipes);

        // This serialization should validate
        $this->assertTrue($validator->validate($serialization));

        // Break it
        $serialization[0]["rows"][0]["id"] = "BROKEN";

        // This serialization shouldn't validate
        $this->assertFalse($validator->validate($serialization));
    }

    public function test_schema_validator()
    {
        $json = json_encode($this->getTestRecipes());
        $decoded = json_decode($json);

        $validator = new SchemaValidator(new JsonValidator);

        // This schema should validate
        $this->assertTrue($validator->validate($decoded->roots)->isOk());
        $this->assertTrue($validator->validate($decoded->nodes)->isOk());
        $this->assertTrue($validator->validate($decoded->polys)->isOk());
        $this->assertTrue($validator->validate($decoded->children)->isOk());

        // Break it
        $decoded->roots->ingredients->name->type = "REF";
        $this->assertTrue($validator->validate($decoded->roots)->isErr());
    }

    public function test_analyzer()
    {
        $polys = $this->getTestRecipes()["polys"];

        $snapper = new Snapper(new Sorter, new SerializationBookKeeper, new DeserializationBookKeeper);

        $stats = $snapper->analyze($polys, [
            "id" => 1,
            "polyable_type" => "NODE",
            "polyable_id" => 2,
            "json" => json_encode([
                "deep" => [
                    "child_id" => 3
                ],
                "foo_child" => 'Lorem <<"4">> ipsum dolor amet <<"5">>',
                "bar_child" => 'Lorem ipsum <<"6">> dolor <<"7">> amet',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $this->assertCount(6, $stats["deps"]);
        $this->assertContains(["nodes", "2"], $stats["deps"]);
        $this->assertContains(["children", 3], $stats["deps"]);
        $this->assertContains(["children", "4"], $stats["deps"]);
        $this->assertContains(["children", "5"], $stats["deps"]);
        $this->assertContains(["children", "6"], $stats["deps"]);
        $this->assertContains(["children", "7"], $stats["deps"]);
        $this->assertEquals(1, $stats["primary"]);
        $this->assertEquals([], $stats["missingFields"]);
    }

    private function insert(PDO $db, $table, array $rows)
    {
        $cols = [];
        $vals = [];
        $vars = [];
        foreach ($rows as $index => $row) {
            $innerVals = [];
            foreach ($row as $col => $value) {
                if ($col === "id") continue;

                if (!in_array($col, $cols)) {
                    $cols[] = $col;
                }

                $var = ":{$col}_$index";
                $vars[$var] = $value;
                $innerVals[] = $var;
            }
            $vals[] = "(" . implode(", ", $innerVals) . ")";
        }

        $insert = "INSERT INTO $table (" . implode(", ", $cols) . ") VALUES " . implode(", ", $vals);

        $stmt = $db->prepare($insert);
        $stmt->execute($vars);
        $lastId = intval($db->lastInsertId());

        return range($lastId - count($rows) + 1, $lastId);
    }

    private function update(PDO $db, $table, array $rows)
    {
        foreach ($rows as $row) {
            $sets = [];
            $vars = [":id" => $row["id"]];
            foreach ($row as $field => $value) {
                $sets[] = "$field=:$field";
                $vars[":$field"] = $value;
            }

            $update = "UPDATE $table SET " . implode(", ", $sets) . " WHERE id = :id";
            $stmt = $db->prepare($update);
            $stmt->execute($vars);
        }
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
            "roots" => function(array $rows) use ($db) {
                return $this->insert($db, "roots", $rows);
            },
            "nodes" => function(array $rows) use ($db) {
                return $this->insert($db, "nodes", $rows);
            },
            "polys" => function(array $rows) use ($db) {
                return $this->insert($db, "polys", $rows);
            },
            "children" => function(array $rows) use ($db) {
                return $this->insert($db, "children", $rows);
            },
        ];

        $updaters = [
            "roots" => function(array $rows) use ($db) {
                $this->update($db, "roots", $rows);
            },
            "nodes" => function(array $rows) use ($db) {
                $this->update($db, "nodes", $rows);
            },
            "polys" => function(array $rows) use ($db) {
                $this->update($db, "polys", $rows);
            },
            "children" => function(array $rows) use ($db) {
                $this->update($db, "children", $rows);
            },
        ];

        $deserializer = new Deserializer(new DeserializationBookKeeper, $recipes, $inserters, $updaters);

        $rootDeps = [];
        $deserializer->onDeps("roots", function($type, $dependee, $dependency) use (&$rootDeps) {
            $rootDeps["$type/$dependee"] = $dependency;
        });

        $deserializer->deserialize($serialization->getOps());

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

        $this->assertEquals([
            "nodes/1" => 1,
            "nodes/2" => 1,
            "nodes/3" => 1,
            "children/1" => 1,
        ], $rootDeps);
    }
}