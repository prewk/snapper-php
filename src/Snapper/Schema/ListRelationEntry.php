<?php
/**
 * ListRelationEntry
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Prewk\Snapper\Collections\MapDependencyCollection;
use stdClass;

/**
 * ListRelationEntry
 */
class ListRelationEntry extends MapEntry
{
    /**
     * @var string
     */
    private $relation;

    /**
     * ListRelationEntry constructor.
     * @param MapEntryPath $path
     * @param string $relation
     */
    public function __construct(
        MapEntryPath $path,
        string $relation
    ) {
        parent::__construct($path);
        $this->relation = $relation;
    }

    /**
     * Get relation
     *
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Get all dependencies within this map
     *
     * @param MapDependencyCollection $collection
     * @param array $map
     * @param array $dotMap
     * @return MapDependencyCollection
     */
    public function getDependencies(MapDependencyCollection $collection, array $map, array $dotMap): MapDependencyCollection
    {
        foreach ($this->path->query($map, $dotMap) as $path => $value) {
            if (is_array($value) && array_key_exists(0, $value)) {
                foreach ($value as $index => $subValue) {
                    if (is_scalar($subValue)) {
                        $collection->push($subValue, $this->relation, "$path.$index");
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "LIST_RELATION_ENTRY",
            "path" => $this->path,
            "relation" => $this->relation,
        ];
    }

    /**
     * Convert to stdClass
     *
     * @return stdClass
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass;

        $obj->type = "LIST_RELATION_ENTRY";
        $obj->path = $this->path->getPath();
        $obj->relation = $this->relation;

        return $obj;
    }
}