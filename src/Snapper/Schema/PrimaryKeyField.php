<?php
/**
 * PrimaryKeyField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Errors\ForbiddenOperationException;
use stdClass;

/**
 * PrimaryKeyField
 */
class PrimaryKeyField extends Field
{
    /**
     * PrimaryKeyField constructor
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name, false, null, null);
    }

    /**
     * Get fallback
     *
     * @return mixed
     * @throws ForbiddenOperationException
     */
    public function getFallback()
    {
        throw new ForbiddenOperationException("Primary keys can't fallback");
    }

    /**
     * Get circular fallback
     *
     * @return mixed
     * @throws ForbiddenOperationException
     */
    public function getCircularFallback()
    {
        throw new ForbiddenOperationException("Primary keys can't fallback");
    }

    /**
     * Compile this field into a task value
     *
     * @param IdMaker $idMaker
     * @param array $fields
     * @param bool $forceCircularFallback
     * @return array
     * @throws ForbiddenOperationException
     */
    public function compile(IdMaker $idMaker, array $fields, bool $forceCircularFallback = false): array
    {
        throw new ForbiddenOperationException("It makes no sense to compile the primary key field");
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "PRIMARY_KEY",
            "name" => $this->name,
        ];
    }

    /**
     * Convert to stdClass
     *
     * @return stdClass
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass;

        $obj->type = "PRIMARY_KEY";
        $obj->name = $this->name;

        return $obj;
    }
}