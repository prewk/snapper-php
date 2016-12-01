<?php
/**
 * Field
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\MessageBag;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Errors\SchemaException;
use Prewk\Snapper\Snapshot;

/**
 * Field
 */
abstract class Field implements Arrayable, Objectable
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $optional;

    /**
     * @var mixed
     */
    protected $fallback;

    /**
     * @var mixed
     */
    protected $circularFallback;

    /**
     * Field constructor.
     * @param string $name
     * @param bool $optional
     * @param mixed $fallback
     * @param mixed $circularFallback
     */
    public function __construct(
        string $name,
        bool $optional,
        $fallback,
        $circularFallback
    ) {
        $this->name = $name;
        $this->optional = $optional;
        $this->fallback = $fallback;
        $this->circularFallback = $circularFallback;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Is optional?
     *
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * Get fallback
     *
     * @return mixed
     */
    public function getFallback()
    {
        return $this->fallback;
    }

    /**
     * Get circular fallback
     *
     * @return mixed
     */
    public function getCircularFallback()
    {
        return $this->circularFallback;
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
        if (!array_key_exists($this->name, $fields) && !$this->optional) {
            return $errors->add("Field", "Non-optional field {$this->name} missing its value");
        }

        return $errors;
    }

    /**
     * Get value with fallback
     *
     * @param array $fields
     * @return mixed
     * @throws SchemaException
     */
    public function getValue(array $fields)
    {
        if (!array_key_exists($this->name, $fields)) {
            if ($this->optional) {
                return $this->fallback;
            } else {
                throw new SchemaException("Non-optional field {$this->name} missing its value");
            }
        }

        return $fields[$this->name];
    }

    /**
     * Transform the relevant field with the given transform
     *
     * @param array $fields
     * @param Closure $transformer Gets the relation entity's name and id as argument, expects the transformed id back:
     *                             (string $relation, mixed $id) => mixed
     * @return array
     * @throws SchemaException
     */
    public function transform(array $fields, Closure $transformer): array
    {
        return [$this->name => $this->getValue($fields)];
    }

    /**
     * Compile this field into a task value
     *
     * @param IdMaker $idMaker
     * @param array $fields
     * @param bool $forceCircularFallback
     * @return array <string, TaskValue>
     */
    abstract public function compile(IdMaker $idMaker, array $fields, bool $forceCircularFallback = false): array;
}