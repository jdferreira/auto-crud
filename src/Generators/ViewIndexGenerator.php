<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;

class ViewIndexGenerator extends BaseGenerator
{
    /**
     * Return the filename containing the stub this generator is based on.
     *
     * @return string
     */
    protected function stub(): string
    {
        return 'view.index.php.stub';
    }

    /**
     * Return the output filename where this file will be saved to.
     *
     * @return string
     */
    protected function filename(): string
    {
        return resource_path(
            'views/' . Str::snake(Str::plural($this->table->name())) . '/index.blade.php'
        );
    }

    /**
     * Return the stub replacements used with the stub.
     *
     * @return array
     */
    protected function replacements(): array
    {
        [$labels, $values] = $this->labelsAndValues();

        return [
            'modelPluralCapitalized' => $this->modelPluralCapitalized(),
            'modelPlural' => $this->modelPlural(),
            'modelSingular' => $this->modelSingular(),
            'labels' => $labels,
            'values' => $values,
        ];
    }

    protected function modelSingular()
    {
        return Str::camel(Str::singular($this->table->name()));
    }

    protected function modelPlural()
    {
        return Str::camel(Str::plural($this->table->name()));
    }

    protected function modelPluralCapitalized()
    {
        return ucwords(Str::snake(Str::plural($this->table->name()), ' '));
    }

    protected function visibleColumns()
    {
        return collect($this->table->columns())->filter(function ($column) {
            if (in_array($column, ['created_at', 'updated_at', 'deleted_at'])) {
                return false;
            }

            $type = $this->table->column($column)->getType()->getName();

            if ($type === Type::BINARY || $type === Type::BLOB) {
                return false;
            }

            return true;
        });
    }

    protected function labelsAndValues()
    {
        // TODO: Take into account the columns that are part of relationships.
        // Note that we want only one-to-one and belongs-to relationship

        $labels = [];
        $values = [];

        $table = $this->table->name();

        foreach ($this->visibleColumns() as $column) {
            // If we have a relationship based on this column, we need to show
            // the name of the row, not the actual column value; additionally,
            // the name is wrapped into an HTML link to the show view of that
            // model.
            if (($references = $this->db->foreignKeysReferences($table, $column)) !== null) {
                [$foreignTable, $foreignColumn] = $references;

                $label = Str::ucfirst(str_replace(
                    '_',
                    ' ',
                    Str::endsWith($column, '_id')
                        ? Str::replaceLast('_id', '', $column)
                        : $column
                ));

                $modelMethod = substr($column, -3) === '_id'
                    ? Str::camel(Str::singular(substr($column, 0, -3)))
                    : Str::camel(Str::singular($foreignTable));
                $foreignLabelColumn = $this->db->table($foreignTable)->labelColumn();

                $modelName = '$' . $this->modelSingular();

                if ($foreignLabelColumn === null) {
                    $value = '(' . ucwords(str_replace('_', ' ', Str::singular($foreignTable))) . ': ' . "$modelName->$column" . ')';
                } else {
                    $type = $this->db->table($foreignTable)->type($foreignColumn)->getName();
                    $required = $this->table->required($column);
                    $raw = "$modelName->$modelMethod->$foreignLabelColumn";

                    $value = $this->castToType($raw, $type);
                    if (! $required) {
                        $value = "$modelName->$column ? $value : ''";
                    }
                }

                $value = '<a href="' . $this->route($foreignTable, "$modelName->$column") . '">' . $value . '</a>';
            } else {
                $label = Str::ucfirst(str_replace('_', ' ', $column));
                $label = preg_replace('/\bId\b/', 'ID', $label);

                $value = '{{ ' . $this->accessor($column) . ' }}';
            }

            $labels[] = "<th>$label</th>";
            $values[] = "<td>$value</td>";
        }

        return [$labels, $values];
    }

    private function route(string $model, string $idAccessor): string
    {
        return "{{ route('$model.show', ['$model' => $idAccessor]) }}";
    }

    protected function accessor($column)
    {
        $type = $this->table->column($column)->getType()->getName();
        $required = $this->table->required($column);
        $modelName = '$' . $this->modelSingular();

        return $this->processAccessor("$modelName->$column", $type, $required);
    }

    protected function processAccessor($raw, $type, $required)
    {
        $result = $this->castToType($raw, $type);

        if (! $required) {
            if ($result === $raw) {
                $result = "$raw ?: ''";
            } else {
                $result = "$raw ? $result : ''";
            }
        }

        return $result;
    }

    private function castToType($accessor, $type)
    {
        if ($type === Type::BOOLEAN) {
            return "$accessor ? '&#10004;' : ''";
        } elseif (
            $type === Type::DATETIME ||
            $type === Type::DATETIME_IMMUTABLE ||
            $type === Type::DATETIMETZ ||
            $type === Type::DATETIMETZ_IMMUTABLE
        ) {
            return "${accessor}->format('Y-m-d H:i:s')";
        } elseif (
            $type === Type::DATE ||
            $type === Type::DATE_IMMUTABLE
        ) {
            return "${accessor}->format('Y-m-d')";
        } elseif (
            $type === Type::TIME ||
            $type === Type::TIME_IMMUTABLE
        ) {
            return "${accessor}->format('H:i:s')";
        } else {
            return $accessor;
        }
    }
}
