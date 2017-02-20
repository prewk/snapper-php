<?php

namespace Prewk\Snapper\Compiler\Sorters;

use PHPUnit\Framework\TestCase;
use Prewk\Snapper;
use Prewk\Snapper\Compiler\CreateTask;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\TaskAlias;
use Prewk\Snapper\Schema\Entity;
use Prewk\Snapper\Snapshot;
use Prewk\Snapper\Snapshot\EntityRow;
use Prewk\SnapperSchema\TestProvider;

class SortByMostRequiredTest extends TestCase
{
    protected function makeCreateTask(IdMaker $idMaker, Entity $entitySchema, EntityRow $entityRow): CreateTask
    {
        $id = $idMaker->getId($entityRow->getName(), $entityRow->getKey());

        $columns = [];
        $values = [];
        $nameToColumnMap = [];

        foreach ($entitySchema->getFields() as $field) {
            $compiledField = $field->compile($idMaker, $entityRow->getFields());
            $compiledColumns = array_keys($compiledField);

            $columns = array_merge($columns, $compiledColumns);
            $values = array_merge($values, array_values($compiledField));
            $nameToColumnMap[$field->getName($entityRow->getFields())] = $compiledColumns;
        }

        return new CreateTask($entityRow->getName(), $id, $columns, $values, $nameToColumnMap);
    }

    public function test_that_it_sorts_by_most_required_task()
    {
        $sorter = new SortByMostRequired;
        $testProvider = new TestProvider;
        $idMaker = new IdMaker;

        $schema = Snapper::makeSchema($testProvider->getSchema());
        $entities = Snapper::makeSnapshot(json_decode($testProvider->getTransformed(), true));

        $createTasks = $entities->mapToArray(function(EntityRow $entityRow) use ($schema, $idMaker) {
            $entitySchema = $schema->getEntityByName($entityRow->getName());
            return $this->makeCreateTask($idMaker, $entitySchema, $entityRow);
        });

        $results = $sorter->sort(
            $idMaker,
            $entities,
            $createTasks
        );

        $this->assertEquals(Snapshot::fromArray(json_decode(file_get_contents(__DIR__ . "/sorted.json"), true)), $results);
    }
}