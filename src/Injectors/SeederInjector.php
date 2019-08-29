<?php

namespace Ferreira\AutoCrud\Injectors;

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
     * Construct an injector to add calls to seeder files on the mian database
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
        $this->ensureMainSeederExists();

        $this->files->put(
            $filename = database_path('seeds/DatabaseSeeder.php'),
            $this->injectInto($this->files->get($filename))
        );
    }

    private function injectInto(string $code): string
    {
        $injection = $this->generateSeederCalls();

        $parts = preg_split('/(^    public function run\(\)\n\s+\{\n)(.*)(\n^    \})/sm', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (Str::endsWith($parts[2], '//')) {
            $parts[2] = substr($parts[2], 0, -2);
        }

        $parts[2] = rtrim($parts[2], " \t");

        return $parts[0] . $parts[1] . $parts[2] . $injection . $parts[3] . $parts[4];
    }

    private function generateSeederCalls(): string
    {
        $lines = [];

        foreach ($this->getSeederClasses() as $class) {
            $lines [] = "        \$this->call($class::class);";
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
            $result [] = Str::studly(Str::singular($table)) . 'Seeder';
        }

        foreach ($this->db->pivots() as $pivot) {
            [$fk1, $fk2] = $this->db->table($pivot)->foreignKeys();

            if (
                in_array($fk1->getForeignTableName(), $this->tables) &&
                in_array($fk2->getForeignTableName(), $this->tables)
            ) {
                $result [] = Str::studly(Str::singular($pivot)) . 'Seeder';
            }
        }

        return $result;
    }

    private function ensureMainSeederExists()
    {
        $filename = database_path('seeds/DatabaseSeeder.php');

        if (! $this->files->exists($filename)) {
            $this->files->put($filename, $this->files->get(__DIR__ . '/stubs/DatabaseSeeder.php'));
        }
    }
}
