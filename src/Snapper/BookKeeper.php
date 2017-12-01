<?php
/**
 * BookKeeper
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

/**
 * Describes a BookKeeper
 */
interface BookKeeper
{
    /**
     * Find or create an id associated with the given type and id
     *
     * @param $type
     * @param $id
     * @param bool $authoritative
     * @return mixed
     */
    public function resolveId($type, $id, bool $authoritative = false);

    /**
     * Reset the BookKeeper's internal state
     *
     * @return BookKeeper
     */
    public function reset(): BookKeeper;
}