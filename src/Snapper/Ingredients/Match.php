<?php
/**
 * Match
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients;

use Closure;
use Prewk\Option;
use Prewk\Option\{None, Some};
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\RecipeException;
use Prewk\Snapper\Ingredients\Match\MatchMapper;

/**
 * Match
 */
class Match implements Ingredient
{
    /**
     * @var string
     */
    private $field;

    /**
     * @var Closure
     */
    private $matcher;

    /**
     * Match constructor.
     *
     * @param string $field
     * @param Closure $matcher
     */
    public function __construct(string $field, Closure $matcher)
    {
        $this->field = $field;
        $this->matcher = $matcher;
    }

    /**
     * Get the MatchMapper for the ingredient
     *
     * @param array $row
     * @return Option Option<MatchMapper>
     * @throws RecipeException
     */
    protected function getMatcher(array $row): Option
    {
        if (!array_key_exists($this->field, $row)) return new None;

        $closure = $this->matcher;
        $matchMapper = $closure(new MatchMapper($this->field));

        if (!($matchMapper instanceof MatchMapper)) {
            throw new RecipeException("Match rule must return a MatchMapper");
        }

        return new Some($matchMapper);
    }

    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @param array $row
     * @param bool $circular
     * @return array Array of [type, id] tuples
     * @throws RecipeException
     */
    public function getDeps($value, array $row, bool $circular = false): array
    {
        return $this->getMatcher($row)
            ->map(function(MatchMapper $matchMapper) use ($value, $row) {
                return $matchMapper->getDeps($value, $row);
            })
            ->unwrapOr([]);
    }

    /**
     * Let the ingredient determine the value of the field
     *
     * @param $value
     * @param array $row
     * @param BookKeeper $books
     * @param bool $circular
     * @return Option
     */
    public function serialize($value, array $row, BookKeeper $books, bool $circular = false): Option
    {
        return $this->getMatcher($row)
            ->andThen(function(MatchMapper $matchMapper) use ($value, $row, $books) {
                return $matchMapper->serialize($value, $row, $books);
            });
    }

    /**
     * Let the ingredient determine the value of the field to insert into the database when deserializing
     *
     * @param $value
     * @param array $row
     * @param BookKeeper $books
     * @return Option
     */
    public function deserialize($value, array $row, BookKeeper $books): Option
    {
        return $this->serialize($value, $row, $books, false);
    }

    /**
     * Should return an array with fields required to be able to UPDATE a row
     *
     * @return string[]
     */
    public function getRequiredExtraFields(): array
    {
        return [$this->field];
    }
}