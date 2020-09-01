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
     * @var string
     */
    private $column;

    /**
     * @var DatabaseInformation
     */
    private $db;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $accessor;

    public function __construct(TableInformation $table, string $column)
    {
        $this->table = $table;
        $this->column = $column;

        $this->db = app(DatabaseInformation::class);

        $this->build();
    }

    private function build()
    {
        if (($references = $this->table->reference($this->column)) !== null) {
            [$foreignTable, $foreignColumn] = $references;

            $label = Str::ucfirst(str_replace(
                '_',
                ' ',
                Str::endsWith($this->column, '_id')
                    ? Str::replaceLast('_id', '', $this->column)
                    : $this->column
            ));

            $modelMethod = substr($this->column, -3) === '_id'
                ? Str::camel(Str::singular(substr($this->column, 0, -3)))
                : Str::camel(Str::singular($foreignTable));
            $foreignLabelColumn = $this->db->table($foreignTable)->labelColumn();

            $modelName = '$' . $this->modelSingular();

            if ($foreignLabelColumn === null) {
                $accessor = '(' . ucwords(str_replace('_', ' ', Str::singular($foreignTable))) . ': ' . "$modelName->{$this->column}" . ')';
            } else {
                $type = $this->db->table($foreignTable)->type($foreignColumn);
                $required = $this->table->required($this->column);
                $raw = "$modelName->$modelMethod->$foreignLabelColumn";

                $accessor = $this->castToType($raw, $type);

                if (! $required) {
                    $accessor = "$modelName->{$this->column} ? $accessor : ''";
                }
            }

            $accessor = '<a href="' . static::route($foreignTable, "$modelName->{$this->column}") . '">' . $accessor . '</a>';
        } else {
            $label = Str::ucfirst(str_replace('_', ' ', $this->column));
            $label = preg_replace('/\bId\b/', 'ID', $label);

            $accessor = '{{ ' . $this->accessor($this->column) . ' }}';
        }

        $this->label = $label;
        $this->accessor = $accessor;
    }

    private function modelSingular()
    {
        return Str::camel(Str::singular($this->table->name()));
    }

    private function accessor()
    {
        $type = $this->table->type($this->column);
        $required = $this->table->required($this->column);
        $modelName = '$' . $this->modelSingular();

        return $this->processAccessor("$modelName->{$this->column}", $type, $required);
    }

    private static function processAccessor($raw, $type, $required)
    {
        $result = static::castToType($raw, $type);

        if (! $required) {
            if ($result === $raw) {
                $result = "$raw ?: ''";
            } else {
                $result = "$raw ? $result : ''";
            }
        }

        return $result;
    }

    private static function castToType($accessor, $type)
    {
        switch ($type) {
            case Type::BOOLEAN:
                return "$accessor ? '&#10004;' : ''";

            case Type::DATETIME:
                return "${accessor}->format('Y-m-d H:i:s')";

            case Type::DATE:
                return "${accessor}->format('Y-m-d')";

            case Type::TIME:
                return "${accessor}->format('H:i:s')";

            default:
                return $accessor;
        }
    }

    private static function route(string $model, string $idAccessor): string
    {
        return "{{ route('$model.show', ['$model' => $idAccessor]) }}";
    }
}
