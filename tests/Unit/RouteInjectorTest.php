<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Injectors\RouteInjector;

class RouteInjectorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->files->makeDirectory(base_path('routes'));
    }

    private function injector($tables, $api = false): RouteInjector
    {
        return app(RouteInjector::class, [
            'tables' => $tables,
            'api' => $api,
        ]);
    }

    /** @test */
    public function it_creates_the_web_and_api_files_if_necessary()
    {
        $this->injector(['students'], true)->inject();

        $this->assertFileExists(base_path('routes/web.php'));
        $this->assertFileExists(base_path('routes/api.php'));
    }

    /** @test */
    public function it_reuses_existing_route_files()
    {
        $this->files->put(
            base_path('routes/web.php'),
            $this->files->get(__DIR__ . '/inputs/routes-web.php')
        );

        $this->assertStringContainsString(
            'Make sure this line exists',
            $this->files->get(base_path('routes/web.php'))
        );

        $this->injector(['students'])->inject();

        $this->assertStringContainsString(
            'Make sure this line exists',
            $this->files->get(base_path('routes/web.php'))
        );
    }

    /** @test */
    public function it_creates_resource_routes()
    {
        $this->injector(['students'])->inject();

        $code = $this->files->get(base_path('routes/web.php'));

        $this->assertStringContainsString("Route::resource('/students', 'StudentController');", $code);
    }

    /** @test */
    public function it_handles_api_resources()
    {
        $this->injector(['students', 'schools'], true)->inject();

        $code = $this->files->get(base_path('routes/web.php'));

        $this->assertStringContainsString("Route::resource('/students', 'StudentController');", $code);

        $code = $this->files->get(base_path('routes/api.php'));

        $this->assertStringContainsString("Route::apiResource('/students', 'StudentController');", $code);
    }
}
