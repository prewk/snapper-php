<?php
/**
 * Schema
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;

use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Collections\MapDependencyCollection;
use Prewk\Snapper\Compiler\JsonDisassembler;
use Prewk\Snapper\Errors\InvalidTypeException;
use Prewk\Snapper\Errors\SchemaException;
use Prewk\Snapper\Schema\BelongsToField;
use Prewk\Snapper\Schema\Entity;
use Prewk\Snapper\Schema\ListRelationEntry;
use Prewk\Snapper\Schema\MapEntryPath;
use Prewk\Snapper\Schema\MapField;
use Prewk\Snapper\Schema\MatchField;
use Prewk\Snapper\Schema\MorphToField;
use Prewk\Snapper\Schema\PrimaryKeyField;
use Prewk\Snapper\Schema\RegExpRelationEntry;
use Prewk\Snapper\Schema\RegExpRelationMatcher;
use Prewk\Snapper\Schema\ValueField;
use Prewk\Snapper\Schema\ValueRelationEntry;

/**
 * Schema
 */
class Schema implements Arrayable
{
    /**
     * @var Entity[]
     */
    private $entities;
    
    /**
     * Schema constructor
     *
     * @param Entity[] $entities
     * @throws InvalidTypeException
     */
    public function __construct(
        array $entities
    )
    {
        foreach ($entities as $key => $value) {
            if (!($value instanceof Entity)) {
                throw new InvalidTypeException("Schema entities must be of type Entity");
            }
        }

        $this->entities = $entities;
    }

    /**
     * Create a node from a schema traversable
     *
     * @param mixed $schema
     * @return mixed
     * @throws SchemaException
     */
    public static function makeNode($schema)
    {
        switch ($schema->type) {
            case "ENTITY":
                return new Entity(
                    $schema->name,
                    self::makeNode($schema->key),
                    array_map(function($rawField) {
                        return self::makeNode($rawField);
                    },
                    $schema->fields),
                    $schema->morphAs
                );
            case "VALUE":
                return new ValueField(
                    $schema->name,
                    $schema->cast,
                    $schema->optional,
                    $schema->fallback,
                    $schema->circularFallback
                );
            case "BELONGS_TO":
                return new BelongsToField(
                    $schema->name,
                    $schema->foreignEntity,
                    $schema->localKey,
                    $schema->optional,
                    $schema->fallback,
                    $schema->circularFallback
                );
            case "MORPH_TO":
                return new MorphToField(
                    $schema->name,
                    $schema->idField,
                    $schema->typeField,
                    $schema->optional,
                    $schema->fallback,
                    $schema->circularFallback,
                    $schema->typeCircularFallback
                );
            case "MAP":
                return new MapField(
                    new MapDependencyCollection,
                    new JsonDisassembler,
                    $schema->name,
                    array_map(function($rawEntry) {
                        return self::makeNode($rawEntry);
                    }, $schema->relations),
                    $schema->cast,
                    $schema->greedy,
                    $schema->optional,
                    $schema->fallback,
                    $schema->circularFallback
                );
            case "MATCH":
                return new MatchField(
                    $schema->name,
                    array_map(function($case) {
                        return [$case[0], self::makeNode($case[1])];
                    }, $schema->cases),
                    self::makeNode($schema->default),
                    $schema->optional,
                    $schema->fallback,
                    $schema->circularFallback
                );
            case "LIST_RELATION_ENTRY":
                return new ListRelationEntry(new MapEntryPath($schema->path), $schema->relation);
            case "PRIMARY_KEY":
                return new PrimaryKeyField($schema->name);
            case "REG_EXP_RELATION_ENTRY":
                return new RegExpRelationEntry(
                    new MapEntryPath($schema->path),
                    array_map(function($rawMatcher) {
                        return new RegExpRelationMatcher($rawMatcher->expression, $rawMatcher->relations, $rawMatcher->cast);
                    }, $schema->matchers));
            case "VALUE_RELATION_ENTRY":
                return new ValueRelationEntry(new MapEntryPath($schema->path), $schema->relation);
            default:
                throw new SchemaException("Invalid field type encountered: {$schema->type}");
        }
    }

    /**
     * Create a Schema instance from a JSON string
     *
     * @param string $json
     * @return Schema
     */
    public static function fromJSON(string $json): Schema
    {
        return new static(
            array_map(function($rawEntity) {
                return self::makeNode($rawEntity);
            }, json_decode($json))
        );
    }

    /**
     * Get entity by name
     *
     * @param string $name
     * @return Entity
     * @throws SchemaException
     */
    public function getEntityByName(string $name): Entity
    {
        foreach ($this->entities as $entity) {
            if ($entity->getName() === $name) {
                return $entity;
            }
        }

        throw new SchemaException("Schema doesn't have an entity named: $name");
    }

    /**
     * Get morph table
     * 
     * @return array
     */
    public function getMorphTable(): array
    {
        return array_reduce($this->entities, function(array $morphTable, Entity $entity) {
            if (!array_key_exists($entity->getMorphAs(), $morphTable)) {
                $morphTable[$entity->getMorphAs()] = $entity->getName();
            }

            return $morphTable;
        }, []);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function(Entity $entity) {
            return $entity->toArray();
        }, $this->entities);
    }

    /**
     * Convert to array of objects
     *
     * @return array
     */
    public function toObjectArray(): array
    {
        return array_map(function(Entity $entity) {
            return $entity->toObject();
        }, $this->entities);
    }
}