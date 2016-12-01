<?php
/**
 * Sorter
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Prewk\Snapper\Snapshot;

/**
 * Describes a Sorter
 */
interface Sorter
{
    /**
     * Sort
     *
     * @param IdMaker $idMaker
     * @param Snapshot $entities
     * @param CreateTask[] $tasks
     * @return Snapshot
     */
    public function sort(IdMaker $idMaker, Snapshot $entities, array $tasks): Snapshot;
}