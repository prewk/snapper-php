<?php
/**
 * TaskSequence
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Compiler\CreateTask;
use Prewk\Snapper\Compiler\Task;
use Prewk\Snapper\Compiler\TaskAlias;
use Prewk\Snapper\Compiler\TaskAssembledAlias;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Compiler\TaskValue;
use Prewk\Snapper\Compiler\UpdateTask;
use Prewk\Snapper\Errors\ForbiddenOperationException;
use Prewk\Snapper\Errors\InvalidTypeException;

/**
 * TaskSequence
 */
class TaskSequence implements Arrayable, Countable
{
    /**
     * @var Task[]
     */
    private $tasks;

    /**
     * TaskSequence constructor
     *
     * @param Task[] $tasks
     * @throws InvalidTypeException
     */
    public function __construct(
        array $tasks = []
    )
    {
        foreach ($tasks as $task) {
            if (!($task instanceof Task)) {
                throw new InvalidTypeException("TaskSequence requires an array of Tasks");
            }
        }
        
        $this->tasks = $tasks;
    }

    /**
     * Factory
     * 
     * @param Task[] $tasks
     * @return TaskSequence
     */
    public function make(array $tasks = []): TaskSequence
    {
        return new static($tasks);
    }

    /**
     * Create a TaskSequence node
     *
     * @param array $data
     * @throws InvalidTypeException
     * @return mixed
     */
    public static function makeNode(array $data)
    {
        switch ($data["type"]) {
            case "CREATE_TASK":
                return new CreateTask($data["entity"], $data["alias"], $data["columns"], array_map(function(array $value) {
                    return self::makeNode($value);
                }, $data["values"]));
            case "UPDATE_TASK":
                return new UpdateTask($data["entity"], $data["alias"], $data["keyName"], $data["columns"], array_map(function(array $value) {
                    return self::makeNode($value);
                }, $data["values"]));
            case "TASK_RAW_VALUE":
                return new TaskRawValue($data["value"]);
            case "TASK_ALIAS":
                return new TaskAlias($data["alias"]);
            case "TASK_ASSEMBLED_ALIAS":
                return new TaskAssembledAlias($data["parts"]);
            default:
                throw new InvalidTypeException("Can't create a TaskSequence node of type: $data[type]");
        }
    }
    
    /**
     * Create a TaskSequence from an array of Tasks
     * 
     * @param array $tasks
     * @return TaskSequence
     */
    public static function fromArray(array $tasks): TaskSequence
    {
        return new static(
            array_map(function(array $task) {
                return self::makeNode($task);
            }, $tasks)
        );
    }

    /**
     * Get task by index
     * 
     * @param int $index
     * @return Task
     */
    public function get(int $index): Task
    {
        return $this->tasks[$index];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function(Task $task) {
            return $task->toArray();
        }, $this->tasks);
    }

    /**
     * Count elements of an object
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->tasks);
    }

    /**
     * Run the tasks with the given closures acting as database repository interfaces
     *
     * @param Closure $inserter Closure shall insert and must return id of created entity: (string $entityName, string[] $columns, mixed[] $values) => int
     * @param Closure $updater Closure shall update entity with the given id: (string $entityName, int $id, string[] $columns, mixed[] $values) => void
     * @return array
     * @throws ForbiddenOperationException
     */
    public function toRepository(Closure $inserter, Closure $updater): array {
        $aliasLookup = [];

        foreach ($this->tasks as $task) {
            if ($task instanceof CreateTask) {
                $id = $inserter($task->getEntity(), $task->getColumns(), array_map(function(TaskValue $value) use (&$aliasLookup) {
                    return $value->getAsValue($aliasLookup);
                }, $task->getValues()));

                if (!is_scalar($id)) {
                    throw new ForbiddenOperationException("The inserter closure must return a scalar id");
                }

                $aliasLookup[$task->getId()] = $id;
            } elseif ($task instanceof UpdateTask) {
                if (!array_key_exists($task->getId(), $aliasLookup)) {
                    throw new ForbiddenOperationException("Needed missing value for alias {$task->getId()}");
                }

                $updater($task->getEntity(), $task->getKeyName(), $aliasLookup[$task->getId()], $task->getColumns(), array_map(function(TaskValue $value) use (&$aliasLookup) {
                    return $value->getAsValue($aliasLookup);
                }, $task->getValues()));
            }
        }

        return $aliasLookup;
    }
}