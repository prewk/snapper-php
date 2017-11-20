<?php
/**
 * MorphMapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Morph;

use JsonSerializable;
use Prewk\Option;
use Prewk\Option\None;
use Prewk\Option\Some;
use Prewk\Snapper\BookKeeper;

/**
 * MorphMapper
 */
class MorphMapper implements JsonSerializable
{
    /**
     * @var array
     */
    private $morphMap = [];

    /**
     * Map a value in the morphable type field against a recipe-compatible entity type
     *
     * @param string $from
     * @param string $to
     * @return MorphMapper
     */
    public function map(string $from, string $to): self
    {
        $this->morphMap[$from] = $to;

        return $this;
    }

    /**
     * Help Morph find its dependency
     *
     * @param string $morphType
     * @param $value
     * @return array
     */
    public function getDeps(string $morphType, $value): array
    {
        if (array_key_exists($morphType, $this->morphMap)) {
            return [[$this->morphMap[$morphType], $value]];
        }

        return [];
    }

    /**
     * Help Morph resolve its value
     *
     * @param string $morphType
     * @param $value
     * @param BookKeeper $books
     * @return Option
     */
    public function resolve(string $morphType, $value, BookKeeper $books): Option
    {
        if (array_key_exists($morphType, $this->morphMap)) {
            return new Some($books->resolveId($this->morphMap[$morphType], $value));
        }

        return new None;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            "morph_map" => $this->morphMap,
        ];
    }

    /**
     * Create a MorphMapper from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return MorphMapper
     */
    public function fromArray(array $config): MorphMapper
    {
        $mapper = (new static);
        foreach ($config["morph_map"] as $from => $to) {
            $mapper->map($from, $to);
        }

        return $mapper;
    }
}