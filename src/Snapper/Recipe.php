<?php
/**
 * Recipe
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Closure;
use Prewk\Snapper\Ingredients\Circular;
use Prewk\Snapper\Ingredients\Ingredient;
use Prewk\Snapper\Ingredients\Json;
use Prewk\Snapper\Ingredients\Match;
use Prewk\Snapper\Ingredients\Morph;
use Prewk\Snapper\Ingredients\Raw;
use Prewk\Snapper\Ingredients\Ref;
use Prewk\Snapper\Ingredients\Value;

/**
 * Recipe
 */
class Recipe
{
    /**
     * @var string
     */
    private $primaryKey;

    /**
     * @var array
     */
    private $ingredients;

    /**
     * Recipe constructor
     *
     * @param string|null $primaryKey
     * @param array $ingredients
     */
    public function __construct(string $primaryKey = null, array $ingredients = [])
    {
        $this->primaryKey = $primaryKey;
        $this->ingredients = $ingredients;
    }

    /**
     * @param string $field
     * @return Recipe
     */
    public function primary(string $field): Recipe
    {
        return new static($field, $this->ingredients);
    }

    /**
     * @param array $ingredients
     * @return Recipe
     */
    public function ingredients(array $ingredients): Recipe
    {
        return new static($this->primaryKey, $ingredients);
    }

    /**
     * @param string $type
     * @return Ref
     */
    public function ref(string $type): Ref
    {
        return new Ref($type);
    }

    /**
     * @return Value
     */
    public function value(): Value
    {
        return new Value;
    }

    /**
     * @param $value
     * @return Raw
     */
    public function raw($value): Raw
    {
        return new Raw($value);
    }

    /**
     * @param string $field
     * @param Closure $morphMapper
     * @return Morph
     */
    public function morph(string $field, Closure $morphMapper): Morph
    {
        return new Morph($field, $morphMapper);
    }

    /**
     * @param string $field
     * @param Closure $matchMapper
     * @return Match
     */
    public function match(string $field, Closure $matchMapper): Match
    {
        return new Match($field, $matchMapper);
    }

    /**
     * @param Closure $jsonRecipe
     * @return Json
     */
    public function json(Closure $jsonRecipe): Json
    {
        return new Json($jsonRecipe);
    }

    /**
     * @param Ingredient $real
     * @param Ingredient $fallback
     * @return Circular
     */
    public function circular(Ingredient $real, Ingredient $fallback): Circular
    {
        return new Circular($real, $fallback);
    }

    /**
     * @return array
     */
    public function getIngredients(): array
    {
        return $this->ingredients;
    }

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }
}