<?php

namespace Ferreira\AutoCrud;

use Illuminate\Support\Str;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class AccessorBuilder
{
    /**
     * @var TableInformation
     */
    private $table;

    /**
     * @var DatabaseInformation
     */
    private $db;

    public function __construct(TableInformation $table)
    {
        $this->table = $table;

        $this->db = app(DatabaseInformation::class);
    }

    public function label(string $column): string
    {
        if ($this->refersTo($column) !== null) {
            return Str::ucfirst(str_replace(
                '_',
                ' ',
                Str::endsWith($column, '_id') ? Str::replaceLast('_id', '', $column) : $column
            ));
        } else {
            return preg_replace(
                '/\b[iI]d$/',
                'ID',
                Str::ucfirst(str_replace('_', ' ', $column))
            );
        }
    }

    private function modelSingular()
    {
        return Str::camel(Str::singular($this->table->name()));
    }

    /**
     * Returns the table that the given column refers to. If the column does not
     * have a foreign key, return false.
     *
     * @param string $column
     *
     * @return null|string
     */
    private function refersTo(string $column)
    {
        if (($references = $this->table->reference($column)) !== null) {
            return $references[0];
        } else {
            return null;
        }
    }

    /**
     * Returns code that accesses (retrieves) the value of a column in a model.
     * If no model is given, the generated code generates the model as well, by
     * writing a PHP variable whose name is based on the table name.
     *
     * If the column refers to another table, the accessor retrieves the label
     * associated with the row on *that* table, rather than the raw identifier
     * of the model.
     *
     * Note that no conversion is done on the accessor (the generated code for
     * date columns access the Carbon instance, not a formatted string that is
     * derived from it etc.). If you want a string accessor consider using the
     * `viewAccessor`, which returns the code to be inserted in blade views.
     *
     * @param string $column
     * @param string $model
     *
     * @return string
     */
    public function simpleAccessor(string $column, string $model = null): string
    {
        $model = $model ?? '$' . $this->modelSingular();

        if (($foreignTable = $this->refersTo($column)) !== null) {
            $foreignLabelColumn = $this->db->table($foreignTable)->labelColumn();

            if ($foreignLabelColumn !== null) {
                $relation = Str::camel(Str::singular(
                    Str::endsWith($column, '_id')
                        ? substr($column, 0, -3)
                        : $foreignTable
                ));

                return "$model->$relation->$foreignLabelColumn";
            }
        }

        return "$model->$column";
    }

    public function simpleAccessorFormatted(string $column)
    {
        $accessor = $this->simpleAccessor($column);

        return static::formatAccessor($accessor, $this->table->type($column));
    }

    /**
     * Returns code that accesses (retrieves) the value of a column in a model
     * in such a way that it can be directly injected in a blade view in order
     * to write, in the view, the value of that column.
     *
     * If the column refers to another table, the accessor retrieves the label
     * associated with the row on *that* table, rather than the raw identifier
     * of the model.
     *
     * The values that are not strings are converted into strings meaning that
     * Carbon instances are formatted (columns whose type is datetime, date or
     * time are dealt appropriately etc.).
     *
     * Nullable columns also have special treatment because they can either be
     * `null`, in which case no string should be included in the view, or they
     * can contain a value, in which case the value must be dealt with respect
     * to the rules above.
     *
     * @param string $column
     *
     * @return string
     */
    public function viewAccessor(string $column): string
    {
        $simple = $this->simpleAccessor($column);

        if (($foreignTable = $this->refersTo($column)) !== null) {
            $foreignModel = Str::camel(Str::singular($foreignTable));

            $idAccessor = '$' . $this->modelSingular() . "->$column";

            $route = "{{ route('$foreignTable.show', ['$foreignModel' => $idAccessor]) }}";

            if ($this->db->table($foreignTable)->labelColumn() !== null) {
                return '<a href="' . $route . '">{{ ' . $simple . ' }}</a>';
            } else {
                $foreignLabel = Str::ucfirst(
                    str_replace('_', ' ', Str::singular($foreignTable))
                );

                return '<a href="' . $route . '">' . $foreignLabel . ' #{{ ' . $simple . ' }}</a>';
            }
        } else {
            $formatted = static::formatAccessor($simple, $this->table->type($column));

            if (! $this->table->required($column) && $formatted !== $simple) {
                if ($this->table->type($column) === Type::BOOLEAN) {
                    $formatted = "($formatted)";
                }

                $formatted = "$simple !== null ? $formatted : null";
            }

            return "{{ $formatted }}";
        }
    }

    private static function formatAccessor($accessor, $type)
    {
        switch ($type) {
            case Type::BOOLEAN:
                return "$accessor ? '&#10004;' : '&#10008;'";

            case Type::DATETIME:
                return "${accessor}->format('Y-m-d H:i:s')";

            case Type::DATE:
                return "${accessor}->format('Y-m-d')";

            case Type::TIME:
                return "${accessor}->format('H:i:s')";

            case Type::TEXT:
                return "\Illuminate\Support\Str::limit(${accessor}, 30)";

            default:
                return $accessor;
        }
    }
}
