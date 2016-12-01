<?php
/**
 * MatchField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;
use Closure;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Errors\InvalidTypeException;
use Prewk\Snapper\Errors\SchemaException;
use stdClass;

/**
 * MatchField
 */
class MatchField extends Field
{
    /**
     * @var array
     */
    private $cases;

    /**
     * @var Field
     */
    private $default;

    /**
     * MatchField constructor
     *
     * @param string $name
     * @param array $cases
     * @param Field $default
     * @param bool $optional
     * @param mixed|null $fallback
     * @param mixed|null $circularFallback
     * @throws InvalidTypeException
     */
    public function __construct(
        string $name,
        array $cases,
        Field $default,
        bool $optional = false,
        $fallback = null,
        $circularFallback = null
    ) {
        parent::__construct($name, $optional, $fallback, $circularFallback);

        foreach ($cases as $case) {
            if (!is_array($case) || count($case) !== 2 || !($case[1] instanceof Field)) {
                throw new InvalidTypeException("Cases must be of the type signature: [mixed, Field]");
            }
        }

        $this->cases = $cases;
        $this->default = $default;
    }

    /**
     * Perform the match and return the correct case
     *
     * @param array $fields
     * @return Field
     * @throws SchemaException
     */
    public function resolve(array $fields): Field {
        $value = null;

        if (!array_key_exists($this->name, $fields)) {
            if ($this->optional) {
                $value = $this->fallback;
            } else {
                throw new SchemaException("Required field missing for MatchField: {$this->name}");
            }
        } else {
            $value = $fields[$this->name];
        }

        foreach ($this->cases as $case) {
            list($match, $field) = $case;

            if ($match === $value) {
                return $field;
            }
        }

        return $this->default;
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
        $field = $this->resolve($fields);

        if ($forceCircularFallback) {
            return [$field->getName() => new TaskRawValue($field->getCircularFallback())];
        }

        return $field->compile($idMaker, $fields);
    }

    /**
     * Get field
     *
     * @param array $fields
     * @return string
     */
    public function getName(array $fields = []): string
    {
        if (count($fields)) {
            return $this->resolve($fields)->getName();
        } else {
            return parent::getName();
        }
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
        $field = $this->resolve($fields);

        return $field->transform($fields, $transformer);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "MAP",
            "name" => $this->name,
            "cases" => array_map(function(array $case) {
                return [
                    $case[0],
                    $case[1]->toArray(),
                ];
            }, $this->cases),
            "default" => $this->default->toArray(),
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

        $obj->type = "MATCH";
        $obj->name = $this->name;
        $obj->cases = array_map(function(array $case) {
            return [
                $case[0],
                $case[1]->toObject(),
            ];
        }, $this->cases);
        $obj->default = $this->default->toObject();
        $obj->optional = $this->optional;
        $obj->fallback = $this->fallback;
        $obj->circularFallback = $this->circularFallback;


        return $obj;
    }
}