<?php

namespace Ferreira\AutoCrud;

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
        return Word::labelUpper($column, $this->refersTo($column) !== null);
    }

    private function modelSingular()
    {
        return Word::variableSingular($this->table->name());
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
        $model = $model ?? $this->modelSingular();

        if (($foreignTable = $this->refersTo($column)) !== null) {
            $foreignLabelColumn = $this->db->table($foreignTable)->labelColumn();

            if ($foreignLabelColumn !== null) {
                $relation = Word::method($column);

                return "$model->$relation->$foreignLabelColumn";
            }
        }

        return "$model->$column";
    }

    public function simpleAccessorFormatted(string $column, string $model = null): string
    {
        $accessor = $this->simpleAccessor($column, $model);

        return $this->formatAccessor($accessor, $column);
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
    public function viewAccessor(string $column, string $model = null): string
    {
        $simple = $this->simpleAccessor($column, $model);

        $model = $model ?? $this->modelSingular();

        if (($foreignTable = $this->refersTo($column)) !== null) {
            $routeParameter = Word::snakeSingular($foreignTable);

            $idAccessor = "$model->$column";

            $route = "{{ route('$foreignTable.show', ['$routeParameter' => $idAccessor]) }}";

            if ($this->db->table($foreignTable)->labelColumn() !== null) {
                $linkText = "{{ $simple }}";
            } else {
                $linkText = Word::labelUpperSingular($foreignTable, true) . " #{{ $simple }}";
            }

            return "<a href=\"$route\">$linkText</a>";
        } else {
            $formatted = $this->formatAccessor($simple, $column);

            return "{{ $formatted }}";
        }
    }

    /**
     * Returns a string of code used to format the value obtained with the given
     * accessor based on the column's specification, namely its type and whether
     * it is nullable.
     *
     * @param string $accessor
     * @param string $column
     * @param bool $view
     *
     * @return string
     */
    public function formatAccessor(string $accessor, string $column): string
    {
        $type = $this->table->type($column);
        $required = $this->table->required($column);

        if ($type === Type::BOOLEAN) {
            return $required
                ? "$accessor ? '&#10004;' : '&#10008;'"
                : "$accessor !== null ? ($accessor ? '&#10004;' : '&#10008;') : null";
        } elseif (($format = Type::dateTimeFormat($type)) !== null) {
            return $required
                ? "${accessor}->format($format)"
                : "$accessor !== null ? ${accessor}->format($format) : null";
        } elseif ($type === Type::TEXT) {
            return "\Illuminate\Support\Str::limit(${accessor}, 30)";
        } else {
            return $accessor;
        }
    }
}
