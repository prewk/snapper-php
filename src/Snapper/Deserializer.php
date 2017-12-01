<?php
/**
 * Deserializer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Closure;
use Prewk\Snapper;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;
use Prewk\Snapper\Exceptions\IntegrityException;

/**
 * Deserializer
 */
class Deserializer
{
    /**
     * @var Recipe[]
     */
    private $recipes;

    /**
     * @var Closure[]
     */
    private $inserters;

    /**
     * @var Closure[]
     */
    private $updaters;

    /**
     * @var DeserializationBookKeeper
     */
    private $bookKeeper;

    /**
     * Deserializer constructor
     *
     * @param DeserializationBookKeeper $bookKeeper
     * @param Recipe[] $recipes
     * @param Closure[] $inserters
     * @param Closure[] $updaters
     */
    public function __construct(DeserializationBookKeeper $bookKeeper, array $recipes, array $inserters, array $updaters)
    {
        $this->recipes = $recipes;
        $this->inserters = $inserters;
        $this->updaters = $updaters;
        $this->bookKeeper = $bookKeeper;
    }

    /**
     * Set/Override a recipe
     *
     * @param string $type
     * @param Recipe $recipe
     * @return Deserializer
     */
    public function setRecipe(string $type, Recipe $recipe): self
    {
        $this->recipes[$type] = $recipe;

        return $this;
    }

    /**
     * Set/Override an inserter
     *
     * @param string $type
     * @param Closure $inserter
     * @return Deserializer
     */
    public function setInserter(string $type, Closure $inserter): self
    {
        $this->inserters[$type] = $inserter;

        return $this;
    }

    /**
     * Set/Override an updater
     *
     * @param string $type
     * @param Closure $updater
     * @return Deserializer
     */
    public function setUpdater(string $type, Closure $updater): self
    {
        $this->updaters[$type] = $updater;

        return $this;
    }

    /**
     * Process a row
     *
     * @param Recipe $recipe
     * @param array $row
     * @return array
     */
    protected function processRow(Recipe $recipe, array $row): array
    {
        $uuid = $row[$recipe->getPrimaryKey()];
        $resolvedRow = [];

        // Go through the ingredients
        foreach ($recipe->getIngredients() as $field => $ingredient) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            // Get the deserialized field value using the ingredient and previously accumulated ids
            $val = $ingredient->deserialize($row[$field], $row, $this->bookKeeper);

            if ($val->isSome()) {
                $resolvedRow[$field] = $val->unwrap();
            }
        }

        return [
            "uuid" => $uuid,
            "resolvedRow" => $resolvedRow,
        ];
    }

    /**
     * Execute the given job
     *
     * @param array $item
     * @return Deserializer
     * @throws IntegrityException
     */
    protected function runOp(array $item): self
    {
        $op = $item["op"];
        $type = $item["type"];

        if (!array_key_exists($type, $this->recipes) || !array_key_exists($type, $this->inserters) || !array_key_exists($type, $this->updaters)) {
            throw new RecipeException("Unknown type: $type");
        }

        $recipe = $this->recipes[$type];

        $results = [];
        foreach ($item["rows"] as $row) {
            $results[] = $this->processRow($recipe, $row);
        }

        if ($op === Snapper::INSERT) {
            // Create the row using the inserter
            $resolvedRows = [];
            foreach ($results as $item) {
                $resolvedRow = $item["resolvedRow"];

                // Add the uuid key, it might be useful to the user when inserting
                $resolvedRow[$recipe->getPrimaryKey()] = $item["uuid"];

                $resolvedRows[] = $resolvedRow;
            }
            $ids = $this->inserters[$type]($resolvedRows);

            if (isset($ids)) {
                if (!is_array($ids)) {
                    throw new IntegrityException("Inserters must return nothing or an array of primary keys created");
                }

                if (count($ids) !== count($results)) {
                    throw new IntegrityException("Returned inserter primary key array had the wrong length");
                }

                foreach ($ids as $index => $id) {
                    // Next time this internal serialization uuid is requested - answer with the database id
                    $this->bookKeeper->wire($results[$index]["uuid"], $id);
                }
            }
        } else if ($op === Snapper::UPDATE) {
            // Update the row using the updater
            $resolvedRows = [];
            foreach ($results as $item) {
                $resolvedRow = $item["resolvedRow"];
                $resolvedRow[$recipe->getPrimaryKey()] = $this->bookKeeper->resolveId($type, $item["uuid"]);
                $resolvedRows[] = $resolvedRow;
            }
            $this->updaters[$type]($resolvedRows);
        }

        return $this;
    }

    /**
     * Iterate through the serialized sequence and execute the inserts and updates
     *
     * @param array $serialization
     */
    public function deserialize(array $serialization)
    {
        foreach ($serialization as $item) {
            $this->runOp($item);
        }
    }
}