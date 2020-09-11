<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\BaseGenerator;

class BaseGeneratorTest extends TestCase
{
    /** @test */
    public function it_does_not_emit_arguments_equal_to_their_default()
    {
        $args = [
            'Product::class',
            "'owner_id'",
            "'id'",
        ];

        $defaults = [
            "'user_id'",
            "'id'",
        ];

        $this->assertEquals(
            "Product::class, 'owner_id'",
            BaseGenerator::removeDefaults($args, $defaults)
        );
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        $table = $this->mockTable('players');

        $stub = $this->getMockForAbstractClass(
            BaseGenerator::class,
            [$table]
        );

        $this->assertEquals('App', $stub->modelNamespace());

        $stub = $this->getMockForAbstractClass(
            BaseGenerator::class,
            [$table]
        )->setModelDirectory('Models');

        $this->assertEquals('App\\Models', $stub->modelNamespace());
    }

    /** @test */
    public function it_knows_the_model_class()
    {
        $table = $this->mockTable('players');

        $stub = $this->getMockForAbstractClass(
            BaseGenerator::class,
            [$table]
        );

        $this->assertEquals('Player', $stub->modelClass());
    }
}
