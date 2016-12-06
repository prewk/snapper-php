<?php
/**
 * SortByMostRequired
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler\Sorters;

use Prewk\Snapper\Compiler\CreateTask;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\Sorter;
use Prewk\Snapper\Schema\Entity;
use Prewk\Snapper\Snapshot;
use Prewk\Snapper\Snapshot\EntityRow;

/**
 * SortByMostRequired
 */
class SortByMostRequired implements Sorter
{
    /**
     * Sort
     *
     * @param IdMaker $idMaker
     * @param Snapshot $entities
     * @param CreateTask[] $tasks
     * @return Snapshot
     */
    public function sort(IdMaker $idMaker, Snapshot $entities, array $tasks): Snapshot
    {
        $allDeps = [];
        foreach ($tasks as $task) {
            $allDeps = array_merge($allDeps, array_map(function(int $id) use ($idMaker) {
                // Translate from task id to real id
                return $idMaker->getEntity($id)[1];
            }, $task->getDependencies()));
        }

        $count = array_count_values($allDeps);

        $withDeps = $entities->filter(function(EntityRow $entityRow) use ($count) { return array_key_exists($entityRow->getKey(), $count); });
        $withoutDeps = $entities->filter(function(EntityRow $entityRow) use ($count) { return !array_key_exists($entityRow->getKey(), $count); });

        return $withDeps->sort(function(EntityRow $a, EntityRow $b) use ($count, $idMaker, &$o) {
            $aId = $a->getKey();
            $bId = $b->getKey();

            $aCount = array_key_exists($aId, $count) ? $count[$aId] : 0;
            $bCount = array_key_exists($bId, $count) ? $count[$bId] : 0;

            if ($aCount === $bCount) return 0;

            return $aCount < $bCount ? -1 : 1;
        })->merge($withoutDeps);
    }
}