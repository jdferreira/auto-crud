<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use Ferreira\AutoCrud\Generators\TableBasedGenerator;

class TableBasedGeneratorTest extends TestCase
{
    /** @test */
    public function it_detects_model_namespace()
    {
        $table = $this->mockTable('players');

        $stub = $this->getMockForAbstractClass(
            TableBasedGenerator::class,
            [$this->files, app(DatabaseInformation::class), $table]
        );

        $this->assertEquals('App', $stub->modelNamespace());

        $stub = $this->getMockForAbstractClass(
            TableBasedGenerator::class,
            [$this->files, app(DatabaseInformation::class), $table]
        )->setModelDirectory('Models');

        $this->assertEquals('App\\Models', $stub->modelNamespace());
    }

    /** @test */
    public function it_knows_the_model_class()
    {
        $table = $this->mockTable('players');

        $stub = $this->getMockForAbstractClass(
            TableBasedGenerator::class,
            [$this->files, app(DatabaseInformation::class), $table]
        );

        $this->assertEquals('Player', $stub->modelClass());
    }
}
