<?php
/**
 * RegExpRelationMatcher
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Collections\MapDependencyCollection;
use Prewk\Snapper\Errors\InvalidEnumException;
use stdClass;

/**
 * RegExpRelationMatcher
 */
class RegExpRelationMatcher implements Arrayable, Objectable
{
    /**
     * @var string
     */
    private $expression;

    /**
     * @var array
     */
    private $relations;

    /**
     * @var string
     */
    private $cast;

    /**
     * RegExpRelationMatcher constructor
     *
     * @param string $expression
     * @param array $relations
     * @param string $cast
     * @throws InvalidEnumException
     */
    public function __construct(
        string $expression,
        array $relations,
        string $cast
    ) {
        $this->expression = $expression;
        $this->relations = $relations;

        if (!in_array($cast, ["NONE", "INTEGER", "STRING", "AUTO"])) {
            throw new InvalidEnumException("Encountered invalid RegExpRelationMatcher cast enum: $cast");
        }
        $this->cast = $cast;
    }

    /**
     * Get expression
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get relations
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Cast value
     *
     * @param mixed $value
     * @return mixed
     */
    protected function cast($value)
    {
        switch ($this->cast) {
            case "INTEGER":
                return intval($value);
                break;
            case "STRING":
                return "" . $value;
                break;
            case "AUTO":
                return is_numeric($value) ? intval($value) : $value;
            default:
                return $value;
        }
    }

    /**
     * Get dependencies in text
     *
     * @param MapDependencyCollection $collection
     * @param string $basePath
     * @param string $subject
     * @return MapDependencyCollection
     */
    public function getDependencies(MapDependencyCollection $collection, string $basePath, string $subject): MapDependencyCollection
    {
        preg_match_all($this->expression, $subject, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches) === count($this->relations)) {
            foreach ($matches as $index => $match) {
                list($value, $offset) = $match[0];

                if (is_string($this->relations[$index])) {
                    $collection->push(
                        $this->cast($value),
                        $this->relations[$index],
                        $basePath,
                        true,
                        $offset,
                        $offset + strlen($value)
                    );
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
            "type" => "REG_EXP_RELATION_MATCHER",
            "expression" => $this->expression,
            "relations" => $this->relations,
            "cast" => $this->cast,
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

        $obj->type = "REG_EXP_RELATION_MATCHER";
        $obj->expression = $this->expression;
        $obj->relations = $this->relations;
        $obj->cast = $this->cast;

        return $obj;
    }
}