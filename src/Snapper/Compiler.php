<?php
/**
 * Compiler
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;

use Prewk\Snapper\Compiler\CreateTask;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\IdResolver;
use Prewk\Snapper\Compiler\Sorter;
use Prewk\Snapper\Compiler\UpdateTask;
use Prewk\Snapper\Errors\CompilerException;
use Prewk\Snapper\Schema\Entity;
use Prewk\Snapper\Snapshot\EntityRow;

/**
 * Compiler
 */
class Compiler
{
    /**
     * @var IdMaker
     */
    private $idMaker;

    /**
     * @var IdResolver
     */
    private $idResolver;

    /**
     * @var TaskSequence
     */
    private $taskSequence;
    /**
     * @var Sorter
     */
    private $sorter;

    /**
     * Compiler constructor
     *
     * @param IdMaker $idMaker
     * @param IdResolver $idResolver
     * @param TaskSequence $taskSequence
     * @param Sorter $sorter
     */
    public function __construct(
        IdMaker $idMaker,
        IdResolver $idResolver,
        TaskSequence $taskSequence,
        Sorter $sorter
    )
    {
        $this->idMaker = $idMaker;
        $this->idResolver = $idResolver;
        $this->taskSequence = $taskSequence;
        $this->sorter = $sorter;
    }

    /**
     * Make a CreateTask
     *
     * @param IdMaker $idMaker
     * @param Entity $entitySchema
     * @param EntityRow $entityRow
     * @param array $problematicFields
     * @return CreateTask
     */
    protected function makeCreateTask(IdMaker $idMaker, Entity $entitySchema, EntityRow $entityRow, array $problematicFields = []): CreateTask
    {
        $id = $idMaker->getId($entityRow->getName(), $entityRow->getKey());

        $columns = [];
        $values = [];
        $nameToColumnMap = [];

        foreach ($entitySchema->getFields() as $field) {
            if (in_array($field->getName(), $problematicFields)) {
                // Circular override
                $compiledField = $field->compile($idMaker, $entityRow->getFields(), true);
            } else {
                // Normal compilation
                $compiledField = $field->compile($idMaker, $entityRow->getFields());
            }

            $compiledColumns = array_keys($compiledField);

            $columns = array_merge($columns, $compiledColumns);
            $values = array_merge($values, array_values($compiledField));
            $nameToColumnMap[$field->getName($entityRow->getFields())] = $compiledColumns;
        }

        return new CreateTask($entityRow->getName(), $id, $columns, $values, $nameToColumnMap);
    }

    /**
     * Compile a snapshot with the given schema into a task sequence
     *
     * @param Schema $schema
     * @param Snapshot $entities
     * @return TaskSequence
     * @throws CompilerException
     */
    public function compile(Schema $schema, Snapshot $entities): TaskSequence
    {
        $idMaker = $this->idMaker->make($schema->getMorphTable());
        $idResolver = $this->idResolver->make();

        $createTasks = [];
        $updateTasks = [];

        // Pre-compile optimization - sort the entities
        $unsortedCreateTasks = $entities->mapToArray(function(EntityRow $entityRow) use ($schema, $idMaker) {
            $entitySchema = $schema->getEntityByName($entityRow->getName());
            return $this->makeCreateTask($idMaker, $entitySchema, $entityRow);
        });

        $entities = $this->sorter->sort($idMaker, $entities, $unsortedCreateTasks);

        // Compile
        $entities->each(function(EntityRow $entityRow) use (&$createTasks, &$updateTasks, $schema, $idMaker, $idResolver) {
            $entitySchema = $schema->getEntityByName($entityRow->getName());
            $createTask = $this->makeCreateTask($idMaker, $entitySchema, $entityRow);
            $id = $createTask->getId();
            $taskDeps = $createTask->getDependencies();

            $circularDeps = $idResolver->findCircularDeps($id, $taskDeps);

            if (count($circularDeps)) {
                $problematicFields = [];
                $problematicColumns = [];

                foreach ($createTask->getValues() as $index => $value) {
                    $isProblematic = false;
                    foreach ($value->getDependencies() as $valueDep) {
                        if (in_array($valueDep, $circularDeps)) {
                            // This TaskValue has a circular dependency
                            $isProblematic = true;
                        }
                    }

                    if ($isProblematic) {
                        $problematicColumns[] = $createTask->getColumns()[$index];
                        $problematicFields[] = $createTask->getFieldName($createTask->getColumns()[$index]);
                    }
                }

                if (!count($problematicFields)) {
                    throw new CompilerException("Internal error: Couldn't find problematic fields when sorting out a circular dependency");
                }

                // The original CreateTask should be an UpdateTask instead
                $updateTasks[] = $createTask->toUpdateTask($entitySchema, $problematicColumns);

                // Re-create the CreateTask with fewer deps
                $createTask = $this->makeCreateTask($idMaker, $entitySchema, $entityRow, $problematicFields);
                $taskDeps = $createTask->getDependencies();
            }

            if (count($taskDeps)) {
                $idResolver->listen($id, $taskDeps, function() use ($createTask, $idResolver, &$createTasks, $idMaker) {
                    $createTasks[] = $createTask;
                    $idResolver->report($createTask->getId());
                });
            } else {
                $createTasks[] = $createTask;
                $idResolver->report($createTask->getId());
            }
        });

        // Merge creates and updates into one sequence
        $sequence = array_merge($createTasks, $updateTasks);

        if (count($createTasks) !== count($entities)) {
            throw new CompilerException("Snapshot uncompilable - can't resolve all dependency trees");
        }

        return $this->taskSequence->make($sequence);
    }

    /**
     * Checks if this entity's dependency has anything pointing back to it
     *
     * @param int $id
     * @param array $allDepenencies
     * @param array $scope
     * @return bool
     */
    protected function hasShallowCircularDependency(int $id, array $allDepenencies, array $scope): bool {
        foreach ($scope as $dep) {
            foreach ($allDepenencies[$dep] as $othersDep) {
                if ($othersDep === $id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create an UpdateTask from a CreateTask
     *
     * @param CreateTask $task
     * @return UpdateTask
     */
    protected function makeUpdateTask(CreateTask $task): UpdateTask
    {
        return new UpdateTask(
            $task->getEntity(),
            $task->getId(),
            $task->getColumns(),
            $task->getValues()
        );
    }
}