<?php

namespace Tests;

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
            $migration = new MigrationGenerator($existing);

            if (in_array($migration->tablename(), $tablenames)) {
                continue;
            }

            $migration->save($this->dir, count($tablenames));

            if (count($specs = $migration->specs()) > 0) {
                $existing[] = $specs;
            }

            $tablenames[] = $migration->tablename();
        }
    }
}
