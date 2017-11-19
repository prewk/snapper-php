<?php
/**
 * Ingredient
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients;

use JsonSerializable;
use Prewk\Option;
use Prewk\Snapper\BookKeeper;

/**
 * Describes an Ingredient
 */
interface Ingredient extends JsonSerializable
{
    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @param array $row
     * @param bool $circular
     * @return array Array of [type, id] tuples
     */
    public function getDeps($value, array $row, bool $circular = false): array;

    /**
     * Let the ingredient determine the value of the field to store in a serialization
     *
     * @param $value
     * @param array $row
     * @param BookKeeper $books
     * @param bool $circular
     * @return Option
     */
    public function serialize($value, array $row, BookKeeper $books, bool $circular = false): Option;

    /**
     * Let the ingredient determine the value of the field to insert into the database when deserializing
     *
     * @param $value
     * @param array $row
     * @param BookKeeper $books
     * @return Option
     */
    public function deserialize($value, array $row, BookKeeper $books): Option;

    /**
     * Should return an array with fields required to be able to UPDATE a row
     *
     * @return string[]
     */
    public function getRequiredExtraFields(): array;

    /**
     * Create an ingredient from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return Ingredient
     */
    public static function fromArray(array $config): Ingredient;
}