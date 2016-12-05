<?php

use PHPUnit\Framework\TestCase;
use Prewk\Snapper;
use Prewk\SnapperSchema\TestProvider;

class LargeIntegrationTest extends TestCase
{
    public function test_that_a_complex_snapshot_validates()
    {
        $testProvider = new TestProvider;

        $snapshot = Snapper::makeSnapshot(json_decode($testProvider->getSnapshot(), true));
        $schema = Snapper::makeSchema($testProvider->getSchema());
        $validator = Snapper::makeValidator();

        $messages = $validator->validate($schema, $snapshot);

        $this->assertCount(0, $messages);
    }

    public function test_that_a_complex_snapshot_transforms()
    {
        $testProvider = new TestProvider;

        $snapshot = Snapper::makeSnapshot(json_decode($testProvider->getSnapshot(), true));
        $schema = Snapper::makeSchema($testProvider->getSchema());
        $transformer = Snapper::makeTransformer();

        $transformed = $transformer->transform($schema, $snapshot, function($name, $id) {
            return base64_encode("$name/$id");
        });

        $this->assertEquals(json_decode($testProvider->getTransformed(), true), $transformed->toArray());
    }

    public function test_that_a_complex_snapshot_compiles()
    {
        $testProvider = new TestProvider;

        $snapshot = Snapper::makeSnapshot(json_decode($testProvider->getTransformed(), true));
        $schema = Snapper::makeSchema($testProvider->getSchema());
        $compiler = Snapper::makeCompiler();

        $compiled = $compiler->compile($schema, $snapshot);
        
        $this->assertEquals(json_decode($testProvider->getCompiled(), true), $compiled->toArray());
    }
}