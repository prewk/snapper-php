<?php
/**
 * Validator
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk\Snapper;

use Illuminate\Contracts\Support\MessageBag;
use JsonSchema\Validator as JsonValidator;
use Prewk\Snapper\Snapshot\EntityRow;
use Prewk\SnapperSchema\SchemaProvider;
use stdClass;

/**
 * Validator
 */
class Validator
{
    /**
     * @var MessageBag
     */
    private $messageBag;

    /**
     * @var JsonValidator
     */
    private $jsonValidator;

    /**
     * @var stdClass
     */
    private $masterSchema;

    /**
     * Validator constructor
     *
     * @param MessageBag $messageBag
     * @param JsonValidator $jsonValidator
     * @param SchemaProvider $schemaProvider
     */
    public function __construct(
        MessageBag $messageBag,
        JsonValidator $jsonValidator,
        SchemaProvider $schemaProvider
    ) {
        $this->messageBag = $messageBag;
        $this->jsonValidator = $jsonValidator;
        $this->masterSchema = $schemaProvider->getMasterAsRef();
    }

    /**
     * Validate the schema against the entities
     *
     * @param Schema $schema
     * @param Snapshot $entities
     * @return MessageBag
     */
    public function validate(Schema $schema, Snapshot $entities): MessageBag
    {
        $this->jsonValidator->check($schema->toObjectArray(), $this->masterSchema);
        $messageBag = $this->messageBag;

        // Validate against the master schema
        if (!$this->jsonValidator->isValid()) {
            foreach ($this->jsonValidator->getErrors() as $error) {
                $messageBag->add("Schema", sprintf("[%s] %s", $error["property"], $error["message"]));
            }
        }

        // Validate individual entities
        $entities->each(function(EntityRow $entity) use ($schema, $entities, &$messageBag) {
            $schemaFields = $schema
                ->getEntityByName($entity->getName())
                ->getFields();

            foreach ($schemaFields as $schemaField) {
                $messageBag = $schemaField->validate($messageBag, $entity->getFields(), $entities);
            }
        });
        
        return $messageBag;
    }
}