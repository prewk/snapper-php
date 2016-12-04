<?php
/**
 * MapDependencyCollection
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Collections;

use Closure;
use Illuminate\Support\Arr;
use Prewk\Snapper\Errors\ForbiddenOperationException;
use Prewk\Snapper\Schema\MapDependency;

/**
 * MapDependencyCollection
 */
class MapDependencyCollection
{
    /**
     * @var MapDependency[]
     */
    private $dependencies;

    /**
     * MapDependencyCollection constructor
     *
     * @param MapDependency[] $dependencies
     */
    public function __construct(
        array $dependencies = []
    )
    {
        $this->dependencies = $dependencies;
    }

    /**
     * Factory
     *
     * @param array $dependencies
     * @return MapDependencyCollection
     */
    public function make(array $dependencies = []): MapDependencyCollection
    {
        return new static($dependencies);
    }

    /**
     * Get count
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->dependencies);
    }

    /**
     * Get all dependencies
     *
     * @return MapDependency[]
     */
    public function all(): array
    {
        return $this->dependencies;
    }

    /**
     * Push a dependency unto the collection
     *
     * @param mixed $id
     * @param string $relation
     * @param string $path
     * @param bool $isInString
     * @param int $startPos
     * @param int $stopPos
     * @return MapDependencyCollection
     */
    public function push(
        $id,
        string $relation,
        string $path,
        bool $isInString = false,
        int $startPos = 0,
        int $stopPos = 0
    ): MapDependencyCollection
    {
        $this->dependencies[] = new MapDependency(
            $id,
            $relation,
            $path,
            $isInString,
            $startPos,
            $stopPos
        );

        return $this;
    }

    /**
     * Transform the given map's relations with the given transformer
     *
     * @param mixed $map
     * @param Closure $transformer Gets the relation entity's name and id as argument, expects the transformed id back:
     *                             (string $relation, mixed $id) => mixed
     * @return array
     * @throws ForbiddenOperationException
     */
    public function transform($map, Closure $transformer): array
    {
        $depCount = count($this->dependencies);

        for ($i = 0; $i < $depCount; $i++) {
            $dependency = $this->dependencies[$i];
            $id = $dependency->getId();
            $path = $dependency->getPath();
            $replacement = $transformer($dependency->getRelation(), $id);

            if ($dependency->isInString()) {
                $target = Arr::get($map, $path);
                $startPos = $dependency->getStartPos();
                $stopPos = $dependency->getStopPos();

                if (!is_string($target)) {
                    throw new ForbiddenOperationException("Tried to transform a map's string at path '$path', found non-string");
                }

                // foobar<123>baz -> foobar<__id__456>baz
                $target = substr($target, 0, $startPos) . $replacement . substr($target, $stopPos);
                Arr::set($map, $path, $target);

                // Adjust offsets on remaining dependencies if needed
                $diff = strlen($replacement) - strlen((string)$id);

                // If offset needs to be adjusted and there are more dependencies after this one
                if ($diff !== 0 && $depCount > $i + 1) {
                    // Iterate through all in-string dependencies after this one and adjust their offset
                    for ($r = $i + 1; $r < $depCount; $r++) {
                        if ($this->dependencies[$r]->isInString() && $this->dependencies[$r]->getPath() === $path) {
                            $this->dependencies[$r]->adjustOffset($diff, $startPos);
                        }
                    }
                }
            } else {
                Arr::set($map, $dependency->getPath(), $replacement);
            }
        }

        return $map;
    }

    /**
     * Mapper
     *
     * @param Closure $mapper
     * @return array
     */
    public function map(Closure $mapper): array
    {
        return array_map($mapper, $this->dependencies);
    }

    /**
     * Foreach
     *
     * @param Closure $mapper
     */
    public function each(Closure $mapper): void
    {
        foreach ($this->dependencies as $dependency) {
            $mapper($dependency);
        }
    }
}