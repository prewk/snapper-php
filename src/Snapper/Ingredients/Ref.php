<?php
/**
 * Ref
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Ingredients;

use Prewk\Option;
use Prewk\Option\{None, Some};
use Prewk\Snapper\BookKeeper;

/**
 * Ref
 */
class Ref implements Ingredient
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $optionalValues = [];

    /**
     * Ref constructor
     *
     * @param string $type
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Specify which values should be treated as optional
     *
     * @param array ...$optionalValues
     * @return Ref
     */
    public function optional(...$optionalValues): self
    {
        $this->optionalValues = $optionalValues;

        return $this;
    }

    /**
     * Get all dependencies of this ingredient
     *
     * @param mixed $value
     * @param array $row
     * @return array Array of [type, id] tuples
     */
    public function getDeps($value, array $row, bool $circular = false): array
    {
        foreach ($this->optionalValues as $opt) {
            if ($opt === $value) return [];
        }

        return [[$this->type, $value]];
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
        foreach ($this->optionalValues as $opt) {
            if ($opt === $value) return new Some($value);
        }

        return new Some($books->resolveId($this->type, $value));
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
        return $this->serialize($value, $row, $books);
    }

    /**
     * Should return an array with fields required to be able to UPDATE a row
     *
     * @return string[]
     */
    public function getRequiredExtraFields(): array
    {
        return [];
    }
}