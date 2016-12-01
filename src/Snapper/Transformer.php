<?php
/**
 * Transformer
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;

use Closure;
use Prewk\Snapper\Schema\Field;
use Prewk\Snapper\Snapshot\EntityRow;

/**
 * Transformer
 */
class Transformer
{
    /**
     * Transform the given entities with the given transformer
     *
     * @param Schema $schema
     * @param Snapshot $entities
     * @param Closure $transformer
     * @return Snapshot
     */
    public function transform(Schema $schema, Snapshot $entities, Closure $transformer): Snapshot
    {
        $morphTable = $schema->getMorphTable();

        return $entities->map(function(EntityRow $entityRow) use ($schema, $transformer, $morphTable) {
            $schemaFields = $schema->getEntityByName($entityRow->getName())->getFields();

            return new EntityRow(
                $entityRow->getName(),
                $transformer($entityRow->getName(), $entityRow->getKey()),
                array_reduce($schemaFields, function(array $transformed, Field $field) use ($entityRow, $transformer, $morphTable) {
                    return array_merge($transformed, $field->transform($entityRow->getFields(), function($name, $id) use ($transformer, $morphTable) {
                        if (array_key_exists($name, $morphTable)) {
                            $name = $morphTable[$name];
                        }
                        
                        return $transformer($name, $id);
                    }));
                }, [])
            );
        });
   }
}