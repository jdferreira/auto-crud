<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ViewCreateGenerator extends BaseGenerator
{
    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    protected function stub(): string
    {
        return 'view.form.php.stub';
    }

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return resource_path(
            'views/' . Str::snake(Str::plural($this->table->name())) . '/create.blade.php'
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
            'verb' => $this->verb(),
            'modelSingular' => $this->modelSingular(),
            'fields' => $this->fields(),
            'buttons' => $this->buttons(),
        ];
    }

    protected function verb()
    {
        return 'New';
    }

    protected function modelSingular()
    {
        return str_replace('_', ' ', Str::singular($this->table->name()));
    }

    public function fields()
    {
        $result = [];

        foreach ($this->table->columns() as $column) {
            $field = $this->field($column);

            if ($field === null) {
                continue;
            }

            $result = array_merge($result, Arr::wrap($field));
        }

        return $result;
    }

    private function field(string $column)
    {
        if ($this->table->primaryKey() === $column) {
            // TODO: This should also test that the column is automatically
            // incremented, or that it has a default value
            return;
        } elseif (in_array($column, ['created_at', 'updated_at', 'deleted_at'])) {
            // Timestamp columns
            return;
        }

        $for = str_replace('_', '-', $column);

        $label = Str::ucfirst(str_replace('_', ' ', $column));

        $input = collect($this->input($column))->map(function ($arg) {
            return "    $arg";
        })->all();

        return array_merge(
            [
                '<div>',
                '    <label for="' . $for . '">' . $label . '</label>',
            ],
            $input,
            [
                '</div>',
            ]
        );
    }

    protected function attributes(string $column)
    {
        return 'name="' . str_replace('_', '-', $column) . '"';
    }

    private function input(string $column)
    {
        $attrs = $this->attributes($column);

        $type = $this->table->type($column);

        if ($type === Type::ENUM) {
            $values = collect($this->table->getEnumValid($column))->map(function ($value) {
                $label = Str::ucfirst(str_replace('_', ' ', $value));

                return "    <option value=\"$value\">$label</option>";
            })->all();

            return array_merge(
                [
                    '<select ' . $attrs . '>',
                ],
                $values,
                [
                    '</select>',
                ]
            );
        }

        if ($column === 'email' && $type === Type::STRING) {
            return '<input ' . $attrs . ' type="email">';
        }

        switch ($type) {
            case Type::STRING:
            case Type::INTEGER:
            case Type::DECIMAL:
                return '<input ' . $attrs . ' type="text">';

            case Type::BOOLEAN:
                return '<input ' . $attrs . ' type="checkbox">';

            case Type::DATE:
                return '<input ' . $attrs . ' type="date">';

            case Type::TIME:
                return '<input ' . $attrs . ' type="time">';

            case Type::DATETIME:
                return '<input ' . $attrs . ' type="datetime">'; // TODO: This has been deprecated in HTML!

            case Type::BINARY:
                return '<input ' . $attrs . ' type="file">'; // TODO: This must be converted to binary on the controller!

            case Type::TEXT:
                // This is its own method because it needs to be overwritten in
                // the `ViewEditGenerator` class
                return $this->textareaInput($column);

            default:
                break;
        }
    }

    protected function textareaInput(string $column)
    {
        return '<textarea name="' . str_replace('_', '-', $column) . '"></textarea>';
    }

    public function buttons()
    {
        return '<button type="submit">Submit</button>';
    }
}
