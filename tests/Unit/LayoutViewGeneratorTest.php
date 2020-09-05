<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\LayoutViewGenerator;

class LayoutViewGeneratorTest extends TestCase
{
    /** @test */
    public function it_creates_a_layout_blade_file()
    {
        $code = $this->app->make(LayoutViewGenerator::class)->generate();

        $this->assertIsString($code);
    }

    /** @test */
    public function it_saves_the_file_in_the_correct_place()
    {
        $this->app->make(LayoutViewGenerator::class)->save();

        $this->assertFileExists(base_path('resources/views/layouts/app.blade.php'));
    }

    /** @test */
    public function it_has_a_content_section()
    {
        $code = $this->app->make(LayoutViewGenerator::class)->generate();

        $this->assertContains("@yield('content')", $code);
    }
}
