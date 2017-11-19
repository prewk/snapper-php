<?php
/**
 * SchemaValidator
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use JsonSchema\Validator;
use Prewk\Result;
use Prewk\Result\{Err, Ok};
use Prewk\SnapperSchema\SchemaProvider;
use stdClass;

/**
 * SchemaValidator
 */
class SchemaValidator
{
    /**
     * @var Validator
     */
    private $jsonValidator;

    /**
     * @var stdClass
     */
    private $schemaRef;

    /**
     * SchemaValidator constructor
     *
     * @param Validator $jsonValidator
     */
    public function __construct(Validator $jsonValidator)
    {
        $this->jsonValidator = $jsonValidator;
        $this->schemaRef = SchemaProvider::getRecipeAsRef();
    }

    /**
     * Validate the recipe against the JSON schema
     *
     * @param stdClass $recipe
     * @return Result Result<null, string[]>
     */
    public function validate(stdClass $recipe): Result
    {
        $this->jsonValidator->check($recipe, $this->schemaRef);

        if (!$this->jsonValidator->isValid()) {
            $errors = [];
            foreach ($this->jsonValidator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error["property"], $error["message"]);
            }

            return new Err($errors);
        }

        return new Ok(null);
    }


}