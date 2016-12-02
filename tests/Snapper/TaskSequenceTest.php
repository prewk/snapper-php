<?php

use PHPUnit\Framework\TestCase;
use Prewk\Snapper\TaskSequence;

class TaskSequenceTest extends TestCase
{
    public function test_that_toRepository_inserts_and_updates_as_intended()
    {
        $sequence = TaskSequence::fromArray([
            [
                "type" => "CREATE_TASK",
                "entity" => "foos",
                "alias" => 0,
                "columns" => ["bar_id", "content"],
                "values" => [
                    ["type" => "TASK_RAW_VALUE", "value" => 0],
                    ["type" => "TASK_RAW_VALUE", "value" => "Lorem ipsum"]
                ],
            ],
            [
                "type" => "CREATE_TASK",
                "entity" => "bars",
                "alias" => 1,
                "columns" => ["foo_id"],
                "values" => [
                    ["type" => "TASK_ALIAS", "alias" => 0],
                ],
            ],
            [
                "type" => "CREATE_TASK",
                "entity" => "bars",
                "alias" => 2,
                "columns" => ["foo_id"],
                "values" => [
                    ["type" => "TASK_ALIAS", "alias" => 0],
                ],
            ],
            [
                "type" => "UPDATE_TASK",
                "entity" => "foos",
                "alias" => 0,
                "keyName" => "id",
                "columns" => ["bar_id"],
                "values" => [
                    ["type" => "TASK_ALIAS", "alias" => 1],
                ],
            ],
        ]);

        $ids = 100;

        $inserts = [];
        $updates = [];

        $sequence->toRepository(
            function($entity, $columns, $values) use (&$ids, &$inserts) {
                $inserts[] = ["INSERT INTO $entity (" . implode(", ", $columns) . ") VALUES (" . implode(", ", array_map(function() { return "?"; }, $values)) . ")", $values];

                return $ids++;
            },
            function($entity, $keyName, $id, $columns, $values) use (&$updates) {
                $updates[] = ["UPDATE $entity SET " . implode(", ", array_map(function($column) { return "$column = ?"; }, $columns)) . " WHERE $keyName = ?", array_merge($values, [$id])];
            }
        );

        $this->assertCount(3, $inserts);
        $this->assertEquals(["INSERT INTO foos (bar_id, content) VALUES (?, ?)", [0, "Lorem ipsum"]], $inserts[0]);
        $this->assertEquals(["INSERT INTO bars (foo_id) VALUES (?)", [100]], $inserts[1]);
        $this->assertEquals(["INSERT INTO bars (foo_id) VALUES (?)", [100]], $inserts[2]);

        $this->assertCount(1, $updates);
        $this->assertEquals(["UPDATE foos SET bar_id = ? WHERE id = ?", [101, 100]], $updates[0]);
    }
}