<?php

namespace Tests;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Constraint\LogicalNot;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;

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
     * The DatabaseInformation instance describing the migrations.
     *
     * @var \Ferreira\AutoCrud\Database\DatabaseInformation
     */
    protected $db;

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

            // We need to re-bind the DatabaseInformation singleton because the
            // instance bound by the service provider was created before the
            // migrations ran. This is not usual for the use case of the package
            // where the instance will be requested after the migrations have
            // run. Also, different test cases may have different migrations,
            // and so we must recompute all database information anyway.
            $this->app->singleton(DatabaseInformation::class);

            $this->db = app(DatabaseInformation::class);
        }
    }

    /**
     * Reverts the migrations executed with the `runMigrations` method.
     */
    protected function rollbackMigrations()
    {
        if ($this->migrations) {
            $this->db = null;

            Artisan::call('migrate:reset', [
                '--path' => $this->migrations,
                '--realpath' => true,
            ]);
        }
    }

    /**
     * Asserts that the elements in the array are exactly the ones expected,
     * without missing or extra ones. In practice, this asserts that the two arrays
     * contain the same elements, disregarding order.
     *
     * @param array $expected
     * @param array $actual
     * @param string $message
     */
    protected function assertSetsEqual(array $expected, array $actual, string $message = '')
    {
        static::assertThat(
            $actual,
            new SetsEqualConstraint($expected),
            $message
        );
    }

    /**
     * Assert that an exception of the provided class is thrown
     * when running the code of the given callable.
     *
     * @param string $class
     * @param callable $test
     * @param string $message
     */
    protected function assertException(string $class, callable $test, string $message = '')
    {
        try {
            $test();
            $exception = null;
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertThat(
            $exception,
            new ExceptionConstraint($class),
            $message
        );
    }

    /**
     * Asserts that the given excerpt is found within the larger piece of code.
     * Notice that the common indentation of the lines is disregarded, so that
     * your source code can contain indentation to keep formatting consistent.
     *
     * @param string $excerpt
     * @param string $code
     * @param string $message
     */
    protected static function assertCodeContains(string $excerpt, string $code, string $message = '')
    {
        static::assertThat(
            $code,
            new CodeContainsConstraint($excerpt),
            $message
        );
    }

    /**
     * Asserts that the given excerpt is not found within the larger piece of code.
     * Notice that the common indentation of the lines is disregarded, so that
     * your source code can contain indentation to keep formatting consistent.
     *
     * @param string $excerpt
     * @param string $code
     * @param string $message
     */
    protected function assertCodeNotContains(string $code, string $content, string $message = '')
    {
        static::assertThat(
            $content,
            new LogicalNot(new CodeContainsConstraint($code)),
            $message
        );
    }

    /**
     * This method is here because PHPUnit 8
     * [deprecated](https://github.com/sebastianbergmann/phpunit/issues/3494)
     * the `assertArraySubset` method.
     *
     * This simplified version of that method asserts that a given associative
     * array contains another one. In other words, it is equivalent to checking
     * that an array contains all the keys of a second array, and that the
     * values associated with those keys are equal. Equality is the strict
     * version. Order is not checked.
     *
     * @param array $subset
     * @param array $array
     */
    protected function assertArrayHasSubset(array $subset, array $array)
    {
        static::assertThat($array, new ArrayHasSubset($subset));
    }
}
