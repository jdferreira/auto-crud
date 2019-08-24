<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Ferreira\AutoCrud\Injectors\SeederInjector;
use Ferreira\AutoCrud\Generators\SeederGenerator;

class SeederCommandTest extends TestCase
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
        $this->app->bind(SeederGenerator::class, function () {
            return Mockery::mock(SeederGenerator::class, function ($mock) {
                $mock->shouldReceive('save');
            });
        });

        $this->artisan('autocrud:seeder');
    }

    /** @test */
    public function it_injects_seeder_calls_into_the_default_seeder()
    {
        $this->app->bind(SeederInjector::class, function () {
            return Mockery::mock(SeederInjector::class, function ($mock) {
                $mock->shouldReceive('inject');
            });
        });

        $this->artisan('autocrud:seeder');
    }
}
