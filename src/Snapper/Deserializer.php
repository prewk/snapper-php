<?php
/**
 * Deserializer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Closure;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;

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
     * @param array $serialization
     */
    public function deserialize(array $serialization)
    {
        foreach ($serialization as $item) {
            $op = $item["op"];
            $type = $item["type"];
            $row = $item["row"];

            if (!array_key_exists($type, $this->recipes) || !array_key_exists($type, $this->inserters) || !array_key_exists($type, $this->updaters)) {
                throw new RecipeException("Unknown type: $type");
            }

            $recipe = $this->recipes[$type];

            $uuid = $row[$recipe->getPrimaryKey()];
            $resolvedRow = [];

            if ($op === "INSERT") {
                unset($row[$recipe->getPrimaryKey()]);

                foreach ($recipe->getIngredients() as $field => $ingredient) {
                    if (!array_key_exists($field, $row)) {
                        continue;
                    }

                    $val = $ingredient->deserialize($row[$field], $row, $this->bookKeeper);

                    if ($val->isSome()) {
                        $resolvedRow[$field] = $val->unwrap();
                    }
                }

                $id = $this->inserters[$type]($resolvedRow);
                $this->bookKeeper->wire($uuid, $id);
            } else if ($op === "UPDATE") {
                foreach ($recipe->getIngredients() as $field => $ingredient) {
                    if (!array_key_exists($field, $row)) {
                        continue;
                    }

                    $val = $ingredient->deserialize($row[$field], $row, $this->bookKeeper);

                    if ($val->isSome()) {
                        $resolvedRow[$field] = $val->unwrap();
                    }
                }

                $this->updaters[$type]($this->bookKeeper->resolveId($type, $uuid), $resolvedRow);
            }
        }
    }
}