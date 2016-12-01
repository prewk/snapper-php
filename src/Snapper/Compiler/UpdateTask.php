<?php
/**
 * UpdateTask
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

/**
 * UpdateTask
 */
class UpdateTask extends Task
{

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "UPDATE_TASK",
            "entity" => $this->entity,
            "alias" => $this->id,
            "columns" => $this->columns,
            "values" => array_map(function(TaskValue $value) {
                return $value->toArray();
            }, $this->values)
        ];
    }
}