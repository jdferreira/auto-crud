<?php

namespace Tests\Features;

use Tests\TestCase;
use Illuminate\Support\Str;

/**
 * @skip
 * @ignore
 */
class AutoCrudCommandTest extends TestCase
{
    /**
     * The names of the tables that should generate files.
     *
     * @var string[]
     */
    private $tablenames = [
        'users', 'avatars', 'products', 'roles', 'sales',
    ];

    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Assert that files were generated for the given tablenames.
     *
     * @param string[] $tablenames
     */
    private function assertFilesWereGenerated($tablenames)
    {
        foreach ($tablenames as $tablename) {
            $singular = Str::singular($tablename);
            $model = Str::ucfirst($singular);

            // TODO: Keep uncommenting the lines that get properly implemented

            // $this->assertFileExists(app_path("${model}.php"));

            // $this->assertFileExists(app_path("Http/Controllers/${model}Controller.php"));

            // $this->assertFileExists(database_path("factories/${model}Factory.php"));

            // $this->assertFileExists(app_path("Requests/${model}Request.php"));

            // $this->assertFileExists(base_path("tests/Feature/${model}ManagementTest.php"));

            // foreach (['index', 'show', 'create', 'edit', '_form'] as $view) {
            //     $this->assertFileExists(resource_path("views/$singular/$view.blade.php"));
            // }

            // $this->assertRouteHas("Route::resource('/$tablename, '${model}Controller')");
        }
    }

    /** @test */
    public function the_command_exists()
    {
        $this->artisan('autocrud:make')->assertExitCode(0);
    }

    /** @test */
    public function it_can_make_crud_files()
    {
        $this->artisan('autocrud:make');

        $this->assertFilesWereGenerated($this->tablenames);

        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
