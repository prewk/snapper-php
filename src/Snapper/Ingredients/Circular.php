<?php
/**
 * Circular
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients;

use Prewk\Option;
use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Recipe;

/**
 * Circular
 */
class Circular implements Ingredient
{
    /**
     * @var Ingredient
     */
    private $ingredient;

    /**
     * @var Ingredient
     */
    private $fallback;

    /**
     * Circular constructor
     *
     * @param Ingredient $ingredient
     * @param Ingredient $fallback
     */
    public function __construct(Ingredient $ingredient, Ingredient $fallback)
    {
        $this->ingredient = $ingredient;
        $this->fallback = $fallback;
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
        return $circular
            ? $this->ingredient->getDeps($value, $row, false)
            : $this->fallback->getDeps($value, $row, false);
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
        return $circular
            ? $this->ingredient->serialize($value, $row, $books, false)
            : $this->fallback->serialize($value, $row, $books, false);
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
        return $this->ingredient->deserialize($value, $row, $books);
    }

    /**
     * Should return an array with fields required to be able to UPDATE a row
     *
     * @return string[]
     */
    public function getRequiredExtraFields(): array
    {
        return $this->ingredient->getRequiredExtraFields();
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
            "type" => "CIRCULAR",
            "config" => [
                "ingredient" => $this->ingredient->jsonSerialize(),
                "fallback" => $this->fallback->jsonSerialize(),
            ],
        ];
    }

    /**
     * Create an ingredient from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return Ingredient
     */
    public static function fromArray(array $config): Ingredient
    {
        return new static(
            Recipe::ingredientFromArray($config["ingredient"]),
            Recipe::ingredientFromArray($config["fallback"])
        );
    }
}