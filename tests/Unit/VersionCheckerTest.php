<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\VersionChecker;

class VersionCheckerTest extends TestCase
{
    /** @test */
    public function it_can_check_laravel_version()
    {
        $this->assertEquals(
            app()->version(),
            app(VersionChecker::class)->laravelVersion()
        );
    }

    /** @test */
    public function it_can_compare_laravel_versions()
    {
        $this->assertTrue(
            app(VersionChecker::class)->after('7.0.0')
        );

        $this->assertTrue(
            app(VersionChecker::class)->before('9.0.0')
        );
    }

    /** @test */
    public function it_can_mock_versions()
    {
        $checker = app(VersionChecker::class);

        $checker->mockVersion('8.4.0');

        $this->assertEquals(
            '8.4.0',
            app(VersionChecker::class)->laravelVersion()
        );
    }
}
