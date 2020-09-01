<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Ferreira\AutoCrud\Generators\TestGenerator;

class TestCommandTest extends TestCase
{
    protected $migrations = __DIR__ . '/../migrations';

    /** @test */
    public function it_calls_on_the_generator()
    {
        $this->app->bind(TestGenerator::class, function () {
            return Mockery::mock(TestGenerator::class, function ($mock) {
                $mock->shouldReceive('save');
            });
        });

        $this->artisan('autocrud:test');
    }
}
