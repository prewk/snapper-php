<?php
/**
 * DeserializationBookKeeper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Deserializer;

use Prewk\Snapper\BookKeeper;
use Prewk\Snapper\Exceptions\IntegrityException;

/**
 * DeserializationBookKeeper
 */
class DeserializationBookKeeper implements BookKeeper
{
    /**
     * @var array
     */
    private $ids = [];

    /**
     * Find or create an id associated with the given type and id
     *
     * @param $type
     * @param $id
     * @param bool $authoritative
     * @return mixed
     * @throws IntegrityException
     */
    public function resolveId($type, $id, bool $authoritative = false)
    {
        if (!isset($this->ids[$id])) {
            throw new IntegrityException("An id ($id) needed to be resolved but wasn't known");
        }

        return $this->ids[$id];
    }

    /**
     * Tell the BookKeeper to return $to when $from is requested
     *
     * @param $from
     * @param $to
     */
    public function wire($from, $to)
    {
        $this->ids[$from] = $to;
    }

    /**
     * Reset the BookKeeper's internal state
     *
     * @return BookKeeper
     */
    public function reset(): BookKeeper
    {
        $this->ids = [];

        return $this;
    }
}