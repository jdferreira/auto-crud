<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;

class ControllerGenerator extends BaseGenerator
{
    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'controller.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        return 'Http/Controllers/' . $this->class() . '.php';
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        return [
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'modelSingular' => $this->modelSingular(),
            'modelPlural' => $this->modelPlural(),
        ];
    }

    private function class()
    {
        return Str::studly(Str::singular($this->table->name())) . 'Controller';
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

    protected function modelSingular()
    {
        return Str::camel(Str::singular($this->table->name()));
    }

    protected function modelPlural()
    {
        return Str::camel(Str::plural($this->table->name()));
    }
}
