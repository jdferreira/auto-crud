<?php

namespace Tests\Integration;

use Exception;
use Tests\TestCase;
use Illuminate\Support\Str;
use Tests\MigrationSetGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class EndToEndTest extends TestCase
{
    private function hasGit(): bool
    {
        exec('command -v git', $output, $exitStatus);

        return $exitStatus === 0;
    }

    private function mkdir(): string
    {
        $dir = sys_get_temp_dir();

        while (true) {
            $basename = md5(mt_rand());

            if (mkdir($path = $dir . DIRECTORY_SEPARATOR . $basename, 0755)) {
                break;
            }
        }

        return $path;
    }

    private function initializeEmptyLaravelProject()
    {
        shell_exec('composer create-project --prefer-dist laravel/laravel .');

        if ($this->hasGit()) {
            shell_exec('git init');
            shell_exec('git add .');
            shell_exec('git commit -m "Empty laravel app"');

            // // TODO: Change composer to load "Ferreira\\Autocrud\\" from "../src/"
            // // TODO: Change phpunit to use an memory SQLite database
            // // TODO: And then commit these changes
            // shell_exec('git add .');
            // shell_exec('git commit -m "Prepare for autocrud"');
        }
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

        $this->files->cleanDirectory($dir);

        (new MigrationSetGenerator($dir))->save();

        Artisan::call('migrate', [
            '--path' => $dir,
            '--realpath' => true,
        ]);
    }

    private function runAutocrud()
    {
        Artisan::call('autocrud:make');
    }

    /** @test */
    public function it_generates_a_working_application()
    {
        $this->setupEmptyLaravelProject();

        $this->generateAndRunMigrations();

        $this->runAutocrud();

        // $this->assertPhpunitSucceeds();
    }

    public function tearDown(): void
    {
        if ($this->hasGit()) {
            // shell_exec('git clean -fd');
            // shell_exec('git checkout -f');
        }
    }
}
