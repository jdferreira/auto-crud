<?php

namespace Ferreira\AutoCrud\Injectors;

use Ferreira\AutoCrud\Word;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class SeederInjector
{
    /**
     * @var DatabaseInformation
     */
    private $db;

    /**
     * @var string[]
     */
    private $tables;

    /**
     * @var Filesystem
     */
    private $files;

    /**
     * Construct an injector to add calls to seeder files on the main database
     * seeder class.
     *
     * @param string[] $tables
     */
    public function __construct(array $tables)
    {
        $this->db = app(DatabaseInformation::class);
        $this->files = app(Filesystem::class);

        $this->tables = $tables;
    }

    public function inject()
    {
        $this->ensureFileExists(
            $filename = database_path('seeds/DatabaseSeeder.php'),
            __DIR__ . '/stubs/DatabaseSeeder.php'
        );

        $this->files->put(
            $filename,
            $this->injectInto($this->files->get($filename))
        );
    }

    private function injectInto(string $code): string
    {
        $injection = $this->generateSeederCalls();

        $parts = preg_split('/(^    public function run\(\)\n\s+\{\n)(.*?)(\n^    \})/sm', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parts[2] = preg_replace('/\s*\/\/$/s', '', $parts[2], -1, $count);

        if ($count > 0) {
            // Remove existing new lines from the previous part
            $parts[1] = rtrim($parts[1], "\n");
        } else {
            $injection = "\n$injection";
        }

        return $parts[0] . $parts[1] . $parts[2] . $injection . $parts[3] . $parts[4];
    }

    private function generateSeederCalls(): string
    {
        $lines = ['', '        // Seeder code injected by autocrud.'];

        foreach ($this->getSeederClasses() as $class) {
            $lines[] = "        \$this->call($class::class);";
        }

        return implode("\n", $lines);
    }

    private function getSeederClasses(): array
    {
        // We will seed the provided tables respecting the order of seeding:
        // tables with foreign keys are seeded after their supporting tables.
        // Note: one-to-one and one-to-many relationship work regardless of
        // order, because the factories create new models when a foreign key is
        // necessary; however, pivot tables MUST be seeded after their
        // supporting tables. As such, we make sure pivot tables are seeded
        // last. Also, pivot tables are not given in the constructor: we want to
        // seed them if we are seeding the two supporting tables.

        $result = [];

        foreach ($this->tables as $table) {
            $result[] = Word::class($table) . 'Seeder';
        }

        foreach ($this->db->pivots() as $pivot) {
            [$fk1, $fk2] = array_values($this->db->table($pivot)->allReferences());

            if (in_array($fk1[0], $this->tables) && in_array($fk2[0], $this->tables)) {
                $result[] = Word::class($pivot) . 'Seeder';
            }
        }

        return $result;
    }

    private function ensureFileExists($filename, $copyFrom)
    {
        if (! $this->files->exists($filename)) {
            $this->files->put($filename, $this->files->get($copyFrom));
        }
    }
}
