<?php
/**
 * TaskAssembledAlias
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Prewk\Snapper\Errors\CompilerException;
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
     * @param array $parts ["PART"|"ALIAS", "NONE"|"JSON", mixed]
     * @throws CompilerException
     */
    public function __construct(
        array $parts
    )
    {
        foreach ($parts as $tuple) {
            if (count($tuple) !== 3) {
                throw new CompilerException("TaskAssembledAlias parts must be a tuple of 3 entries");
            }

            list($type, $cast) = $tuple;

            if (!in_array($type, ["PART", "ALIAS"])) {
                throw new CompilerException("TaskAssembledAlias part tuple index 0 must be PART or ALIAS, found: $type");
            } elseif (!in_array($cast, ["NONE", "JSON"])) {
                throw new CompilerException("TaskAssembledAlias part tuple index 1 must be NONE or JSON, found: $cast");
            }
        }
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