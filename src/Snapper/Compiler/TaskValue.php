<?php
/**
 * TaskValue
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Describes a TaskValue
 */
interface TaskValue extends Arrayable
{
    /**
     * Settle TaskValue with the given alias-id lookup table
     * 
     * @param array $aliasLookup
     * @return mixed
     */
    public function getAsValue(array $aliasLookup);
    
    /**
     * Get dependencies
     *
     * @return int[]
     */
    public function getDependencies(): array;
}