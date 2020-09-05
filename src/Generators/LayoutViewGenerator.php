<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Stub\StubRenderer;

class LayoutViewGenerator
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new generator.
     */
    public function __construct()
    {
        $this->files = app(Filesystem::class);
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
            []
        );
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
            $directory . DIRECTORY_SEPARATOR . 'layout.php.stub'
        );
    }

    /**
     * Save the file this generator is responsible for.
     */
    public function save()
    {
        $filename = base_path('resources/views/layouts/app.blade.php');

        if (! $this->files->exists($filename)) {
            $this->ensureDirectory($filename);

            $this->files->put($filename, $this->generate());
        }
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
}
