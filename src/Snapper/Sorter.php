<?php
/**
 * Sorter
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use MJS\TopSort\Implementations\StringSort;

/**
 * Sorter
 */
class Sorter extends StringSort
{
    /**
     * Make a new empty Sorter
     *
     * @return Sorter
     */
    public function make(): Sorter
    {
        return new static([], $this->throwCircularDependency);
    }
}