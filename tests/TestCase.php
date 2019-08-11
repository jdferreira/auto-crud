<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The instance that deals with file system stuff.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The directory path of the migrations for this test case.
     * If `null` no migrations will run before the tests in this class.
     *
     * @var null|string
     */
    protected $migrations;

    /**
     * Load this package's service provider.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return lasselehtinen\MyPackage\MyPackageServiceProvider
     */
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    /**
     * Setup the test environment by removing previously generated files
     * and running migrations, if any are necessary for this test class.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->files = app(Filesystem::class);

        $this->restartApplicationDirectoryTree();

        $this->runMigrations();
    }

    /**
     * Remove all previously generated files from the application tree.
     */
    protected function restartApplicationDirectoryTree()
    {
        $this->files->cleanDirectory(app_path());
        $this->files->cleanDirectory(database_path('factories'));
        $this->files->cleanDirectory(resource_path('views'));
        $this->files->deleteDirectory(base_path('tests'));
        $this->files->deleteDirectory(base_path('routes'));
    }

    /**
     * Clean up the testing environment before the next test.
     */
    public function tearDown(): void
    {
        $this->rollbackMigrations();

        parent::tearDown();
    }

    /**
     * Executes the migrations that live in the migrations path, if one has
     * been set for this class.
     */
    protected function runMigrations()
    {
        if ($this->migrations) {
            Artisan::call('migrate', [
                '--path' => $this->migrations,
                '--realpath' => true,
            ]);
        }
    }

    /**
     * Reverts the migrations executed with the `runMigrations` method.
     */
    protected function rollbackMigrations()
    {
        if ($this->migrations) {
            Artisan::call('migrate:reset', [
                '--path' => $this->migrations,
                '--realpath' => true,
            ]);
        }
    }
}
