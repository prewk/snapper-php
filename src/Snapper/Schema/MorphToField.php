<?php
/**
 * MorphToField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Closure;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\TaskAlias;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Errors\SchemaException;
use stdClass;

/**
 * MorphToField
 */
class MorphToField extends Field
{
    /**
     * @var string
     */
    private $idField;

    /**
     * @var string
     */
    private $typeField;

    /**
     * @var mixed|null
     */
    private $typeCircularFallback;

    /**
     * MorphToField constructor
     *
     * @param string $name
     * @param string $idField
     * @param string $typeField
     * @param bool $optional
     * @param mixed|null $fallback
     * @param mixed|null $idCircularFallback
     * @param mixed|null $typeCircularFallback
     */
    public function __construct(
        string $name,
        string $idField,
        string $typeField,
        bool $optional = false,
        $fallback = null,
        $idCircularFallback = null,
        $typeCircularFallback = null
    ) {
        parent::__construct($name, $optional, $fallback, $idCircularFallback);
        $this->idField = $idField;
        $this->typeField = $typeField;
        $this->typeCircularFallback = $typeCircularFallback;
    }

    /**
     * Get id field
     *
     * @return string
     */
    public function getIdField(): string
    {
        return $this->idField;
    }

    /**
     * Get type field
     *
     * @return string
     */
    public function getTypeField(): string
    {
        return $this->typeField;
    }

    /**
     * Transform the relevant field with the given transform
     *
     * @param array $fields
     * @param Closure $transformer
     * @return array
     * @throws SchemaException
     */
    public function transform(array $fields, Closure $transformer): array
    {
        $morph = $this->getValue($fields);

        if (is_array($morph) && count($morph) === 2) {
            return [$this->name  => [$morph[0], $transformer($morph[0], $morph[1])]];
        } else {
            return [$this->name => $morph];
        }
    }

    /**
     * Compile this field into a task value
     *
     * @param IdMaker $idMaker
     * @param array $fields
     * @param bool $forceCircularFallback
     * @return array <string, TaskValue>
     */
    public function compile(IdMaker $idMaker, array $fields, bool $forceCircularFallback = false): array
    {
        if ($forceCircularFallback) {
            return [
                $this->idField => new TaskRawValue($this->circularFallback),
                $this->typeField => new TaskRawValue($this->typeCircularFallback),
            ];
        }
        
        $morph = $this->getValue($fields);

        if (is_scalar($morph[1])) {
            return [
                $this->idField => new TaskAlias($idMaker->getId($morph[0], $morph[1])),
                $this->typeField => new TaskRawValue($morph[0]),
            ];
        } else {
            return [
                $this->idField => new TaskRawValue($morph[1]),
                $this->typeField => new TaskRawValue($morph[0]),
            ];
        }
    }

    /**
     * Get type circular fallback
     *
     * @return mixed|null
     */
    public function getTypeCircularFallback()
    {
        return $this->typeCircularFallback;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "MORPH_TO",
            "name" => $this->name,
            "idField" => $this->idField,
            "typeField" => $this->typeField,
            "optional" => $this->optional,
            "fallback" => $this->fallback,
            "circularFallback" => $this->circularFallback,
            "typeCircularFallback" => $this->typeCircularFallback,
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

        $obj->type = "MORPH_TO";
        $obj->name = $this->name;
        $obj->idField = $this->idField;
        $obj->typeField = $this->typeField;
        $obj->optional = $this->optional;
        $obj->fallback = $this->fallback;
        $obj->circularFallback = $this->circularFallback;
        $obj->typeCircularFallback = $this->typeCircularFallback;

        return $obj;
    }
}