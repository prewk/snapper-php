<?php
/**
 * ListRelationEntry
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Prewk\Snapper\Collections\MapDependencyCollection;
use Prewk\Snapper\Errors\InvalidEnumException;
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
     * @var string
     */
    private $relationCondition;

    /**
     * @var string
     */
    private $conditionMatcher;

    /**
     * ListRelationEntry constructor
     *
     * @param MapEntryPath $path
     * @param string $relation
     * @param string $relationCondition
     * @param string $conditionMatcher
     * @throws InvalidEnumException
     */
    public function __construct(
        MapEntryPath $path,
        string $relation,
        string $relationCondition,
        string $conditionMatcher = ""
    ) {
        if (!in_array($relationCondition, ["NON_ZERO_INT", "REGEXP", "NONE", "TRUTHY"])) {
            throw new InvalidEnumException("Invalid 'relationCondition' enum: $relationCondition");
        }

        parent::__construct($path);

        $this->relation = $relation;
        $this->relationCondition = $relationCondition;
        $this->conditionMatcher = $conditionMatcher;
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
                    switch ($this->relationCondition) {
                        case "NON_ZERO_INT":
                            if (is_int($subValue) && $subValue > 0) {
                                $collection->push($subValue, $this->relation, "$path.$index");
                            }
                            break;
                        case "TRUTHY":
                            if ($subValue) {
                                $collection->push($subValue, $this->relation, "$path.$index");
                            }
                            break;
                        case "REGEXP":
                            if (preg_match($this->conditionMatcher, (string)$subValue) === 1) {
                                $collection->push($subValue, $this->relation, "$path.$index");
                            }
                            break;
                        case "NONE":
                            $collection->push($subValue, $this->relation, "$path.$index");
                            break;
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
            "relationCondition" => $this->relationCondition,
            "conditionMatcher" => $this->conditionMatcher,
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
        $obj->relationCondition = $this->relationCondition;
        $obj->conditionMatcher = $this->conditionMatcher;

        return $obj;
    }
}