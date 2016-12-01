<?php
/**
 * TaskAlias
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;

use Prewk\Snapper\Errors\ForbiddenOperationException;

/**
 * TaskAlias
 */
class TaskAlias implements TaskValue
{
    /**
     * @var int
     */
    private $alias;

    /**
     * TaskAlias constructor
     *
     * @param int $alias
     */
    public function __construct(
        int $alias
    )
    {
        $this->alias = $alias;
    }

    /**
     * Get alias
     *
     * @return int
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Get dependencies
     *
     * @return int[]
     */
    public function getDependencies(): array
    {
        return [$this->alias];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "TASK_ALIAS",
            "alias" => $this->alias,
        ];
    }

    /**
     * Settle TaskValue with the given alias-id lookup table
     *
     * @param array $aliasLookup
     * @return mixed
     * @throws ForbiddenOperationException
     */
    public function getAsValue(array $aliasLookup)
    {
        if (!array_key_exists($this->alias, $aliasLookup)) {
            throw new ForbiddenOperationException("Needed missing value for alias {$this->alias}");
        }
        
        return $aliasLookup[$this->alias];
    }
}