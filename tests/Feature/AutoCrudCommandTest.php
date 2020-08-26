<?php

namespace Tests\Features;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Contracts\Console\Kernel;

class AutoCrudCommandTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Assert that the command with the given name is called when we call
     * `autocrud:make`.
     *
     * @param string $name The name of the inner command
     */
    private function assertCommandIsCalled(string $name)
    {
        $kernel = app(Kernel::class);

        $class = get_class($kernel->all()[$name]);

        // Replace the command with a spy, so that we can later assert that its
        // `handle` method has been called once.
        $kernel->registerCommand(
            $command = Mockery::spy($class . '[handle]')
        );

        $this->artisan('autocrud:make');

        $command->shouldHaveReceived('handle')->once();
    }

    /**
     * Assert that the options.
     *
     * @param string $name
     * @param array $makeArguments
     * @param array $expectedOptions
     */
    private function assertOptionsPassedToInner(string $name, array $makeArguments = [], array $expectedOptions = [])
    {
        $makeArguments = ['--table' => ['users']] + $makeArguments;
        $expectedOptions = ['table' => ['users']] + $expectedOptions;

        $command = app(Kernel::class)->all()[$name];

        $this->artisan('autocrud:make', $makeArguments);

        $this->assertArrayHasSubset($expectedOptions, $command->options());
    }

    /**
     * Assert that the expected files were generated.
     */
    private function assertFilesWereGenerated()
    {
        $tablenames = [
            'users',
            'avatars',
            'products',
            'roles',
            'sales',
            'payment_methods',
        ];

        foreach ($tablenames as $tablename) {
            $singular = Str::singular($tablename);
            $model = Str::ucfirst($singular);

            $this->assertFileExists(app_path("${model}.php"));

            $this->assertFileExists(app_path("Http/Controllers/${model}Controller.php"));

            $this->assertFileExists(database_path("factories/${model}Factory.php"));

            $this->assertFileExists(app_path("Requests/${model}Request.php"));

            $this->assertFileExists(base_path("tests/Feature/${model}ManagementTest.php"));

            foreach (['index', 'show', 'create', 'edit'] as $view) {
                $this->assertFileExists(resource_path("views/$singular/$view.blade.php"));
            }

            $this->assertRouteHas("Route::resource('/$tablename, '${model}Controller')");
        }
    }

    /** @test */
    public function it_calls_the_inner_commands()
    {
        $this->assertCommandIsCalled('autocrud:model');
        $this->assertCommandIsCalled('autocrud:controller');
        $this->assertCommandIsCalled('autocrud:factory');
        $this->assertCommandIsCalled('autocrud:seeder');
        $this->assertCommandIsCalled('autocrud:request');
        $this->assertCommandIsCalled('autocrud:route');
        $this->assertCommandIsCalled('autocrud:view');

        $this->markTestIncomplete(
            'Keep moving the lines below this to above it ' .
                'and when the time comes, remove this line altogether!'
        );

        $this->assertCommandIsCalled('autocrud:test');
    }

    /** @test */
    public function it_sends_the_correct_options_to_the_inner_commands()
    {
        $this->assertOptionsPassedToInner('autocrud:model', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:controller', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:factory', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:seeder', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:request', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:route', ['--skip-api' => true], ['skip-api' => true]);
        $this->assertOptionsPassedToInner('autocrud:view', ['--dir' => 'Models'], ['dir' => 'Models']);
        $this->assertOptionsPassedToInner('autocrud:view', ['--skip-api' => true], []);

        $this->markTestIncomplete(
            'Keep adding equivalent assertions for the other commands!'
        );
    }

    /** @test */
    public function it_can_make_crud_files()
    {
        $this->markTestIncomplete(
            'The full set of features needed to pass this test have not been implemented yet.'
        );

        $this->artisan('autocrud:make');

        $this->assertFilesWereGenerated();
    }

    /** @test */
    public function it_fails_on_non_existing_tables()
    {
        $command = $this->artisan('autocrud:make', [
            '--table' => ['non_existing_table'],
        ]);

        $command
            ->expectsOutput('Table non_existing_table does not exist.')
            ->assertExitCode(1);
    }
}
