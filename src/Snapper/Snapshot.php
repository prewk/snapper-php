<?php
/**
 * Snapshot
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;

use Closure;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Errors\InvalidTypeException;
use Prewk\Snapper\Snapshot\EntityRow;

/**
 * Snapshot
 */
class Snapshot implements Countable, Arrayable
{
    /**
     * @var array
     */
    private $entityRows;

    /**
     * Snapshot constructor
     *
     * @param EntityRow[] $entityRows
     * @throws InvalidTypeException
     */
    public function __construct(array $entityRows = [])
    {
        foreach ($entityRows as $entityRow) {
            if (!($entityRow instanceof EntityRow)) {
                throw new InvalidTypeException("Given array must be an array of EntityRow objects");
            }
        }
        $this->entityRows = $entityRows;
    }

    /**
     * Factory
     * 
     * @param EntityRow[] $entityRows
     * @return Snapshot
     */
    public function make(array $entityRows = []): Snapshot
    {
        return new static($entityRows);
    }

    /**
     * Create a Snapshot instance from an array source
     * 
     * @param array $rows
     * @return Snapshot
     */
    public static function fromArray(array $rows): Snapshot
    {
        return new static(
            array_map(function(array $row) {
                return new EntityRow($row["name"], $row["key"], $row["fields"]);
            }, $rows)
        );
    }

    /**
     * Get entity rows
     *
     * @return array
     */
    public function getEntityRows()
    {
        return $this->entityRows;
    }

    /**
     * Foreach
     *
     * @param Closure $forEacher
     */
    public function each(Closure $forEacher)
    {
        array_map($forEacher, $this->entityRows);
    }

    /**
     * Map a snapshot immutably
     *
     * @param Closure $mapper
     * @return Snapshot
     */
    public function map(Closure $mapper): Snapshot
    {
        return new static(array_map($mapper, $this->entityRows));
    }

    /**
     * Map a snapshot's entity rows to an array immutably
     *
     * @param Closure $mapper
     * @return EntityRow[]
     */
    public function mapToArray(Closure $mapper): array
    {
        return array_map($mapper, $this->entityRows);
    }
    
    /**
     * Sort a snapshot immutably
     *
     * @param Closure $comparator
     * @return Snapshot
     */
    public function sort(Closure $comparator): Snapshot
    {
        $copy = $this->entityRows;

        usort($copy, $comparator);

        return new static($copy);
    }

    /**
     * Count
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->entityRows);
    }

    /**
     * Has entity?
     * 
     * @param string $entity
     * @param $key
     * @return bool
     */
    public function hasEntity(string $entity, $key): bool
    {
        foreach ($this->entityRows as $entityRow) {
            if ($entityRow->getName() === $entity && $entityRow->getKey() === $key) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function(EntityRow $row) {
            return $row->toArray();
        }, $this->entityRows);
    }
}