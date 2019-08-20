<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Ferreira\AutoCrud\Generators\FactoryGenerator;

class FactoryCommandTest extends TestCase
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
        $this->app->bind(FactoryGenerator::class, function () {
            return Mockery::mock(FactoryGenerator::class, function ($mock) {
                $mock->shouldReceive('save');
            });
        });

        $this->artisan('autocrud:factory');
    }
}
