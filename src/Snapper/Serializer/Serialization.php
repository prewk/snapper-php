<?php
/**
 * Serialization
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper\Serializer;

/**
 * Serialization
 */
class Serialization
{
    /**
     * @var array
     */
    private $ops;

    /**
     * @var SerializationBookKeeper
     */
    private $books;

    /**
     * Serialization constructor.
     * @param array $ops
     * @param SerializationBookKeeper $books
     */
    public function __construct(array $ops, SerializationBookKeeper $books)
    {
        $this->ops = $ops;
        $this->books = $books;
    }

    /**
     * Get the sequence of operations
     *
     * @return array
     */
    public function getOps(): array
    {
        return $this->ops;
    }

    /**
     * Get a dictionary of internal ids, types and ids in the following format:
     * [
     *   "type_1" => [
     *     "3d87bbb4-87e0-4464-94c0-1b4ee401d655" => 1055,
     *     "d80bdaee-eb3c-435c-8919-097fb0ebc12c" => 2063
     *   ],
     *   "type_2 => [
     *     "c19a2e4c-40b5-474e-b847-55a1aba71475" => 572,
     *     "2f7ff8c5-6af7-44d6-b87b-f47009007ab9" => 523
     *   ]
     * ]
     *
     * @return array
     */
    public function getIdManifest(): array
    {
        return $this->books->getIdDict();
    }
}