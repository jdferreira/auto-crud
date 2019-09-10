<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Injectors\RouteInjector;

class RouteInjectorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

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
        $this->injector(['users', 'avatars'], true)->inject();

        $this->assertFileExists(base_path('routes/web.php'));
        $this->assertFileExists(base_path('routes/api.php'));
    }

    /** @test */
    public function it_does_not_create_files_if_not_necessary()
    {
        $this->files->put(
            base_path('routes/web.php'),
            $this->files->get(__DIR__ . '/inputs/routes-web.php')
        );

        $this->assertContains(
            'Make sure this line exists',
            $this->files->get(base_path('routes/web.php'))
        );

        $this->injector(['users'])->inject();

        $this->assertContains(
            'Make sure this line exists',
            $this->files->get(base_path('routes/web.php'))
        );
    }

    /** @test */
    public function it_creates_resource_routes()
    {
        $this->injector(['users', 'roles'])->inject();

        $code = $this->files->get(base_path('routes/web.php'));

        $this->assertContains("Route::resource('users', 'UserController');", $code);
        $this->assertContains("Route::resource('roles', 'RoleController');", $code);
    }

    /** @test */
    public function it_handles_api_resources()
    {
        $this->injector(['users', 'roles'], true)->inject();

        $code = $this->files->get(base_path('routes/web.php'));

        $this->assertContains("Route::resource('users', 'UserController');", $code);
        $this->assertContains("Route::resource('roles', 'RoleController');", $code);

        $code = $this->files->get(base_path('routes/api.php'));

        $this->assertContains("Route::apiResource('users', 'UserController');", $code);
        $this->assertContains("Route::apiResource('roles', 'RoleController');", $code);
    }
}
