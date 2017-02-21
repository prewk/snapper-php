<?php
/**
 * ValueField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Illuminate\Contracts\Support\MessageBag;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Errors\InvalidEnumException;
use Prewk\Snapper\Errors\SchemaException;
use Prewk\Snapper\Snapshot;
use stdClass;

/**
 * ValueField
 */
class ValueField extends Field
{
    /**
     * @var string
     */
    private $cast;

    /**
     * ValueField constructor
     *
     * @param string $name
     * @param string $cast
     * @param bool $optional
     * @param mixed $fallback
     * @param mixed $circularFallback
     * @throws InvalidEnumException
     */
    public function __construct(
        string $name,
        string $cast,
        bool $optional,
        $fallback,
        $circularFallback
    )
    {
        if (!in_array($cast, ["NONE", "JSON", "INTEGER", "FLOAT"])) {
            throw new InvalidEnumException("Invalid 'cast' enum: $cast");
        }

        parent::__construct($name, $optional, $fallback, $circularFallback);

        $this->cast = $cast;
    }

    /**
     * Get cast
     *
     * @return string
     */
    public function getCast(): string
    {
        return $this->cast;
    }

    /**
     * Try to cast a value
     *
     * @param mixed $value
     * @return mixed
     * @throws SchemaException
     */
    public function castValue($value)
    {
        switch ($this->cast) {
            case "JSON":
                $json = @json_encode($value);

                if ($json === false) {
                    throw new SchemaException("Couldn't cast value to JSON: ". json_last_error_msg());
                } else {
                    return $json;
                }
            case "INTEGER":
                return intval($value);
            case "FLOAT":
                return floatval($value);
            default:
                return $value;
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

            try {
                $this->castValue($value);
            } catch (SchemaException $e) {
                $errors->add("ValueField", "Couldn't cast value: " . $e->getMessage());
            }
        }

        return $errors;
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
            return [$this->name => new TaskRawValue($this->circularFallback)];
        }

        return [$this->name => new TaskRawValue($this->castValue($this->getValue($fields)))];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "type" => "VALUE",
            "cast" => $this->cast,
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

        $obj->type = "VALUE";
        $obj->name = $this->name;
        $obj->cast = $this->cast;
        $obj->optional = $this->optional;
        $obj->fallback = $this->fallback;
        $obj->circularFallback = $this->circularFallback;

        return $obj;
    }
}