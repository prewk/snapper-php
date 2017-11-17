<?php
/**
 * MatchMapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Match;

use Prewk\Option;
use Prewk\Option\{None, Some};
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Ingredient;

/**
 * MatchMapper
 */
class MatchMapper
{
    /**
     * @var array
     */
    private $on = [];

    /**
     * @var array
     */
    private $patterns = [];

    /**
     * @var string
     */
    private $field;

    /**
     * @var Ingredient
     */
    private $fallback;

    /**
     * MatchMapper constructor
     *
     * @param string $field
     */
    public function __construct(string $field)
    {
        $this->field = $field;
    }

    /**
     * If value is matched, the field is considered to be the given ingredient
     *
     * @param string $value
     * @param Ingredient $ingredient
     * @return MatchMapper
     */
    public function on(string $value, Ingredient $ingredient): self
    {
        $this->on[$value] = $ingredient;

        return $this;
    }

    /**
     * If value is RegExp matched, the field is considered to be the given ingredient
     *
     * @param string $pattern
     * @param Ingredient $ingredient
     * @return MatchMapper
     */
    public function pattern(string $pattern, Ingredient $ingredient): self
    {
        $this->patterns[$pattern] = $ingredient;

        return $this;
    }

    /**
     * If no "on" or "pattern" was matched, this will be the fallback
     *
     * @param Ingredient $ingredient
     * @return MatchMapper
     */
    public function default(Ingredient $ingredient): self
    {
        $this->fallback = $ingredient;

        return $this;
    }

    /**
     * @param array $row
     * @return Option Option<Ingredient>
     */
    protected function getMatchedIngredient(array $row): Option
    {
        if (!array_key_exists($this->field, $row)) {
            return new None;
        }

        $comparee = $row[$this->field];

        if (array_key_exists($comparee, $this->on)) {
            return new Some($this->on[$comparee]);
        }

        foreach ($this->patterns as $pattern => $ingredient) {
            if (preg_match($pattern, $comparee) === 1) {
                return new Some($ingredient);
            }
        }

        if (isset($this->fallback)) {
            return new Some($this->fallback);
        }

        return new None;
    }

    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @param array $row
     * @param bool $circular
     * @return array Array of [type, id] tuples
     */
    public function getDeps($value, array $row, bool $circular = false): array
    {
        return $this->getMatchedIngredient($row)
            ->map(function(Ingredient $matched) use ($value, $row) {
                return $matched->getDeps($value, $row);
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
        return $this->getMatchedIngredient($row)
            ->andThen(function(Ingredient $matched) use ($value, $row, $books, $circular) {
                return $matched->serialize($value, $row, $books, $circular);
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
        return $this->getMatchedIngredient($row)
            ->andThen(function(Ingredient $matched) use ($value, $row, $books) {
                return $matched->deserialize($value, $row, $books);
            });
    }
}