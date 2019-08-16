<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Stub\StubRenderer;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

/**
 * Abstract class used by all the generator commands in this package.
 */
abstract class BaseGenerator
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \Ferreira\AutoCrud\Database\DatabaseInformation
     */
    protected $db;

    /**
     * @var null|\Ferreira\AutoCrud\Database\TableInformation
     */
    protected $table;

    /**
     * @var string
     */
    protected $dir;

    /**
     * Whether to force the generation of the file. If false, and the output
     * file already exists, no new file will be generated and an exception is
     * thrown. If true and the output file exists, the file is overwritten.
     *
     * @var bool
     */
    protected $force;

    /**
     * Create a new generator, resposible for generating the CRUD files for a certain table.
     *
     * @param string $table The table name
     */
    public function __construct(string $table)
    {
        $this->files = app(Filesystem::class);
        $this->db = app(DatabaseInformation::class);

        $this->table = new TableInformation($table);

        // Default values. Use the setters to change them
        $this->dir = '';
        $this->force = false;
    }

    /**
     * Set the directory where models are saved, relative to the base path of
     * the laravel application (usually `app/`). If not provided, this defaults
     * to the empty string. Forward slashes are translated to the systems's
     * directory separator character before assignment.
     *
     * @param string $dir
     *
     * @return $this
     */
    public function setModelDirectory(string $dir): self
    {
        $this->dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);

        return $this;
    }

    /**
     * Set whether to force the generation of the file, even if it already exists.
     *
     * @param bool $force
     *
     * @return $this
     */
    public function setForce(bool $force): self
    {
        // TODO: Missing tests

        $this->force = $force;

        return $this;
    }

    /**
     * Generate the file this generator is responsible for.
     *
     * @return string
     */
    public function generate(): string
    {
        return StubRenderer::render(
            $this->readStub(),
            $this->replacements()
        );
    }

    /**
     * Save the file this generator is responsible for.
     */
    public function save()
    {
        $filename = app_path($this->filename());

        if ($this->force || ! $this->files->exists($filename)) {
            $this->ensureDirectory($filename);

            $this->files->put($filename, $this->generate());
        }
    }

    /**
     * For a given array of arguments and an expected array of arguments, return a string
     * that represents the arguments (appropriately comma-separated) such that default
     * values are not included in the final returned value, to emulates human code.
     *
     * @param string[] $args
     * @param string[] $defaultValues
     *
     * @return string
     */
    public static function removeDefaults(array $args, array $defaultValues): string
    {
        $argsCount = count($args);
        $defaultsCount = count($defaultValues);

        while (
            $argsCount > 0 && $defaultsCount > 0 &&
            $args[$argsCount - 1] === $defaultValues[$defaultsCount - 1]
        ) {
            $argsCount--;
            $defaultsCount--;
        }

        return implode(', ', array_slice($args, 0, $argsCount));
    }

    /**
     * Read the stub that will be used as a basis for the file generated.
     *
     * @return string
     */
    protected function readStub(): string
    {
        $directory = config('autocrud.stubs') ?: __DIR__ . DIRECTORY_SEPARATOR . 'stubs';

        return $this->files->get(
            $directory . DIRECTORY_SEPARATOR . $this->stub()
        );
    }

    /**
     * Ensure that the directory where this file will be saved exists.
     */
    protected function ensureDirectory(string $filename)
    {
        $dir = $this->files->dirname($filename);

        if (! $this->files->exists($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }
    }

    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    abstract protected function stub(): string;

    /**
     * Return the stub replacements used with the stub.
     *
     * @return array
     */
    abstract protected function replacements(): array;

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    abstract protected function filename(): string;
}
