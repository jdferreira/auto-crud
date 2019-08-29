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
        $makeArguments = ['--table' => ['users'], '--dir' => 'Models'] + $makeArguments;
        $expectedOptions = ['table' => ['users'], 'dir' => 'Models'] + $expectedOptions;

        $command = app(Kernel::class)->all()[$name];

        $this->artisan('autocrud:make', $makeArguments);

        $this->assertArraySubset($expectedOptions, $command->options());
    }

    /**
     * Assert that the expected files were generated.
     */
    private function assertFilesWereGenerated()
    {
        $tablenames = [
            'users', 'avatars', 'products', 'roles', 'sales',
        ];

        foreach ($tablenames as $tablename) {
            $singular = Str::singular($tablename);
            $model = Str::ucfirst($singular);

            $this->assertFileExists(app_path("${model}.php"));

            $this->assertFileExists(app_path("Http/Controllers/${model}Controller.php"));

            $this->assertFileExists(database_path("factories/${model}Factory.php"));

            $this->assertFileExists(app_path("Requests/${model}Request.php"));

            $this->assertFileExists(base_path("tests/Feature/${model}ManagementTest.php"));

            foreach (['index', 'show', 'create', 'edit', '_form'] as $view) {
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

        $this->markTestIncomplete(
            'Keep moving the lines below this to above it ' .
                'and when the time comes, remove this line altogether!'
        );

        $this->assertCommandIsCalled('autocrud:view');
        $this->assertCommandIsCalled('autocrud:test');
        $this->assertCommandIsCalled('autocrud:route');
    }

    /** @test */
    public function it_sends_the_correct_options_to_the_inner_commands()
    {
        $this->assertOptionsPassedToInner('autocrud:model');
        $this->assertOptionsPassedToInner('autocrud:controller');
        $this->assertOptionsPassedToInner('autocrud:factory');
        $this->assertOptionsPassedToInner('autocrud:seeder');
        $this->assertOptionsPassedToInner('autocrud:request');

        $this->markTestIncomplete(
            'Keep adding equivalent assertions for the other tables!'
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
