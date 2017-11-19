<?php
/**
 * Recipe
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Closure;
use JsonSerializable;
use Prewk\Snapper\Exceptions\RecipeException;
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
class Recipe implements JsonSerializable
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

    /**
     * Create a recipe from an array
     *
     * @param array $recipe
     * @return Recipe
     */
    public static function fromArray(array $recipe): Recipe
    {
        $primaryKey = $recipe["primary_key"];
        $ingredients = array_map(function(array $ingredient) {
            return static::ingredientFromArray($ingredient);
        }, $recipe["ingredients"]);

        return new static($primaryKey, $ingredients);
    }

    /**
     * Create an Ingredient from an array
     *
     * @param array $ingredient
     * @return Ingredient
     * @throws RecipeException
     */
    public static function ingredientFromArray(array $ingredient): Ingredient
    {
        switch ($ingredient["type"]) {
            case "VALUE":
                return Value::fromArray($ingredient["config"]);
            case "REF":
                return Ref::fromArray($ingredient["config"]);
            case "RAW":
                return Raw::fromArray($ingredient["config"]);
            case "MORPH":
                return Morph::fromArray($ingredient["config"]);
            case "MATCH":
                return Match::fromArray($ingredient["config"]);
            case "JSON":
                return Json::fromArray($ingredient["config"]);
            case "CIRCULAR":
                return Circular::fromArray($ingredient["config"]);
            default:
                throw new RecipeException("Can't create ingredient from invalid type: " . $ingredient["type"]);
        }
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
            "primary_key" => $this->primaryKey ?? null,
            "ingredients" => array_map(function(Ingredient $ingredient) {
                return $ingredient->jsonSerialize();
            }, $this->ingredients),
        ];
    }
}