<?php
/**
 * Entity
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Schema;

use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Errors\InvalidTypeException;
use Prewk\Snapper\Errors\SchemaException;
use stdClass;

/**
 * Entity
 */
class Entity implements Arrayable, Objectable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var PrimaryKeyField
     */
    private $key;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var string
     */
    private $morphAs;

    /**
     * Entity constructor
     *
     * @param string $name
     * @param PrimaryKeyField $key
     * @param Field[] $fields
     * @param string $morphAs
     * @throws InvalidTypeException
     */
    public function __construct(
        string $name,
        PrimaryKeyField $key,
        array $fields,
        string $morphAs
    )
    {
        foreach ($fields as $index => $field) {
            if (!($field instanceof Field)) {
                throw new InvalidTypeException("Entity fields must be of type 'Field'");
            }
        }

        $this->name = $name;
        $this->key = $key;
        $this->fields = $fields;
        $this->morphAs = $morphAs;
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
     * Get primary key field
     *
     * @return PrimaryKeyField
     */
    public function getKey(): PrimaryKeyField
    {
        return $this->key;
    }

    /**
     * Get fields
     *
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get morphAs value
     *
     * @return string
     */
    public function getMorphAs(): string
    {
        return $this->morphAs;
    }

    /**
     * Get field by name
     * 
     * @param string $name
     * @return Field
     * @throws SchemaException
     */
    public function getFieldByName(string $name): Field
    {
        foreach ($this->fields as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
        }
        
        throw new SchemaException("Couldn't find a Field with the name '$name' in this entity's field collection");
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "type" => "ENTITY",
            "name" => $this->name,
            "morphAs" => $this->morphAs,
            "key" => $this->key->toArray(),
            "fields" => array_map(function(Field $field) {
                return $field->toArray();
            }, $this->fields),
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

        $obj->type = "ENTITY";
        $obj->name = $this->name;
        $obj->morphAs = $this->morphAs;
        $obj->key = $this->key->toObject();
        $obj->fields = array_map(function(Field $field) {
            return $field->toObject();
        }, $this->fields);
        
        return $obj;
    }
}