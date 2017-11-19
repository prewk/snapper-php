<?php
/**
 * Json
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
use Prewk\Snapper\Ingredients\Json\JsonRecipe;

/**
 * Json
 */
class Json implements Ingredient
{
    /**
     * @var Closure
     */
    private $jsonRecipe;

    /**
     * Json constructor
     *
     * @param Closure $jsonRecipe
     */
    public function __construct(Closure $jsonRecipe)
    {
        $this->jsonRecipe = $jsonRecipe;
    }

    /**
     * Get the JsonRecipe for the ingredient
     *
     * @param $value
     * @return Option
     * @throws RecipeException
     */
    protected function getJsonRecipe($value): Option
    {
        if (!is_string($value)) {
            return new None;
        }

        $closure = $this->jsonRecipe;
        $jsonRecipe = $closure(new JsonRecipe);

        if (!($jsonRecipe instanceof JsonRecipe)) {
            throw new RecipeException("Json rule closure must return a JsonRecipe");
        }

        return new Some($jsonRecipe);
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
        return $this->getJsonRecipe($value)
            ->map(function(JsonRecipe $jsonRecipe) use ($value, $row) {
                return $jsonRecipe->getDeps($value, $row);
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
        return $this->getJsonRecipe($value)
            ->andThen(function(JsonRecipe $jsonRecipe) use ($value, $row, $books) {
                return $jsonRecipe->serialize($value, $row, $books);
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
        if (!is_string($value)) return new Some($value);

        foreach ($this->getDeps($value, $row, false) as list($type, $id)) {
            $replacement = $books->resolveId($type, $id);
            if (is_numeric($replacement)) {
                $value = str_replace("\"$id\"", $replacement, $value);
            }
            $value = str_replace($id, $replacement, $value);
        }

        return new Some($value);
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

    /**
     * Create an ingredient from an array, used for creating recipes from JSON
     *
     * @param array $config
     * @return Ingredient
     */
    public static function fromArray(array $config): Ingredient
    {
        return new static(JsonRecipe::fromArray($config["json_recipe"]));
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        $closure = $this->jsonRecipe;
        $jsonRecipe = $closure(new JsonRecipe);

        return [
            "type" => "JSON",
            "config" => [
                "json_recipe" => $jsonRecipe->jsonSerialize(),
            ],
        ];
    }
}