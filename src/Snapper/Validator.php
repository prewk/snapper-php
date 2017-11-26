<?php
/**
 * Validator
 *
 * @author Oskar Thornblad
 */

declare(strict_types=1);

namespace Prewk\Snapper;

use Exception;
use Prewk\Snapper\Deserializer\DeserializationBookKeeper;

/**
 * Validator
 */
class Validator extends Deserializer
{
    /**
     * @var int
     */
    private $fakeIdCnt = 0;

    /**
     * @var DeserializationBookKeeper
     */
    private $bookKeeper;

    /**
     * Validator constructor
     *
     * @param DeserializationBookKeeper $bookKeeper
     * @param array $recipes
     */
    public function __construct(DeserializationBookKeeper $bookKeeper, array $recipes)
    {
        $inserters = $this->makeInserters($recipes);
        $updaters = $this->makeUpdaters($recipes);

        $this->bookKeeper = $bookKeeper;

        parent::__construct($bookKeeper, $recipes, $inserters, $updaters);
    }

    /**
     * Make fake inserters
     *
     * @param array $recipes
     * @return array
     */
    protected function makeInserters(array $recipes): array
    {
        $inserters = [];
        foreach ($recipes as $field => $_) {
            $inserters[$field] = function(array $rows) {
                $ids = [];
                foreach ($rows as $_) {
                    $ids[] = ++$this->fakeIdCnt;
                }
                return $ids;
            };
        }
        return $inserters;
    }

    /**
     * Make fake updaters
     *
     * @param array $recipes
     * @return array
     */
    protected function makeUpdaters(array $recipes): array
    {
        $updaters = [];
        foreach ($recipes as $field => $_) {
            $updaters[$field] = function() {};
        }
        return $updaters;
    }

    /**
     * Validate a serialization
     *
     * @param array $serialization
     * @return bool
     */
    public function validate(array $serialization): bool
    {
        $this->bookKeeper->reset();
        try {
            $this->deserialize($serialization);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}