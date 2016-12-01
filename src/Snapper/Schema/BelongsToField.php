<?php
/**
 * BelongsToField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Closure;
use Illuminate\Contracts\Support\MessageBag;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\TaskAlias;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Errors\SchemaException;
use Prewk\Snapper\Snapshot;
use stdClass;

/**
 * BelongsToField
 */
class BelongsToField extends Field
{
    /**
     * @var string
     */
    private $foreignEntity;

    /**
     * @var string
     */
    private $localKey;

    /**
     * BelongsToField constructor
     *
     * @param string $name
     * @param string $foreignEntity
     * @param string $localKey
     * @param bool $optional
     * @param mixed|null $fallback
     * @param mixed|null $circularFallback
     */
    public function __construct(
        string $name,
        string $foreignEntity,
        string $localKey,
        bool $optional = false,
        $fallback = null,
        $circularFallback = null
    )
    {
        parent::__construct($name, $optional, $fallback, $circularFallback);
        $this->foreignEntity = $foreignEntity;
        $this->localKey = $localKey;
    }


    /**
     * Get foreign entity name
     *
     * @return string
     */
    public function getForeignEntity(): string
    {
        return $this->foreignEntity;
    }

    /**
     * Get local key name
     *
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * Validate the given value against the schema
     *
     * @param MessageBag $errors
     * @param array $fields
     * @param Snapshot $entities
     * @return MessageBag
     */
    public function validate(MessageBag $errors, array $fields, Snapshot $entities): MessageBag
    {
        $errors = parent::validate($errors, $fields, $entities);

        if ($errors->isEmpty()) {
            $value = isset($fields[$this->name]) ? $fields[$this->name] : $this->fallback;

            if (is_string($value) || is_int($value)) {
                if (!$entities->hasEntity($this->foreignEntity, $value)) {
                    $errors->add("BelongsTo", "Couldn't find required relation for {$this->name}");
                }
            }
        }

        return $errors;
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
        return [$this->name => $transformer($this->foreignEntity, $this->getValue($fields))];
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
        $value = $this->getValue($fields);

        if ($forceCircularFallback) {
            return [$this->localKey => new TaskRawValue($this->circularFallback)];
        } else {
            return [$this->localKey => new TaskAlias($idMaker->getId($this->getForeignEntity(), $value))];
        }
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "BELONGS_TO",
            "name" => $this->name,
            "foreignEntity" => $this->foreignEntity,
            "localKey" => $this->localKey,
            "optional" => $this->optional,
            "fallback" => $this->fallback,
            "circularFallback" => $this->circularFallback,
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

        $obj->type = "BELONGS_TO";
        $obj->name = $this->name;
        $obj->foreignEntity = $this->foreignEntity;
        $obj->localKey = $this->localKey;
        $obj->optional = $this->optional;
        $obj->fallback = $this->fallback;
        $obj->circularFallback = $this->circularFallback;

        return $obj;
    }
}