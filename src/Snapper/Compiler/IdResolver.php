<?php
/**
 * IdResolver
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Closure;
use Prewk\Snapper\Errors\CompilerException;

class IdResolver
{
    /**
     * @var array[]
     */
    private $listeners = [];

    /**
     * @var int[]
     */
    private $resolved = [];

    /**
     * Factory
     * 
     * @return IdResolver
     */
    public function make(): IdResolver
    {
        return new static;
    }
    
    /**
     * Listen and run handler when dependencies have been resolved
     *
     * @param int $id
     * @param int[] $dependencies
     * @param Closure $handler
     * @return Closure
     * @throws CompilerException
     */
    public function listen(int $id, array $dependencies, Closure $handler): Closure
    {
        if ($this->hasListener($id)) {
            throw new CompilerException("Tried to register two listeners for the same id: $id");
        } elseif (count($this->findCircularDeps($id, $dependencies))) {
            throw new CompilerException("Making $id rely on " . implode(", ", $dependencies) . " would cause a circular dependency");
        }

        $this->listeners[$id] = [$dependencies, $handler];

        $this->resolve();

        return function() use ($id) {
            $this->unregister($id);
        };
    }

    /**
     * Has listener?
     *
     * @param int $id
     * @return bool
     */
    public function hasListener(int $id): bool
    {
        return array_key_exists($id, $this->listeners) && !is_null($this->listeners[$id]);
    }

    /**
     * Find circular dependencies
     * 
     * @param int $id
     * @param int[] $deps
     * @return array
     */
    public function findCircularDeps(int $id, array $deps): array
    {
        $circularDeps = [];

        foreach ($deps as $dep) {
            if (
                $this->hasListener($dep) &&
                in_array($id, $this->listeners[$dep][0])
            ) {
                $circularDeps[] = $dep;
            }
        }

        return $circularDeps;
    }

    /**
     * Unregister an id
     *
     * @param int $id
     * @throws CompilerException
     */
    public function unregister(int $id)
    {
        if (!array_key_exists($id, $this->listeners)) {
            throw new CompilerException("Can't unregister listener because id didn't exist for id $id");
        } elseif (is_null($this->listeners[$id])) {
            throw new CompilerException("Can only unregister a listener for id $id once");
        }

        $this->listeners[$id] = null;
    }

    /**
     * Report an id as resolved
     *
     * @param int $id
     * @throws CompilerException
     */
    public function report(int $id)
    {
        if (in_array($id, $this->resolved)) {
            throw new CompilerException("Can't report id $id, it's already reported");
        }

        $this->resolved[] = $id;

        $this->resolve();
    }

    /**
     * Fire and forget all handlers with resolved dependencies
     */
    protected function resolve()
    {
        $ids = [];

        foreach ($this->listeners as $id => $tuple) {
            if (is_null($tuple)) continue;

            list($deps) = $tuple;

            $resolvable = array_reduce($deps, function($resolvable, $dep) {
                return $resolvable && in_array($dep, $this->resolved);
            }, true);

            if ($resolvable) {
                $ids[] = $id;
            }
        }

        foreach ($ids as $id) {
            // Due to recursion some earlier handler in this foreach might nullify stuff before this iteration starts 
            if (is_null($this->listeners[$id])) {
                continue;
            }

            $handler = $this->listeners[$id][1];
            $this->listeners[$id] = null;

            $handler();
        }
    }
}
