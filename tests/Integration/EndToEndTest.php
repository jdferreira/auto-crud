<?php

namespace Tests\Integration;

use Exception;
use Tests\TestCase;
use Tests\MigrationSetGenerator;
use Illuminate\Support\Facades\Artisan;

class EndToEndTest extends TestCase
{
    /** @var \Illuminate\Foundation\Application */
    protected $app;

    const RUNNING = __DIR__ . DIRECTORY_SEPARATOR . '.running.phpunit';

    /** @var bool */
    protected $rerun;

    public function setUp(): void
    {
        parent::setUp();

        $this->rerun = $this->files->exists(static::RUNNING);
    }

    public function tearDown(): void
    {
        // If the test succeeded, clean all the repository of the changes that
        // were made; otherwise, keep the migrations as they are, and clean
        // everything else, allowing quickly rerunning the failing test.

        if ($this->getStatus() === 0) {
            if ($this->rerun) {
                $this->files->delete(static::RUNNING);
            }

            shell_exec('git clean -fd');
            shell_exec('git checkout -f');
        } else {
            $this->files->put(static::RUNNING, '');

            // Let the developer look at the generated files
            fwrite(STDOUT, 'Press ENTER when you are done inspecting the generated files' . PHP_EOL);
            fgets(STDIN);

            // Remove all but the migrations
            shell_exec('git add database/migrations');
            shell_exec('git clean -fd');
            shell_exec('git checkout -- $(git status --porcelain | \grep -P "^ M" | cut -c4-)');
        }
    }

    /**
     * @test
     * @group end-to-end
     */
    public function it_generates_a_working_application()
    {
        $this->setupEmptyLaravelProject();

        $this->generateAndRunMigrations();

        $this->runAutocrud();

        $this->assertPhpunitSucceeds();
    }

    private function initializeEmptyLaravelProject()
    {
        shell_exec('composer create-project --prefer-dist laravel/laravel .');

        shell_exec('git init');
        shell_exec('git add .');
        shell_exec('git commit -m "Empty laravel app"');

        // // TODO: Change composer to load "Ferreira\\Autocrud\\" from "../src/"
        // // TODO: Change phpunit to use an memory SQLite database
        // // TODO: And then commit these changes
        // shell_exec('git add .');
        // shell_exec('git commit -m "Prepare for autocrud"');
    }

    private function setupEmptyLaravelProject()
    {
        $emptyLaravel = env('EMPTY_LARAVEL_PROJECT', 'empty-laravel');

        if ($this->files->isFile($emptyLaravel)) {
            throw new Exception("Cannot use file $emptyLaravel as an empty laravel project");
        }

        if (! $this->files->isDirectory($emptyLaravel)) {
            mkdir($emptyLaravel);
        }

        chdir($emptyLaravel);

        if (count($this->files->allFiles('.')) === 0) {
            $this->initializeEmptyLaravelProject();
        }

        $this->app->setBasePath('.');
    }

    private function generateAndRunMigrations()
    {
        $dir = implode(DIRECTORY_SEPARATOR, [
            'database',
            'migrations',
        ]);

        if (! $this->rerun) {
            $this->files->cleanDirectory($dir);

            (new MigrationSetGenerator($dir))->save();
        }

        Artisan::call('migrate', [
            '--path' => $dir,
            '--realpath' => true,
        ]);
    }

    private function runAutocrud()
    {
        Artisan::call('autocrud:make');
    }

    private function assertPhpunitSucceeds()
    {
        $specs = [
            1 => ['file', 'phpunit.stdout.txt', 'w'],
            2 => ['file', 'phpunit.stderr.txt', 'w'],
        ];

        $process = proc_open('vendor/bin/phpunit --colors=never', $specs, $pipes);

        if (is_resource($process)) {
            // Close inner pipes before calling closing the process to avoid
            // deadlock

            fclose($pipes[1]);
            fclose($pipes[2]);
            $phpunitStatus = proc_close($process);
        }

        $this->assertEquals(0, $phpunitStatus);
    }
}
