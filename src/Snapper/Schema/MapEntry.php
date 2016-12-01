<?php
/**
 * MapEntry
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;
use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Collections\MapDependencyCollection;
use Prewk\Snapper\Snapshot\MapDependency;

/**
 * MapEntry
 */
abstract class MapEntry implements Arrayable, Objectable
{
    /**
     * @var MapEntryPath
     */
    protected $path;

    /**
     * MapEntry constructor
     *
     * @param MapEntryPath $path
     */
    public function __construct(
        MapEntryPath $path
    )
    {
        $this->path = $path;
    }

    /**
     * Get path
     *
     * @return MapEntryPath
     */
    public function getPath(): MapEntryPath
    {
        return $this->path;
    }

    /**
     * Get all dependencies within this map
     *
     * @param MapDependencyCollection $collection
     * @param array $map
     * @param array $dotMap
     * @return MapDependencyCollection
     */
    abstract public function getDependencies(MapDependencyCollection $collection, array $map, array $dotMap): MapDependencyCollection;
}