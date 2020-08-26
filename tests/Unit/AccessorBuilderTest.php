<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\AccessorBuilder;

class AccessorBuilderTest extends TestCase
{
    /** @test */
    public function it_builds_labels_and_accessor()
    {
        $builder = new AccessorBuilder($this->mockTable('tablename', [
            'column' => [],
        ]), 'column');

        $this->assertNotNull($builder->label);
        $this->assertNotNull($builder->accessor);
    }

    /** @test */
    public function it_capitalizes_column_names()
    {
        $table = $this->mockTable('tablename', [
            'name' => [],
            'wants_email' => [],
        ]);

        $this->assertEquals('Name', (new AccessorBuilder($table, 'name'))->label);
        $this->assertEquals('Wants email', (new AccessorBuilder($table, 'wants_email'))->label);
    }

    // TODO: I need many more tests here! See ViewIndexGeneratorTest for ideas!
}
