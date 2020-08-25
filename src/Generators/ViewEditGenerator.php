<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;

class ViewEditGenerator extends ViewCreateGenerator
{
    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return resource_path(
            'views/' . Str::snake(Str::plural($this->table->name())) . '/edit.blade.php'
        );
    }

    protected function verb()
    {
        return 'Edit';
    }

    protected function model()
    {
        return Str::camel(Str::singular($this->table->name()));
    }

    protected function attributes(string $column)
    {
        [$name, $value] = $this->nameAndValue($column);

        return "name=\"$name\" value=\"$value\"";
    }

    protected function textareaInput(string $column)
    {
        [$name, $value] = $this->nameAndValue($column);

        return "<textarea name=\"$name\">$value</textarea>";
    }

    private function nameAndValue($column)
    {
        $name = str_replace('_', '-', $column);

        $old = 'old(\'' . $name . '\')';
        $bound = '$' . $this->model() . '->' . $column;
        $value = "{{ $old ?? $bound }}";

        return [$name, $value];
    }
}
