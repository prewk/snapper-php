<?php
/**
 * MapEntryPath
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Prewk\Snapper\Errors\SchemaException;
use Illuminate\Support\Arr;

/**
 * MapEntryPath
 */
class MapEntryPath
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var bool
     */
    private $isRegExp = false;

    /**
     * MapEntryPath constructor
     *
     * @param string $path
     * @throws SchemaException
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        if ($path[0] === "/") {
            // Test the expression
            if (@preg_match($path, "") === false) {
                throw new SchemaException("Use of an invalid regular expression as path: $path");
            }
            $this->isRegExp = true;
        }
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Query a dot-indexed map
     *
     * @param array $map
     * @param array $dotMap
     * @return array
     */
    public function query(array $map, array $dotMap): array
    {
        if (!$this->isRegExp && Arr::has($map, $this->path)) {
            return [$this->path => Arr::get($map, $this->path)];
        }

        $results = [];

        foreach ($dotMap as $key => $value) {
            if ($this->isRegExp && preg_match($this->path, $key) === 1) {
                $results[$key] = $value;
            } elseif ($key === $this->path) {
                $results[$key] = $value;
            }
        }

        return $results;
    }
}