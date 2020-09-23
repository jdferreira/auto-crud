<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Ferreira\AutoCrud\AccessorBuilder;
use Ferreira\AutoCrud\Database\TableInformation;

class ViewEditGenerator extends ViewCreateGenerator
{
    /**
     * @var AccessorBuilder
     */
    private $builder;

    public function initialize()
    {
        $this->builder = app(AccessorBuilder::class, [
            'table' => $this->table,
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
            'views/' . $this->table->name() . '/edit.blade.php'
        );
    }

    protected function verb()
    {
        return 'Edit';
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
        $label = Word::labelUpper($value);

        $model = Word::variableSingular($this->table->name());

        $name = Word::kebab($column);

        $selected = "{{ (old('$name') ?? $model->$column) === '$value' ? 'selected' : '' }}";

        return "<option value=\"$value\" $selected>$label</option>";
    }

    protected function foreignOptionItem(string $column, string $value, string $text)
    {
        $model = Word::variableSingular($this->table->name());

        $name = Word::kebab($column);

        // We use a double equal sign `==` on purpose. The `old` helper returns
        // a string, but the value on the model is very probably an integer.
        // As such we must test equality disregarding the type (yey PHP!)
        $selected = "{{ (old('$name') ?? $model->$column) == $value ? 'selected' : '' }}";

        return "<option value=\"{{ $value }}\" $selected>$text</option>";
    }

    private function value($column)
    {
        $old = 'old(\'' . Word::kebab($column) . '\')';

        $bound = $this->builder->simpleAccessor($column);

        if (Type::dateTimeFormat($this->table->type($column)) !== null) {
            $bound = $this->builder->formatAccessor($bound, $column);
        }

        return "$old ?? $bound";
    }
}
