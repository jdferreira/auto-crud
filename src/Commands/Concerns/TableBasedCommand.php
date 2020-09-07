<?php

namespace Ferreira\AutoCrud\Commands\Concerns;

use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

trait TableBasedCommand
{
    protected function handleMultipleTables($class)
    {
        $tablenames = $this->tablenames();

        foreach ($tablenames as $name) {
            $table = $this->laravel->make(TableInformation::class, [
                'name' => $name,
            ]);

            $this->laravel->make($class, [
                'table' => $table,
            ])->save();
        }
    }

    /**
     * Return the name of the tables that were given as parameters to this command.
     *
     * @return string[]
     */
    protected function tablenames(): array
    {
        $requested = $this->option('table');

        return count($requested) > 0
            ? $requested
            : $this->laravel->make(DatabaseInformation::class)->tablenames(false);
    }
}
