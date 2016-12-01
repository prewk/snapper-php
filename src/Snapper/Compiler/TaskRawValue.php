<?php
/**
 * TaskRawValue
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

/**
 * TaskRawValue
 */
class TaskRawValue implements TaskValue
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * TaskRawValue constructor
     *
     * @param mixed $value
     */
    public function __construct(
        $value
    )
    {
        $this->value = $value;
    }

    /**
     * Get dependencies
     *
     * @return int[]
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "TASK_RAW_VALUE",
            "value" => $this->value,
        ];
    }

    /**
     * Settle TaskValue with the given alias-id lookup table
     *
     * @param array $aliasLookup
     * @return mixed
     */
    public function getAsValue(array $aliasLookup)
    {
        return $this->value;
    }
}