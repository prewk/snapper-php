<?php
/**
 * EntityRow
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper\Snapshot;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Prewk\Snapper\Errors\SchemaException;
use Prewk\Snapper\Schema\Entity;

/**
 * Class EntityRow
 * @package Prewk\Snapper\Snapshot
 */
class EntityRow implements Arrayable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var mixed
     */
    private $key;

    /**
     * @var array
     */
    private $fields;

    /**
     * EntityRow constructor
     *
     * @param string $name
     * @param mixed $key
     * @param array $fields
     */
    public function __construct(
        string $name,
        $key,
        array $fields
    ) {
        $this->name = $name;
        $this->key = $key;
        $this->fields = $fields;
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
     * Get key
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get a specific field
     * 
     * @param string $name
     * @return mixed
     */
    public function getField(string $name)
    {
        return $this->fields[$name];
    }

    /**
     * Get composite key
     *
     * @return string
     */
    public function getCompositeKey(): string
    {
        return $this->name . "-" . $this->key;
    }

    /**
     * Map over fields
     *
     * @param Closure $mapper
     * @return array
     */
    public function mapFields(Closure $mapper): array
    {
        $new = [];
        foreach ($this->fields as $key => $value) {
            $new[] = $mapper($value, $key);
        }

        return $new;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "name" => $this->name,
            "key" => $this->key,
            "fields" => $this->fields,
        ];
    }

    /**
     * Make a new EntityRow instance with circular fallbacks in the given field names
     * 
     * @param Entity $schema
     * @param array $names
     * @return EntityRow
     * @throws SchemaException
     */
    public function makeWithCircularFallbacks(Entity $schema, array $names = []): EntityRow
    {
        $newFields = [];

        foreach ($this->fields as $name => $data) {
            if (in_array($name, $names)) {
                // This field should be forced to a circular fallback value
                $newFields[$name] = $schema->getFieldByName($name)->getCircularFallback();
            } else {
                $newFields[$name] = $data;
            }
        }

        return new static($this->name, $this->key, $newFields);
    }
}