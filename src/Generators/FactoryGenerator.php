<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;

class FactoryGenerator extends BaseGenerator
{
    /** @var string[] */
    private $referencedTables = [];

    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    protected function stub(): string
    {
        return 'factory.php.stub';
    }

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return database_path(
            'factories/' . Str::studly(Str::singular($this->table->name())) . 'Factory.php'
        );
    }

    /**
     * Return the stub replacements used with the stub.
     *
     * @return array
     */
    protected function replacements(): array
    {
        return [
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'fakes' => $this->fakes(),
            'otherUses' => $this->otherUses(),
        ];
    }

    private function modelNamespace(): string
    {
        if ($this->dir === '') {
            return 'App';
        } else {
            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }
    }

    private function modelClass()
    {
        return Str::studly(Str::singular($this->table->name()));
    }

    private function fakes(): array
    {
        $result = [];

        foreach ($this->table->columns() as $name) {
            $faker = app(ColumnFaker::class, [
                'table' => $this->table,
                'column' => $name,
            ]);

            $fake = $faker->fake();

            /** @var ColumnFaker $faker */
            if (($referencedTable = $faker->referencedTable()) !== null) {
                if (! in_array($referencedTable, $this->referencedTables)) {
                    $this->referencedTables[] = $referencedTable;
                }
            }

            if ($fake === '') {
                continue;
            }

            $result = array_merge($result, $this->extendFake($fake, $name));
        }

        return $result;
    }

    private function extendFake($fake, $name)
    {
        if (strpos($fake, "\n") !== false) {
            $lines = explode("\n", $fake);
        } else {
            $lines = [$fake];
        }

        $lines[0] = "'$name' => " . $lines[0];
        $lines[count($lines) - 1] .= ',';

        return $lines;
    }

    private function otherUses()
    {
        $result = [];

        foreach ($this->referencedTables as $table) {
            $namespace = $this->namespace();
            $class = Str::singular(Str::ucfirst(Str::camel($table)));

            $result[] = "use $namespace\\$class;";
        }

        return $result;
    }

    private function namespace()
    {
        if ($this->dir === '') {
            return 'App';
        } else {
            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }
    }
}
