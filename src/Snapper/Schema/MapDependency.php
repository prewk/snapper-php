<?php
/**
 * MapDependency
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

/**
 * MapDependency
 */
class MapDependency
{
    /**
     * @var mixed
     */
    private $id;

    /**
     * @var string
     */
    private $relation;

    /**
     * @var string
     */
    private $path;

    /**
     * @var bool
     */
    private $inString;

    /**
     * @var int
     */
    private $startPos;

    /**
     * @var int
     */
    private $stopPos;

    /**
     * MapDependency constructor
     *
     * @param mixed $id
     * @param string $relation
     * @param string $path
     * @param bool $inString
     * @param int $startPos
     * @param int $stopPos
     */
    public function __construct(
        $id,
        string $relation,
        string $path,
        bool $inString = false,
        int $startPos = 0,
        int $stopPos = 0
    )
    {
        $this->id = $id;
        $this->relation = $relation;
        $this->path = $path;
        $this->inString = $inString;
        $this->startPos = $startPos;
        $this->stopPos = $stopPos;
    }

    /**
     * Get id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get relation
     *
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Get dot path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Is in string?
     *
     * @return boolean
     */
    public function isInString(): bool
    {
        return $this->inString;
    }

    /**
     * Get start pos
     *
     * @return int
     */
    public function getStartPos(): int
    {
        return $this->startPos;
    }

    /**
     * Get stop pos
     *
     * @return int
     */
    public function getStopPos(): int
    {
        return $this->stopPos;
    }

    /**
     * Adjust offset if needed
     *
     * @param int $offsetChange
     * @param int $at
     * @return MapDependency
     */
    public function adjustOffset(int $offsetChange, int $at): MapDependency
    {
        if ($this->startPos > $at) {
            $this->startPos += $offsetChange;
            $this->stopPos += $offsetChange;
        }

        return $this;
    }
}