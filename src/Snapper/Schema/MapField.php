<?php
/**
 * MapField
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Closure;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Support\Arr;
use Prewk\Snapper\Collections\MapDependencyCollection;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\JsonDisassembler;
use Prewk\Snapper\Compiler\TaskAssembledAlias;
use Prewk\Snapper\Compiler\TaskRawValue;
use Prewk\Snapper\Errors\CompilerException;
use Prewk\Snapper\Errors\InvalidEnumException;
use Prewk\Snapper\Errors\InvalidTypeException;
use Prewk\Snapper\Snapshot;
use Prewk\Snapper\Snapshot\EntityRow;
use stdClass;

/**
 * MapField
 */
class MapField extends Field
{
    /**
     * @var MapEntry[]
     */
    private $relations;

    /**
     * @var bool
     */
    private $greedy;

    /**
     * @var MapDependencyCollection
     */
    private $mapDependencyCollection;

    /**
     * @var string
     */
    private $cast;

    /**
     * @var JsonDisassembler
     */
    private $jsonDisassembler;

    /**
     * MapField constructor
     *
     * @param MapDependencyCollection $mapDependencyCollection
     * @param JsonDisassembler $jsonDisassembler
     * @param string $name
     * @param MapEntry[] $relations
     * @param string $cast
     * @param bool $greedy
     * @param bool $optional
     * @param null $fallback
     * @param null $circularFallback
     * @throws InvalidEnumException
     * @throws InvalidTypeException
     */
    public function __construct(
        MapDependencyCollection $mapDependencyCollection,
        JsonDisassembler $jsonDisassembler,
        string $name,
        array $relations,
        string $cast,
        bool $greedy = true,
        bool $optional = false,
        $fallback = null,
        $circularFallback = null
    )
    {
        parent::__construct($name, $optional, $fallback, $circularFallback);

        if (!in_array($cast, ["JSON"])) {
            throw new InvalidEnumException("Invalid 'cast' enum: $cast");
        }

        foreach ($relations as $relation) {
            if (!($relation instanceof MapEntry)) {
                throw new InvalidTypeException("Relations must be of type 'MapEntry'");
            }
        }

        $this->relations = $relations;
        $this->greedy = $greedy;
        $this->mapDependencyCollection = $mapDependencyCollection;
        $this->cast = $cast;
        $this->jsonDisassembler = $jsonDisassembler;
    }

    /**
     * Validate the given value against the schema
     *
     * @param MessageBag $errors
     * @param Field[] $fields
     * @param Snapshot $entities
     * @return MessageBag
     */
    public function validate(MessageBag $errors, array $fields, Snapshot $entities): MessageBag
    {
        $errors = parent::validate($errors, $fields, $entities);

        if ($errors->isEmpty()) {
            $value = isset($fields[$this->name]) ? $fields[$this->name] : $this->fallback;

            if (is_array($value) || $value instanceof stdClass) {
                $dotMap = Arr::dot($value);
                $dependencyCollection = $this->mapDependencyCollection->make();

                // Create a lookup table for keys
                $keyLookup = [];
                $entities->each(function(EntityRow $entityRow) use (&$keyLookup) {
                    $keyLookup[$entityRow->getCompositeKey()] = true;
                });

                // Gather all dependencies in this map
                foreach ($this->relations as $entry) {
                    $dependencyCollection = $entry->getDependencies($dependencyCollection, $value, $dotMap);
                }

                // Check that all dependencies are resolvable using the lookup table
                foreach ($dependencyCollection->all() as $dependency) {
                    if (!array_key_exists($dependency->getRelation() . "-" . $dependency->getId(), $keyLookup)) {
                        $errors->add("MapField", "Couldn't find required relation for {$dependency->getPath()}");
                    }
                }
            } else {
                // Non-traversable maps should be considered an error
                $errors->add("MapField", "MapField must be - or fallback to - an array or stdClass");
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
        $map = $fields[$this->name];
        $dotMap = Arr::dot($map);
        $dependencies = $this->mapDependencyCollection->make();

        foreach ($this->relations as $relation) {
            $dependencies = $relation->getDependencies($dependencies, $map, $dotMap);
        }

        return [$this->name => $dependencies->transform($map, $transformer)];
    }

    /**
     * Compile this field into a task value
     *
     * @param IdMaker $idMaker
     * @param array $fields
     * @param bool $forceCircularFallback
     * @return array <string, TaskValue>
     * @throws CompilerException
     */
    public function compile(IdMaker $idMaker, array $fields, bool $forceCircularFallback = false): array
    {
        if ($forceCircularFallback) {
            return [$this->name => new TaskRawValue($this->circularFallback)];
        }

        $hashToUuid = [];
        $uuidToTaskId = [];
        $transformed = $this->transform($fields, function($name, $id) use (&$hashToUuid, &$uuidToTaskId, $idMaker) {
            $hash = base64_encode("$name/$id");
            if (!array_key_exists($hash, $hashToUuid)) {
                $hashToUuid[$hash] = uniqid("", true);
                $uuidToTaskId[$hashToUuid[$hash]] = $idMaker->getId($name, $id);
            }
            $uuid = $hashToUuid[$hash];

            return $uuid;
        });

        switch ($this->cast) {
            case "JSON":
                $disassembled = $this->jsonDisassembler->disassemble($transformed, array_values($hashToUuid));

                return [$this->name => new TaskAssembledAlias(array_map(function($part) use ($uuidToTaskId) {
                    list($type, $cast, $value) = $part;

                    if ($type === "ALIAS" && array_key_exists($value, $uuidToTaskId)) {
                        return [$type, $cast, $uuidToTaskId[$value]];
                    }

                    return $part;
                }, $disassembled))];
        }
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
                reset($value);
                if (is_int(key($value))) {
                    $json = @json_encode($value);
                } else {
                    $json = @json_encode((object)$value);
                }

                if ($json === false) {
                    throw new SchemaException("Couldn't cast value to JSON: ". json_last_error_msg());
                } else {
                    return $json;
                }
        }
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
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "MAP",
            "name" => $this->name,
            "relations" => array_map(function(MapEntry $field) {
                return $field->toArray();
            }, $this->relations),
            "cast" => $this->cast,
            "greedy" => $this->greedy,
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

        $obj->type = "MAP";
        $obj->name = $this->name;
        $obj->relations = array_map(function(MapEntry $field) {
            return $field->toObject();
        }, $this->relations);
        $obj->cast = $this->cast;
        $obj->greedy = $this->greedy;
        $obj->optional = $this->optional;
        $obj->fallback = $this->fallback;
        $obj->circularFallback = $this->circularFallback;

        return $obj;
    }
}