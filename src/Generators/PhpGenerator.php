<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Stub\StubRenderer;

/**
 * Abstract class used by all the generator commands in this package.
 */
abstract class PhpGenerator
{
    /** @var Filesystem */
    protected $files;

    /**
     * Whether to force the generation of the file. If false, and the output
     * file already exists, no new file will be generated and an exception is
     * thrown. If true and the output file exists, the file is overwritten.
     *
     * @var bool
     */
    protected $force = false;

    /**
     * Whether to format the resulting PHP code.
     *
     * @var bool
     */
    protected $format = false;

    /**
     * Create a new generator, responsible for generating some PHP code.
     *
     * @param TableInformation $table
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * This is where subclasses can specify an initialization procedure. By
     * default this method does nothing.
     */
    protected function initialize()
    {
        //
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
        $this->force = $force;

        return $this;
    }

    /**
     * Set whether to format the generated PHP code according to php-cs-fixer
     * format rules.
     *
     * @param bool $force
     *
     * @return $this
     */
    public function setFormat(bool $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Generate the file this generator is responsible for.
     *
     * @return string
     */
    public function generate(): string
    {
        $code = StubRenderer::render(
            $this->readStub(),
            $this->replacements()
        );

        $code = $this->postProcess($code);

        return $code;
    }

    /**
     * Save the file this generator is responsible for.
     */
    public function save()
    {
        $filename = $this->filename();

        if ($this->force || ! $this->files->exists($filename)) {
            $this->ensureDirectory($filename);

            $this->files->put($filename, $this->generate());

            if ($this->format) {
                $this->formatCode();
            }
        }
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
     * Return the output filename where this file will be saved to. The returned
     * value must be the absolute path of the file. Use one of the laravel
     * `*_path` helper functions (e.g. `app_path`).
     *
     * @return string
     */
    abstract protected function filename(): string;

    /**
     * Tweak the final result from rendering the stub with the replacements.
     * Defaults to no-op.
     *
     * @param string $code
     *
     * @return string
     */
    protected function postProcess(string $code): string
    {
        return $code;
    }

    private function formatCode()
    {
        // We need to make sure that there is a php-cs-fixer accessible from the
        // current working directory. We do it by looking into vendor/bin/

        if ($this->files->exists('vendor/bin/php-cs-fixer')) {
            exec('vendor/bin/php-cs-fixer fix ' . $this->filename());
        }
    }

    public static function removeMethod(string $methodName, string $code): string
    {
        // TODO: Not thoroughly tested (just indirectly)

        $lines = explode("\n", $code);

        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], $methodName) !== false) {
                // Remove possible php doc blocks
                if (preg_match('/^\s*\/\*\*.*\*\/\s*$/', $lines[$i - 1])) {
                    $start = $i - 1;
                } elseif (preg_match('/\*\/\s*$/', $lines[$i - 1])) {
                    $start = $i - 1;

                    do {
                        $start--;
                    } while (preg_match('/^\s*\/\*\*/', $lines[$start]) === 0);
                } else {
                    $start = $i;
                }

                // Also remove the line before the method, if it is empty.
                if (trim($lines[$start - 1]) === '') {
                    $start--;
                }
            } elseif (isset($start) && $lines[$i] === '    }') {
                $end = $i;
                break;
            }
        }

        if (isset($start)) {
            for ($i = $start; $i <= $end; $i++) {
                unset($lines[$i]);
            }
        }

        return implode("\n", $lines);
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
}
