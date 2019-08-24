<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;

class SeederGenerator extends BaseGenerator
{
    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'seeder.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        return database_path(
            'seeds/' . Str::studly(Str::singular($this->table->name())) . 'Seeder.php'
        );
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        return [
            'useModel' => $this->useModel(),
            'modelClass' => $this->modelClass(),
            'useSeeder' => $this->useSeeder(),
            'seed' => $this->seed(),
        ];
    }

    private function useModel(): string
    {
        if ($this->table->isPivot()) {
            return '';
        }

        if ($this->dir === '') {
            $namespace = 'App';
        } else {
            $namespace = 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }

        return "use $namespace\\" . $this->modelClass() . ';';
    }

    private function modelClass()
    {
        return Str::studly(Str::singular($this->table->name()));
    }

    private function useSeeder()
    {
        return $this->table->isPivot() ? 'use Ferreira\AutoCrud\PivotSeeder;' : '';
    }

    private function seed()
    {
        return $this->table->isPivot()
            ? 'app(PivotSeeder::class)->seed(\'' . $this->table->name() . '\');'
            : 'factory(' . $this->modelClass() . '::class, 50)->create();';
    }
}