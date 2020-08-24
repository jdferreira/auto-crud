<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ViewEditGenerator;
use Ferreira\AutoCrud\Generators\ViewShowGenerator;
use Ferreira\AutoCrud\Generators\ViewIndexGenerator;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCommandTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    private function assertGeneratorIsCalled(string $class)
    {
        $this->app->bind($class, function () use ($class) {
            return Mockery::mock($class, function ($mock) {
                $mock->shouldReceive('save');
            });
        });

        $this->artisan('autocrud:view');
    }

    /** @test */
    public function it_calls_on_the_index_view_generators()
    {
        $this->assertGeneratorIsCalled(ViewIndexGenerator::class);
    }

    /** @test */
    public function it_calls_on_the_create_view_generators()
    {
        $this->assertGeneratorIsCalled(ViewCreateGenerator::class);
    }

    /** @test */
    public function it_calls_on_the_show_view_generators()
    {
        $this->assertGeneratorIsCalled(ViewShowGenerator::class);
    }

    /** @test */
    public function it_calls_on_the_edit_view_generators()
    {
        $this->assertGeneratorIsCalled(ViewEditGenerator::class);
    }

    // TODO: We need to test the options --no-index, etc.
}
