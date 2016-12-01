<?php
/**
 * CreateTask
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;
use Prewk\Snapper\Errors\CompilerException;

/**
 * CreateTask
 */
class CreateTask extends Task
{
    protected $nameToColumnMap = [];

    /**
     * CreateTask constructor
     * 
     * @param string $entity
     * @param int $id
     * @param array $columns
     * @param TaskValue[] $values
     * @param array $nameToColumnMap
     */
    public function __construct(
        string $entity,
        int $id,
        array $columns,
        array $values,
        array $nameToColumnMap = []
    ) {
        parent::__construct($entity, $id, $columns, $values);
        $this->nameToColumnMap = $nameToColumnMap;
    }

    /**
     * Create an UpdateTask from this CreateTask
     *
     * @param array $problematicColumns
     * @return UpdateTask
     */
    public function toUpdateTask(array $problematicColumns): UpdateTask
    {
        $updateColumns = [];
        $updateValues = [];

        foreach ($this->columns as $index => $column) {
            if (in_array($column, $problematicColumns)) {
                $updateColumns[] = $column;
                $updateValues[] = $this->values[$index];
            }
        }

        return new UpdateTask($this->entity, $this->id, $updateColumns, $updateValues);
    }
    
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "CREATE_TASK",
            "entity" => $this->entity,
            "alias" => $this->id,
            "columns" => $this->columns,
            "values" => array_map(function(TaskValue $value) {
                return $value->toArray();
            }, $this->values)
        ];
    }

    /**
     * Get field name, given a column name
     *
     * @param string $column
     * @return string
     * @throws CompilerException
     */
    public function getFieldName(string $column): string
    {
        foreach ($this->nameToColumnMap as $name => $columns) {
            if (in_array($column, $columns)) {
                return $name;
            }
        }
        
        throw new CompilerException("Couldn't find the field name for a column '$column'");
    }
}