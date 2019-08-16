<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\BaseGenerator;

class BaseGeneratorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

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
}
