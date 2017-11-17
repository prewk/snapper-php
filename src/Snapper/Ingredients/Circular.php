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
}