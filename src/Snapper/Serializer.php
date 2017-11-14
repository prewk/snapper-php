<?php
/**
 * Serializer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Prewk\Snapper\Exceptions\RecipeException;
use Prewk\Snapper\Ingredients\Circular;
use Prewk\Snapper\Serializer\SerializationBookKeeper;

/**
 * Serializer
 */
class Serializer
{
    /**
     * @var Recipe[]
     */
    private $recipes = [];

    /**
     * @var Sorter
     */
    private $sorter;

    /**
     * @var Sorter
     */
    private $circularSorter;

    /**
     * @var SerializationBookKeeper
     */
    private $bookKeeper;

    /**
     * @var array
     */
    private $rows = [];

    /**
     * @var array
     */
    private $circularRows = [];

    /**
     * Serializer constructor
     *
     * @param Sorter $sorter
     * @param SerializationBookKeeper $bookKeeper
     * @param Recipe[] $recipes
     */
    public function __construct(Sorter $sorter, SerializationBookKeeper $bookKeeper, array $recipes)
    {
        $this->sorter = $sorter->make();
        $this->circularSorter = $sorter->make();
        $this->bookKeeper = $bookKeeper;
        $this->recipes = $recipes;
    }

    /**
     * Add a row
     *
     * @param string $type
     * @param array $row
     * @return Serializer
     * @throws RecipeException
     */
    public function add(string $type, array $row): self
    {
        if (!array_key_exists($type, $this->recipes)) {
            throw new RecipeException("Unknown type: $type");
        }

        $recipe = $this->recipes[$type];

        $id = $row[$recipe->getPrimaryKey()];
        $uuid = $this->bookKeeper->resolveId($type, $id);
        $deps = [];
        $circularDeps = [];
        $resolvedRow = [
            $recipe->getPrimaryKey() => $uuid,
        ];
        $resolvedCircularRow = [];
        $requiredCircularFields = [];

        foreach ($recipe->getIngredients() as $field => $ingredient) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $deps = array_merge($deps, array_map(function(array $pair) {
                return $this->bookKeeper->resolveId(...$pair);
            }, $ingredient->getDeps($row[$field], $row, false)));

            $val = $ingredient->serialize($row[$field], $row, $this->bookKeeper, false);
            if ($val->isSome()) {
                $resolvedRow[$field] = $val->unwrap();
            }

            if ($ingredient instanceof Circular) {
                $circularDeps = array_merge($circularDeps, array_map(function(array $pair) use ($ingredient) {
                    return $this->bookKeeper->resolveId(...$pair);
                }, $ingredient->getDeps($row[$field], $row, true)));

                $val = $ingredient->serialize($row[$field], $row, $this->bookKeeper, true);
                if ($val->isSome()) {
                    $resolvedCircularRow[$field] = $val->unwrap();
                }

                $requiredCircularFields = array_merge($requiredCircularFields, $ingredient->getRequiredExtraFields());
            }
        }

        if (!empty($resolvedCircularRow)) {
            // Add id field
            $resolvedCircularRow[$recipe->getPrimaryKey()] = $uuid;
            // Add required extra fields
            foreach ($requiredCircularFields as $reqField) {
                if (!array_key_exists($reqField, $resolvedCircularRow) && array_key_exists($reqField, $resolvedRow)) {
                    $resolvedCircularRow[$reqField] = $resolvedRow[$reqField];
                }
            }

            $this->circularRows[$uuid] = [
                "deps" => $circularDeps,
                "row" => $resolvedCircularRow,
                "type" => $type,
            ];
        }

        $this->rows[$uuid] = [
            "row" => $resolvedRow,
            "type" => $type,
        ];

        $this->sorter->add($uuid, $deps);
        $this->circularSorter->add($uuid);

        return $this;
    }

    /**
     * Turn the added rows into a sequence of operations
     *
     * @return array
     */
    public function compile(): array
    {
        $order = $this->sorter->sort();

        foreach ($this->circularRows as $uuid => $item) {
            $this->circularSorter->add($uuid, $item["deps"]);
        }

        $circularOrder = array_values(array_filter($this->circularSorter->sort(), function(string $uuid) {
            return array_key_exists($uuid, $this->circularRows);
        }));

        return array_merge(
            array_map(function(string $uuid) {
                $item = $this->rows[$uuid];

                return [
                    "op" => "INSERT",
                    "type" => $item["type"],
                    "row" => $item["row"],
                ];
            }, $order),
            array_map(function(string $uuid) {
                $item = $this->circularRows[$uuid];

                return [
                    "op" => "UPDATE",
                    "type" => $item["type"],
                    "row" => $item["row"],
                ];
            }, $circularOrder)
        );
    }
}