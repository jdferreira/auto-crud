<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\AccessorBuilder;
use Ferreira\AutoCrud\Database\TableInformation;

class ViewEditGenerator extends ViewCreateGenerator
{
    /**
     * @var AccessorBuilder
     */
    private $builder;

    public function __construct(TableInformation $table)
    {
        parent::__construct($table);

        $this->builder = app(AccessorBuilder::class, [
            'table' => $table,
        ]);
    }

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

    protected function regularInput(string $column, string $inputType, string $attrs)
    {
        $value = $this->value($column);

        $attrs = "$attrs value=\"{{ $value }}\"";

        return "<input $attrs type=\"$inputType\">";
    }

    protected function textareaInput(string $column, string $attrs)
    {
        $value = $this->value($column);

        return "<textarea $attrs>{{ $value }}</textarea>";
    }

    protected function checkboxInput(string $column, string $attrs)
    {
        $value = $this->value($column);
        $checked = "{{ ($value) ? 'checked' : '' }}";

        return [
            "<input $attrs $checked type=\"checkbox\" value=\"1\">",
            "<input $attrs type=\"hidden\" value=\"0\">",
        ];
    }

    protected function optionItem(string $column, string $value)
    {
        $label = Str::ucfirst(str_replace('_', ' ', $value));

        $model = $this->model();

        $selected = "{{ (old('$column') ?? \$$model->$column) === '$value' ? 'selected' : '' }}";

        return "<option value=\"$value\" $selected>$label</option>";
    }

    private function value($column)
    {
        $old = 'old(\'' . str_replace('_', '-', $column) . '\')';

        $bound = $this->builder->simpleAccessor($column);

        if (Type::dateTimeFormat($this->table->type($column)) !== null) {
            $bound = $this->builder->formatAccessor($bound, $column);
        }

        return "$old ?? $bound";
    }
}
