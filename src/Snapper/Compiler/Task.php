<?php
/**
 * Task
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

/**
 * Task
 */
abstract class Task implements Arrayable
{
    /**
     * @var string
     */
    protected $entity;

    /**
     * @var array|\string[]
     */
    protected $columns;

    /**
     * @var TaskValue[]
     */
    protected $values;

    /**
     * @var int
     */
    protected $id;

    /**
     * Task constructor
     *
     * @param string $entity
     * @param int $id
     * @param string[] $columns
     * @param TaskValue[] $values
     */
    public function __construct(
        string $entity,
        int $id,
        array $columns,
        array $values
    )
    {
        $this->entity = $entity;
        $this->columns = $columns;
        $this->values = $values;
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Get columns
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get values
     *
     * @return TaskValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get unique dependencies in an int array
     *
     * @return int[]
     */
    public function getDependencies(): array
    {
        return array_unique(
            Arr::flatten(
                array_map(
                    function(TaskValue $value) {
                        return $value->getDependencies();
                    },
                    $this->values
                )
            )
        );
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    abstract public function toArray();
}