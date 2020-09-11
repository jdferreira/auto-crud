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
            'factories/' . $this->modelClass() . 'Factory.php'
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
            'fullModel' => $this->fullModel(),
        ];
    }

    private function fakes(): array
    {
        $result = [];

        foreach ($this->table->columns() as $name) {
            if ($name === 'deleted_at') {
                continue;
            }

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
        $lines = explode("\n", $fake);

        $lines[0] = "'$name' => " . $lines[0];
        $lines[count($lines) - 1] .= ',';

        return $lines;
    }

    private function otherUses()
    {
        $result = [];

        foreach ($this->referencedTables as $table) {
            $classname = $this->modelNamespace() . '\\' . Str::studly(Str::singular($table));

            $result[] = "use $classname;";
        }

        return $result;
    }

    public function fullModel()
    {
        $result = [];

        foreach ($this->collectNullable() as $column) {
            if ($column === 'deleted_at') {
                continue;
            }

            $faker = app(ColumnFaker::class, [
                'table' => $this->table,
                'column' => $column,
                'forceRequired' => true,
            ]);

            $fake = $faker->fake();

            if ($fake === '') {
                continue;
            }

            $result = array_merge($result, $this->extendFake($fake, $column));
        }

        return $result;
    }

    private function collectNullable()
    {
        return collect($this->table->columns())->filter(function ($column) {
            return ! $this->table->required($column);
        })->all();
    }
}
