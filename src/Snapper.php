<?php
/**
 * Snapper
 *
 * @author Oskar Thornblad
 */

declare(strict_types = 1);

namespace Prewk;

use Illuminate\Support\MessageBag;
use JsonSchema\Validator as JsonValidator;
use Prewk\Snapper\Compiler;
use Prewk\Snapper\Compiler\IdMaker;
use Prewk\Snapper\Compiler\IdResolver;
use Prewk\Snapper\Compiler\Sorters\SortByMostRequired;
use Prewk\Snapper\Schema;
use Prewk\Snapper\Snapshot;
use Prewk\Snapper\TaskSequence;
use Prewk\Snapper\Transformer;
use Prewk\Snapper\Validator;
use Prewk\SnapperSchema\SchemaProvider;

/**
 * Snapper
 */
class Snapper
{
    /**
     * Make a compiler
     *
     * @return Compiler
     */
    public static function makeCompiler(): Compiler
    {
        return new Compiler(new IdMaker, new IdResolver, new TaskSequence, new SortByMostRequired);
    }

    /**
     * Make a validator
     *
     * @return Validator
     */
    public static function makeValidator(): Validator
    {
        return new Validator(new MessageBag, new JsonValidator, new SchemaProvider);
    }

    /**
     * Make a transformer
     * 
     * @return Transformer
     */
    public static function makeTransformer(): Transformer
    {
        return new Transformer;
    }

    /**
     * Make a schema from a JSON string
     *
     * @param string $json
     * @return Schema
     */
    public static function makeSchema(string $json): Schema
    {
        return Schema::fromJSON($json);
    }

    /**
     * Make a snapshot from an array of rows
     *
     * @param array $rows
     * @return Snapshot
     */
    public static function makeSnapshot(array $rows): Snapshot
    {
        return Snapshot::fromArray($rows);
    }
}