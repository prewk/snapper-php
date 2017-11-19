<?php
/**
 * MatchMapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients\Match;

use JsonSerializable;
use Prewk\Option;
use Prewk\Option\{None, Some};
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Ingredients\Ingredient;
use Prewk\Snapper\Recipe;

/**
 * MatchMapper
 */
class MatchMapper implements JsonSerializable
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

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $on = [];
        foreach ($this->on as $value => $ingredient) {
            $on[$value] = $ingredient->jsonSerialize();
        }

        $patterns = [];
        foreach ($this->patterns as $pattern => $ingredient) {
            $patterns[$pattern] = $ingredient->jsonSerialize();
        }

        $default = isset($this->fallback) ? $this->fallback->jsonSerialize() : null;

        return [
            "field" => $this->field,
            "on" => $on,
            "patterns" => $patterns,
            "default" => $default,
        ];
    }

    /**
     * Create a MatchMapper from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return MatchMapper
     */
    public static function fromArray(array $config): MatchMapper
    {
        $mapper = new static($config["field"]);

        foreach ($config["on"] as $value => $ingredient) {
            $mapper->on($value, Recipe::ingredientFromArray($ingredient));
        }

        foreach ($config["patterns"] as $pattern => $ingredient) {
            $mapper->pattern($pattern, Recipe::ingredientFromArray($ingredient));
        }

        if (isset($config["default"])) {
            $mapper->default(Recipe::ingredientFromArray($config["default"]));
        }

        return $mapper;
    }
}