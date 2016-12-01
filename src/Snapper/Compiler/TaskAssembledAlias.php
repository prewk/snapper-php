<?php
/**
 * TaskAssembledAlias
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Prewk\Snapper\Errors\ForbiddenOperationException;

/**
 * TaskAssembledAlias
 */
class TaskAssembledAlias implements TaskValue
{
    /**
     * @var int[]|string[]
     */
    private $parts;

    /**
     * TaskAssembledAlias constructor
     *
     * @param string[]|int[] $parts
     */
    public function __construct(
        array $parts
    )
    {
        $this->parts = $parts;
    }

    /**
     * Get parts
     *
     * @return int[]|string[]
     */
    public function getParts()
    {
        return $this->parts;
    }

    /**
     * Get dependencies
     *
     * @return int[]
     */
    public function getDependencies(): array
    {
        return array_filter($this->parts, function($part) {
            return is_int($part);
        });
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "TASK_ASSEMBLED_ALIAS",
            "parts" => $this->parts,
        ];
    }

    /**
     * Settle TaskValue with the given alias-id lookup table
     *
     * @param array $aliasLookup
     * @return mixed
     */
    public function getAsValue(array $aliasLookup)
    {
        return implode("", array_map(function($part) use ($aliasLookup) {
            if (is_int($part)) {
                if (!array_key_exists($part, $aliasLookup)) {
                    throw new ForbiddenOperationException("Needed missing value for alias {$part}");
                }

                // TODO: Cast type should be part of the schema
                return json_encode($aliasLookup[$part]);
            } else {
                return $part;
            }
        }, $this->parts));
    }
}