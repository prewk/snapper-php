<?php
/**
 * Snapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk;
use Prewk\Snapper\Deserializer;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;
use Prewk\Snapper\Recipe;
use Prewk\Snapper\Serializer;
use Prewk\Snapper\Serializer\SerializationBookKeeper;
use Prewk\Snapper\Sorter;

/**
 * Snapper
 */
class Snapper
{
    const INSERT = "INSERT";
    const UPDATE = "UPDATE";
    /**
     * @var Sorter
     */
    private $sorter;
    /**
     * @var SerializationBookKeeper
     */
    private $serializationBookKeeper;
    /**
     * @var DeserializationBookKeeper
     */
    private $deserializationBookKeeper;

    /**
     * Snapper constructor
     *
     * @param Sorter $sorter
     * @param SerializationBookKeeper $serializationBookKeeper
     * @param DeserializationBookKeeper $deserializationBookKeeper
     */
    public function __construct(
        Sorter $sorter,
        SerializationBookKeeper $serializationBookKeeper,
        DeserializationBookKeeper $deserializationBookKeeper
    ) {
        $this->sorter = $sorter;
        $this->serializationBookKeeper = $serializationBookKeeper;
        $this->deserializationBookKeeper = $deserializationBookKeeper;
    }

    /**
     * Make a serializer
     *
     * @param array $recipes
     * @return Serializer
     */
    public function makeSerializer(array $recipes): Serializer
    {
        return new Serializer($this->sorter, $this->serializationBookKeeper, $recipes);
    }

    /**
     * Make a deserializer
     *
     * @param array $recipes
     * @param array $inserters
     * @param array $updaters
     * @return Deserializer
     */
    public function makeDeserializer(array $recipes, array $inserters, array $updaters): Deserializer
    {
        return new Deserializer($this->deserializationBookKeeper, $recipes, $inserters, $updaters);
    }

    /**
     * Analyze the row using the recipe
     *
     * @param Recipe $recipe
     * @param array $row
     * @return array
     */
    public function analyze(Recipe $recipe, array $row): array
    {
        $deps = [];
        $missing = [];
        $primaryKey = $recipe->getPrimaryKey();

        foreach ($recipe->getIngredients() as $field => $ingredient) {
            if (!array_key_exists($field, $row)) {
                $missing[] = $field;
                continue;
            }

            $deps = array_merge($deps, $ingredient->getDeps($row[$field], $row, false));
        }

        return [
            "deps" =>  $deps,
            "missingFields" => $missing,
            "primary" => array_key_exists($primaryKey, $row) ? $row[$primaryKey] : null,
        ];
    }
}