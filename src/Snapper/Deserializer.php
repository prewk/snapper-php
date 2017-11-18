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
use Prewk\Snapper\Deserializer\DeserializerEvent;
use Prewk\Snapper\Deserializer\Events\OnInsert;
use Prewk\Snapper\Deserializer\Events\OnUpdate;
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
     * @var DeserializerEvent[]
     */
    private $events = [];

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
        $row = $item["row"];

        if (!array_key_exists($type, $this->recipes) || !array_key_exists($type, $this->inserters) || !array_key_exists($type, $this->updaters)) {
            throw new RecipeException("Unknown type: $type");
        }

        $recipe = $this->recipes[$type];

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

        // Fire events
        foreach ($this->events as $event) {
            if ($op === Snapper::INSERT && $event instanceof OnInsert) {
                $event->call($type, $resolvedRow);
            } else if ($op === Snapper::UPDATE && $event instanceof OnUpdate) {
                $event->call($type, $resolvedRow);
            }
        }

        if ($op === Snapper::INSERT) {
            // Remove the primary key from the INSERT row before sending to the inserter
            unset($row[$recipe->getPrimaryKey()]);

            // Create the row using the inserter
            $id = $this->inserters[$type]($resolvedRow);

            if (!isset($id)) {
                throw new IntegrityException("Inserters must return the primary key created");
            }

            // Next time this internal serialization uuid is requested - answer with the database id
            $this->bookKeeper->wire($uuid, $id);
        } else if ($op === Snapper::UPDATE) {
            // Update the row using the updater
            $this->updaters[$type]($this->bookKeeper->resolveId($type, $uuid), $resolvedRow);
        }

        return $this;
    }

    /**
     * Register an event
     *
     * @param DeserializerEvent $event
     * @return Closure Unregistering function
     */
    public function on(DeserializerEvent $event): Closure
    {
        $index = count($this->events);
        $this->events[] = $event;

        return function() use ($index) {
            unset($this->events[$index]);
        };
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