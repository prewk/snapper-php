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
     * @var string
     */
    private $keyName;

    /**
     * UpdateTask constructor
     * 
     * @param string $entity
     * @param int $id
     * @param string $keyName
     * @param array $columns
     * @param TaskValue[] $values
     */
    public function __construct(string $entity, $id, string $keyName, array $columns, array $values)
    {
        $this->keyName = $keyName;

        parent::__construct($entity, $id, $columns, $values);
    }

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
            "keyName" => $this->keyName,
            "columns" => $this->columns,
            "values" => array_map(function(TaskValue $value) {
                return $value->toArray();
            }, $this->values)
        ];
    }

    /**
     * Get key name
     * 
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }
}