<?php
/**
 * IdMaker
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Compiler;
use Prewk\Snapper\Errors\CompilerException;

/**
 * IdMaker
 */
class IdMaker
{
    /**
     * @var int
     */
    private $nextId = 1;

    /**
     * @var array
     */
    private $books = [];

    /**
     * @var array
     */
    private $tuples = [];

    /**
     * @var array
     */
    private $morphTable;

    /**
     * IdMaker constructor
     * 
     * @param array $morphTable
     */
    public function __construct(array $morphTable = [])
    {
        $this->morphTable = $morphTable;
    }

    /**
     * Factory
     *
     * @param array $morphTable
     * @return IdMaker
     */
    public function make(array $morphTable = []): IdMaker
    {
        return new static($morphTable);
    }
    
    /**
     * Create or get id
     *
     * @param string $name
     * @param mixed $id
     * @return int
     */
    public function getId(string $name, $id): int
    {
        if (array_key_exists($name, $this->morphTable)) {
            $name = $this->morphTable[$name];
        }

        $key = "$name/$id";

        if (!array_key_exists($key, $this->books)) {
            $madeId = $this->nextId++;
            $this->books[$key] = $madeId;
            $this->tuples[$madeId] = [$name, $id];
        }

        return $this->books[$key];
    }
    
    /**
     * Get a [$name, $id] tuple for the given internal id
     *
     * @param int $id
     * @return array
     * @throws CompilerException
     */
    public function getEntity(int $id): array
    {
        if (!array_key_exists($id, $this->tuples)) {
            throw new CompilerException("Tried to get (entity, id) tuple for an unknown internal id: $id");
        }
        
        return $this->tuples[$id];
    }

    /**
     * Get books
     * 
     * @return array
     */
    public function getBooks()
    {
        return $this->books;
    }
}