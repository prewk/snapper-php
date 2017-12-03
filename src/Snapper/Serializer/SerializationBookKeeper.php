<?php
/**
 * BookKeeper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Serializer;

use Prewk\Option;
use Prewk\Snapper\BookKeeper;
use Ramsey\Uuid\Uuid;

/**
 * BookKeeper
 */
class SerializationBookKeeper implements BookKeeper
{
    /**
     * @var array
     */
    private $idByPair = [];

    /**
     * @var array
     */
    private $pairById = [];

    /**
     * @var array
     */
    private $casts = [];

    /**
     * Cast id to its original
     *
     * @param string $uuid
     * @param string $id
     * @return int|string
     */
    protected function cast(string $uuid, string $id)
    {
        return isset($this->casts[$uuid]) && $this->casts[$uuid] === "int"
            ? intval($id)
            : $id;
    }

    /**
     * Turn the Map<Type/Id, Uuid> into a Map<Type, Map<Uuid, Id>>
     *
     * @return array
     */
    public function getIdDict(): array
    {
        $deep = [];

        foreach ($this->idByPair as $pairStr => $uuid) {
            list($type, $id) = explode("/", $pairStr);

            if (!isset($deep[$type])) $deep[$type] = [];

            $deep[$type][$uuid] = $this->cast($uuid, $id);
        }

        return $deep;
    }

    /**
     * @param string $type
     * @param $id
     * @return string
     */
    protected function pair(string $type, $id): string
    {
        return "$type/$id";
    }

    /**
     * Get pair by uuid
     *
     * @param string $uuid
     * @return Option Option<[type, id]>
     */
    public function getPairByUuid(string $uuid): Option
    {
        return Option\From::key($this->pairById, $uuid)
            ->map(function(string $pairStr) use ($uuid) {
                list($type, $id) = explode("/", $pairStr);

                return [$type, $this->cast($uuid, $id)];
            });
    }

    /**
     * Find or create an id associated with the given type and id
     *
     * @param string $type
     * @param $id
     * @param bool $authoritative
     * @return mixed
     */
    public function resolveId($type, $id, bool $authoritative = false)
    {
        $pair = $this->pair($type, $id);

        if (isset($this->idByPair[$pair])) {
            $uuid = $this->idByPair[$pair];

            if ($authoritative) {
                $this->casts[$uuid] = is_int($id) ? "int" : "string";
            }

            return $uuid;
        }

        $uuid = Uuid::uuid4()->toString();

        $this->idByPair[$pair] = $uuid;
        $this->pairById[$uuid] = $pair;
        $this->casts[$uuid] = is_int($id) ? "int" : "string";

        return $uuid;
    }

    /**
     * Reset the BookKeeper's internal state
     *
     * @return BookKeeper
     */
    public function reset(): BookKeeper
    {
        $this->idByPair = [];
        $this->pairById = [];

        return $this;
    }
}