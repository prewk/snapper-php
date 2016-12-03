<?php
/**
 * RegExpRelationEntry
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Prewk\Snapper\Collections\MapDependencyCollection;

use Prewk\Snapper\Errors\InvalidTypeException;
use stdClass;

/**
 * RegExpRelationEntry
 */
class RegExpRelationEntry extends MapEntry
{
    /**
     * @var RegExpRelationMatcher[]
     */
    private $matchers;

    /**
     * RegExpRelationEntry constructor
     *
     * @param MapEntryPath $path
     * @param RegExpRelationMatcher[] $matchers
     * @throws InvalidTypeException
     */
    public function __construct(
        MapEntryPath $path,
        array $matchers
    ) {
        parent::__construct($path);

        foreach ($matchers as $matcher) {
            if (!($matcher instanceof RegExpRelationMatcher)) {
                throw new InvalidTypeException("Matchers must be of type RegExpRelationMatcher");
            }
        }
        $this->matchers = $matchers;
    }

    /**
     * Get matchers
     *
     * @return RegExpRelationMatcher[]
     */
    public function getMatchers(): array
    {
        return $this->matchers;
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
            if (is_string($value)) {
                foreach ($this->matchers as $matcher) {
                    $collection = $matcher->getDependencies($collection, $path, $value);
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
    public function toArray()
    {
        return [
            "type" => "REG_EXP_RELATION_ENTRY",
            "path" => $this->path,
            "matchers" => array_map(function(RegExpRelationMatcher $matcher) {
                return $matcher->toArray();
            }, $this->matchers),
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

        $obj->type = "REG_EXP_RELATION_ENTRY";
        $obj->path = $this->path->getPath();
        $obj->matchers = array_map(function(RegExpRelationMatcher $matcher) {
            return $matcher->toObject();
        }, $this->matchers);

        return $obj;
    }
}