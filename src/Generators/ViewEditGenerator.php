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
        $attrs = parent::attributes($column);

        $value = $this->value($column);

        return "$attrs value=\"{{ $value }}\"";
    }

    protected function textareaInput(string $column)
    {
        $attrs = parent::attributes($column);

        $value = $this->value($column);

        return "<textarea $attrs>{{ $value }}</textarea>";
    }

    protected function checkboxInput(string $column)
    {
        $attrs = parent::attributes($column);

        $value = $this->value($column);
        $checked = "{{ ($value ) ? 'checked' : '' }}";

        return [
            "<input $attrs $checked type=\"checkbox\" value=\"1\">",
            "<input $attrs type=\"hidden\" value=\"0\">",
        ];
    }

    private function value($column)
    {
        $old = 'old(\'' . str_replace('_', '-', $column) . '\')';
        $bound = '$' . $this->model() . '->' . $column;

        return "$old ?? $bound";
    }
}
