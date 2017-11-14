<?php
/**
 * MorphMapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Morph;

use Prewk\Option;
use Prewk\Option\None;
use Prewk\Option\Some;
use Prewk\Snapper\BookKeeper;

/**
 * MorphMapper
 */
class MorphMapper
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
}