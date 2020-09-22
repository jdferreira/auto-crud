<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\PhpGenerator;

class PhpGeneratorTest extends TestCase
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
            PhpGenerator::removeDefaults($args, $defaults)
        );
    }
}
