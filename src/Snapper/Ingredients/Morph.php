<?php
/**
 * Morph
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
use Prewk\Snapper\Ingredients\Morph\MorphMapper;

/**
 * Morph
 */
class Morph implements Ingredient
{
    /**
     * @var string
     */
    private $field;

    /**
     * @var Closure
     */
    private $morphMapper;

    /**
     * @var array
     */
    private $optionalValues = [];

    /**
     * Morph constructor
     *
     * @param string $field
     * @param Closure $morphMapper
     */
    public function __construct(string $field, Closure $morphMapper)
    {
        $this->field = $field;
        $this->morphMapper = $morphMapper;
    }

    /**
     * Specify which values should be treated as optional
     *
     * @param array ...$optionalValues
     * @return Morph
     */
    public function optional(...$optionalValues): self
    {
        $this->optionalValues = $optionalValues;
    }

    /**
     * Get the MorphMapper for the ingredient
     *
     * @param $value
     * @param array $row
     * @return Option
     * @throws RecipeException
     */
    protected function getMorphMapper($value, array $row): Option
    {
        if (!array_key_exists($this->field, $row)) {
            return new None;
        }

        $morphType = $row[$this->field];

        foreach ($this->optionalValues as $opt) {
            if ($opt === $value) return new None;
            if ($opt === $morphType) return new None;
        }

        $closure = $this->morphMapper;
        $morphMapper = $closure(new MorphMapper);

        if (!($morphMapper instanceof MorphMapper)) {
            throw new RecipeException("Morph rule closure must return a MorphMapper");
        }

        return new Some($morphMapper);
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
        return $this->getMorphMapper($value, $row)
            ->map(function(MorphMapper $morphMapper) use ($value, $row) {
                $morphType = $row[$this->field];

                return $morphMapper->getDeps($morphType, $value);
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
     * @throws RecipeException
     */
    public function serialize($value, array $row, BookKeeper $books, bool $circular = false): Option
    {
        return $this->getMorphMapper($value, $row)
            ->andThen(function(MorphMapper $morphMapper) use ($value, $row, $books) {
                $morphType = $row[$this->field];

                return $morphMapper->resolve($morphType, $value, $books);
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