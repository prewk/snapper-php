<?php
/**
 * Serializer
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Closure;
use Prewk\Snapper;
use Prewk\Snapper\Exceptions\IntegrityException;
use Prewk\Snapper\Exceptions\RecipeException;
use Prewk\Snapper\Ingredients\Circular;
use Prewk\Snapper\Serializer\Events\OnInsert;
use Prewk\Snapper\Serializer\Events\OnUpdate;
use Prewk\Snapper\Serializer\SerializationBookKeeper;
use Prewk\Snapper\Serializer\SerializerEvent;

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
     * @var SerializerEvent[]
     */
    private $events = [];

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
     * Set/Override a recipe
     *
     * @param string $type
     * @param Recipe $recipe
     * @return Serializer
     */
    public function setRecipe(string $type, Recipe $recipe): self
    {
        $this->recipes[$type] = $recipe;

        return $this;
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

        // Go through all ingredients in the recipe
        foreach ($recipe->getIngredients() as $field => $ingredient) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            // Convert dependencies into internal ids
            $deps = array_merge($deps, array_map(function(array $pair) {
                return $this->bookKeeper->resolveId(...$pair);
            }, $ingredient->getDeps($row[$field], $row, false)));

            // Resolve the field's value
            $val = $ingredient->serialize($row[$field], $row, $this->bookKeeper, false);
            if ($val->isSome()) {
                $resolvedRow[$field] = $val->unwrap();
            }

            // Handle possible circular refs - ends up as UPDATE ops
            if ($ingredient instanceof Circular) {
                // Convert circular dependencies into internal ids
                $circularDeps = array_merge($circularDeps, array_map(function(array $pair) use ($ingredient) {
                    return $this->bookKeeper->resolveId(...$pair);
                }, $ingredient->getDeps($row[$field], $row, true)));

                // Resolve the circular field's value
                $val = $ingredient->serialize($row[$field], $row, $this->bookKeeper, true);
                if ($val->isSome()) {
                    $resolvedCircularRow[$field] = $val->unwrap();
                }

                // Some ingredients require extra fields to be present to be able to function properly
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

            // Call OnUpdate events
            foreach ($this->events as $event) {
                if ($event instanceof OnUpdate) {
                    $overwrite = $event->call($type, $resolvedCircularRow);
                    if (is_array($overwrite)) {
                        $resolvedCircularRow = $overwrite;
                    }
                }
            }

            $this->circularRows[$uuid] = [
                "deps" => $circularDeps,
                "row" => $resolvedCircularRow,
                "type" => $type,
            ];
        }

        // Call OnInsert events
        foreach ($this->events as $event) {
            if ($event instanceof OnInsert) {
                $overwrite = $event->call($type, $resolvedRow);
                if (is_array($overwrite)) {
                    $resolvedRow = $overwrite;
                }
            }
        }

        $this->rows[$uuid] = [
            "deps" => $deps,
            "row" => $resolvedRow,
            "type" => $type,
            "uuid" => $uuid,
        ];

        $this->sorter->add($uuid, $deps);
        $this->circularSorter->add($uuid);

        return $this;
    }

    /**
     * Register an event
     *
     * @param SerializerEvent $event
     * @return Closure
     */
    public function on(SerializerEvent $event): Closure
    {
        $index = count($this->events);
        $this->events[] = $event;

        return function() use ($index) {
            unset($this->events[$index]);
        };
    }

    /**
     * Convert an ordered dependency tree into UPDATE ops
     *
     * @param array $ordered
     * @return array
     */
    protected function toUpdateOps(array $ordered): array
    {
        $ops = [];
        $batch = [];
        $lastType = "";
        $lastFieldHash = "";

        foreach ($ordered as $uuid) {
            $item = $this->circularRows[$uuid];
            $type = $item["type"];

            // Create a hash of the order and names of fields
            $fieldHash = implode("/", array_keys($item));

            if ($type !== $lastType || $fieldHash !== $lastFieldHash) {
                // Start a new batch
                if (!empty($batch)) $ops[] = $this->itemsToBatchedOp(Snapper::UPDATE, $lastType, $batch);
                $batch = [];
            }

            // Add item to batch
            $batch[] = $item;
            $lastType = $type;
            $lastFieldHash = $fieldHash;
        }

        // Empty the last of the batch into ops
        if (!empty($batch)) $ops[] = $this->itemsToBatchedOp(Snapper::UPDATE, $lastType, $batch);

        return $ops;
    }

    /**
     * Convert an array of items into one batched INSERT op
     *
     * @param string $op
     * @param string $type
     * @param array $items
     * @return array
     */
    protected function itemsToBatchedOp(string $op, string $type, array $items): array
    {
        return [
            "op" => $op,
            "type" => $type,
            "rows" => array_map(function(array $item) {
                return $item["row"];
            }, $items),
        ];
    }

    /**
     * Convert an ordered dependency tree into batched INSERT ops
     *
     * @param array $ordered
     * @return array
     */
    protected function toInsertOps(array $ordered): array
    {
        $ops = [];
        $batch = [];
        $lastType = "";
        $lastFieldHash = "";

        foreach ($ordered as $uuid) {
            $item = $this->rows[$uuid];
            $deps = $item["deps"];
            $type = $item["type"];

            // Create a hash of the order and names of fields
            $fieldHash = implode("/", array_keys($item));

            if ($type === $lastType && $fieldHash === $lastFieldHash) {
                // Gather all uuids so far in the batch
                $uuidsInBatch = array_map(function (array $item) {
                    return $item["uuid"];
                }, $batch);

                // Check for intradependencies
                foreach ($deps as $dep) {
                    $depItem = $this->rows[$dep];
                    if ($depItem["type"] === $type && in_array($dep, $uuidsInBatch)) {
                        // Nope, this item has intradependencies - New batch
                        if (!empty($batch)) $ops[] = $this->itemsToBatchedOp(Snapper::INSERT, $type, $batch);
                        $batch = [];
                        break;
                    }
                }
            } else {
                // New type - Start a new batch
                if (!empty($batch)) $ops[] = $this->itemsToBatchedOp(Snapper::INSERT, $lastType, $batch);
                $batch = [];
            }

            // Add item to batch
            $batch[] = $item;
            $lastType = $type;
            $lastFieldHash = $fieldHash;
        }

        // Empty the last of the batch into ops
        if (!empty($batch)) $ops[] = $this->itemsToBatchedOp(Snapper::INSERT, $lastType, $batch);

        return $ops;
    }

    /**
     * Turn the added rows into a sequence of operations
     *
     * @return array
     * @throws IntegrityException
     */
    public function compile(): array
    {
        $order = $this->sorter->sortWithBookkeeping($this->bookKeeper)->unwrap();

        foreach ($this->circularRows as $uuid => $item) {
            $this->circularSorter->add($uuid, $item["deps"]);
        }

        $circularOrder = array_values(
            array_filter(
                $this->circularSorter->sortWithBookkeeping(
                    $this->bookKeeper
                )->unwrap(),
                function(string $uuid) {
                    return array_key_exists($uuid, $this->circularRows);
                })
        );

        return array_merge($this->toInsertOps($order), $this->toUpdateOps($circularOrder));
    }
}