<?php

namespace Ferreira\AutoCrud\Generators;

class MigrationSetGenerator
{
    /** @var string */
    private $dir;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    public function save()
    {
        $totalTables = random_int(5, 10);
        $tablenames = [];
        $existing = [];

        while (count($tablenames) < $totalTables) {
            $migration = app(MigrationGenerator::class, [
                'existing' => $existing,
            ]);

            if (in_array($migration->tablename(), $tablenames)) {
                continue;
            }

            $migration->setSaveDetails($this->dir, count($tablenames));
            $migration->save();

            if (count($specs = $migration->specs()) > 0) {
                $existing[] = $specs;
            }

            $tablenames[] = $migration->tablename();
        }
    }
}
