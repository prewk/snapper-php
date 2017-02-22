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
use Prewk\Snapper\Errors\InvalidEnumException;
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
     * @var string
     */
    private $relationCondition;

    /**
     * @var string
     */
    private $conditionMatcher;

    /**
     * BelongsToField constructor
     *
     * @param string $name
     * @param string $foreignEntity
     * @param string $localKey
     * @param string $relationCondition
     * @param bool $optional
     * @param mixed|null $fallback
     * @param mixed|null $circularFallback
     * @param string $conditionMatcher
     * @throws InvalidEnumException
     */
    public function __construct(
        string $name,
        string $foreignEntity,
        string $localKey,
        string $relationCondition,
        bool $optional = false,
        $fallback = null,
        $circularFallback = null,
        string $conditionMatcher = ""
    )
    {
        if (!in_array($relationCondition, ["NON_ZERO_INT", "REGEXP", "NONE"])) {
            throw new InvalidEnumException("Invalid 'relationCondition' enum: $relationCondition");
        }

        parent::__construct($name, $optional, $fallback, $circularFallback);

        $this->foreignEntity = $foreignEntity;
        $this->localKey = $localKey;
        $this->relationCondition = $relationCondition;
        $this->conditionMatcher = $conditionMatcher;
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
     * Does the value pass our expectations for a foreign id?
     *
     * @param $value
     * @return bool
     */
    protected function isValidForeignId($value): bool {
        switch ($this->relationCondition) {
            case "NON_ZERO_INT":
                return is_int($value) && $value > 0;
            case "REGEXP":
                return preg_match($this->conditionMatcher, (string)$value) === 1;
            case "NONE":
                return true;
            default:
                return false;
        }
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

            if ($this->isValidForeignId($value)) {
                if (!$entities->hasEntity($this->foreignEntity, $value)) {
                    var_dump($this->foreignEntity, $value);
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
        $value = $this->getValue($fields);

        return [
            $this->name =>
                $this->isValidForeignId($value)
                    ? $transformer($this->foreignEntity, $this->getValue($fields))
                    : $value
        ];
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
            return [
                $this->localKey =>
                    $this->isValidForeignId($value)
                        ? new TaskAlias($idMaker->getId($this->getForeignEntity(), $value))
                        : new TaskRawValue($value)
            ];
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
            "relationCondition" => $this->relationCondition,
            "conditionMatcher" => $this->conditionMatcher,
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
        $obj->relationCondition = $this->relationCondition;
        $obj->conditionMatcher = $this->conditionMatcher;

        return $obj;
    }
}