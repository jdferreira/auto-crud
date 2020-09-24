<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Ferreira\AutoCrud\Database\ManyToMany;

class ViewCreateGenerator extends TableBasedGenerator
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
            'views/' . $this->table->name() . '/create.blade.php'
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
        return Word::labelSingular($this->table->name());
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

        $result = array_merge(
            $result,
            collect($this->db->manyToMany($this->table->name()))
                ->flatMap(function (ManyToMany $relationship) {
                    return $this->fieldGroup(
                        Word::kebab($relationship->foreignTwo),
                        Word::labelUpper($relationship->foreignTwo),
                        $this->manyToManyField($relationship)
                    );
                })
                ->all()
        );

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

        return $this->fieldGroup(
            Word::kebab($column),
            Word::labelUpper($column),
            $this->input($column)
        );
    }

    private function fieldGroup(string $for, string $label, $input)
    {
        return array_merge(
            [
                '<div>',
                '    <label for="' . $for . '">' . $label . '</label>',
            ],
            array_map(function ($arg) {
                return "    $arg";
            }, Arr::wrap($input)),
            [
                '</div>',
            ]
        );
    }

    protected function attributes(string $column)
    {
        $name = Word::kebab($column);

        $required = $this->required($column) ? ' required' : '';

        return "name=\"$name\"$required";
    }

    private function required(string $column)
    {
        return $this->table->required($column)
            && $this->table->type($column) !== Type::BOOLEAN;
    }

    private function input(string $column)
    {
        $attrs = $this->attributes($column);

        $type = $this->table->type($column);

        if (($reference = $this->table->reference($column)) !== null) {
            return $this->foreignInput($column, $attrs, $reference[0]);
        }

        if ($type === Type::ENUM) {
            return $this->selectInput($column, $attrs);
        }

        if ($column === 'email' && $type === Type::STRING) {
            return $this->regularInput($column, 'email', $attrs);
        }

        switch ($type) {
            case Type::STRING:
            case Type::INTEGER:
            case Type::DECIMAL:
                return $this->regularInput($column, 'text', $attrs);

            case Type::BOOLEAN:
                return $this->checkboxInput($column, $attrs);

            case Type::DATE:
                return $this->regularInput($column, 'date', $attrs);

            case Type::TIME:
                return $this->regularInput($column, 'time', $attrs);

            case Type::DATETIME:
                // TODO: This has been deprecated in HTML! Do we need to adapt?
                return $this->regularInput($column, 'datetime', $attrs);

            case Type::TEXT:
                return $this->textareaInput($column, $attrs);

            default:
                break;
        }
    }

    protected function regularInput(string $column, string $inputType, string $attrs)
    {
        if ($this->table->hasDefault($column)) {
            $default = $this->table->default($column);

            $timeColumn = in_array($this->table->type($column), [
                Type::DATE,
                Type::DATETIME,
                Type::TIME,
            ]);

            if ($timeColumn && $default === 'CURRENT_TIMESTAMP') {
                $default = '{{ now() }}';
            }

            $default = " value=\"$default\"";
        } else {
            $default = '';
        }

        return "<input $attrs type=\"$inputType\"$default>";
    }

    protected function textareaInput(string $column, string $attrs)
    {
        if ($this->table->hasDefault($column)) {
            $default = $this->table->default($column);
        } else {
            $default = '';
        }

        return "<textarea $attrs>$default</textarea>";
    }

    protected function checkboxInput(string $column, string $attrs)
    {
        if ($this->table->hasDefault($column)) {
            $default = $this->table->default($column) ? ' checked' : '';
        } else {
            $default = '';
        }

        return [
            "<input $attrs type=\"checkbox\" value=\"1\"$default>",
            "<input $attrs type=\"hidden\" value=\"0\">",
        ];
    }

    protected function selectInput(string $column, string $attrs)
    {
        $values = collect($this->table->getEnumValid($column))->map(function ($value) use ($column) {
            $option = $this->optionItem($column, $value);

            return "    $option";
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

    protected function optionItem(string $column, string $value)
    {
        $label = Word::labelUpper($value);

        $selected = $this->table->default($column) === $value ? ' selected' : '';

        return "<option value=\"$value\"$selected>$label</option>";
    }

    protected function foreignInput(string $column, string $attrs, string $foreignTable)
    {
        $modelClass = Word::class($foreignTable);
        $model = Word::variableSingular($foreignTable);
        $primaryKey = $this->db->table($foreignTable)->primaryKey();
        $labelColumn = $this->db->table($foreignTable)->labelColumn();

        $text = $labelColumn !== null
            ? "{{ $model->$labelColumn }}"
            : Word::labelUpperSingular($foreignTable) . " #{{ $model->$primaryKey }}";

        $item = $this->foreignOptionItem($column, "$model->$primaryKey", $text);

        $namespace = $this->modelNamespace();

        return [
            '<select ' . $attrs . '>',
            "    @foreach (\\$namespace\\$modelClass::all() as $model)",
            "        $item",
            '    @endforeach',
            '</select>',
        ];
    }

    protected function foreignOptionItem(string $column, string $value, string $text)
    {
        return "<option value=\"{{ $value }}\">$text</option>";
    }

    private function manyToManyField(ManyToMany $relationship)
    {
        $foreignTable = $relationship->foreignTwo;

        $name = Word::kebab($foreignTable);
        $foreignClass = '\\' . $this->modelNamespace() . '\\' . Word::class($foreignTable);
        $model = Word::variableSingular($foreignTable);
        $primaryKey = $this->db->table($foreignTable)->primaryKey();
        $labelColumn = $this->db->table($foreignTable)->labelColumn();

        if ($labelColumn !== null) {
            $text = "{{ $model->$labelColumn }}";
        } else {
            $word = Word::labelUpperSingular($foreignTable);
            $text = "$word #{{ $model->$primaryKey }}";
        }

        return [
            "<select name=\"$name\" multiple>",
            "    @foreach ($foreignClass::all() as $model)",
            "        <option value=\"{{ $model->$primaryKey }}\">$text</option>",
            '    @endforeach',
            '</select>',
        ];
    }

    public function buttons()
    {
        return '<button type="submit">Submit</button>';
    }
}
