<?php
/**
 * BookKeeper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Serializer;

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
     * @param string $type
     * @param $id
     * @return string
     */
    protected function pair(string $type, $id): string
    {
        return "$type/$id";
    }

    /**
     * Find or create an id associated with the given type and id
     *
     * @param string $type
     * @param $id
     * @return mixed
     */
    public function resolveId($type, $id)
    {
        $pair = $this->pair($type, $id);

        if (isset($this->idByPair[$pair])) {
            return $this->idByPair[$pair];
        }

        $uuid = Uuid::uuid4()->toString();

        $this->idByPair[$pair] = $uuid;
        $this->pairById[$uuid] = $pair;

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