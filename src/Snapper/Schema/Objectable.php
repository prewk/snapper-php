<?php
/**
 * Objectable
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;
use stdClass;

/**
 * Describes a Objectable
 */
interface Objectable
{
    /**
     * Convert to stdClass
     * 
     * @return stdClass
     */
    public function toObject(): stdClass;
}