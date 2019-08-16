<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ModelGenerator;

class ModelCommandTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /** @test */
    public function it_calls_on_the_generator()
    {
        $this->app->bind(ModelGenerator::class, function () {
            return Mockery::mock(ModelGenerator::class, function ($mock) {
                $mock->shouldReceive('save');
            });
        });

        $this->artisan('autocrud:model');
    }
}
