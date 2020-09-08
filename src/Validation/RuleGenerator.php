<?php

namespace Ferreira\AutoCrud\Validation;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\Database\TableInformation;

class RuleGenerator
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
     * @var bool
     */
    private $implodable = true;

    /**
     * @var bool
     */
    private $needsModel = false;

    public function __construct(TableInformation $table, string $column)
    {
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Computes a PHP code string that can be used to fake values for the given
     * column.
     *
     * @return null|string[]
     */
    public function generate(): ?array
    {
        if ($this->ignoreColumn()) {
            return null;
        }

        return $this->implode(
            $this->makeRules()
        );
    }

    public function makeRules(): array
    {
        $methods = [
            'nullable',
            'byColumnName',
            'byColumnType',
            'decimalType',
            'enumRules',
            'foreignKeys',
            'uniqueRules',
        ];

        $rules = [];

        foreach ($methods as $method) {
            if (($result = $this->$method()) !== null) {
                $rules = array_merge($rules, Arr::wrap($result));
            }
        }

        return $rules;
    }

    private function implode(array $rules)
    {
        if ($this->implodable) {
            // TODO: I know that this is probably incorrect, specially since we
            // need to take care of the quotes, pipe characters and commas
            // (right?). However, we can mark rules that don't serialize neatly
            // as not implodable, which means the next line _is_ correct. In any
            // case, this needs tests!

            $imploded = implode('|', array_map(function ($rule) {
                return substr($rule, 1, -1);
            }, $rules));

            return ["'$imploded'"];
        }

        $rules = array_map(function ($value) {
            return "    $value,";
        }, $rules);

        // I wish PHP would allow
        //   $rules = ['['] + $rules + [']']
        // but alas, it does not! :(

        array_unshift($rules, '[');
        array_push($rules, ']');

        return $rules;
    }

    private function nullable()
    {
        return $this->quote(
            $this->required()
                ? 'required'
                : 'nullable'
        );
    }

    private function required()
    {
        // TODO: This should be moved into the TableInformation somehow;
        // many other classes use this definition of required!
        return $this->table->required($this->column)
            && $this->table->type($this->column) !== Type::BOOLEAN;
    }

    private function byColumnName()
    {
        $customs = [
            'email' => 'email:rfc',
            'uuid' => 'uuid',
        ];

        return $this->quote(Arr::get($customs, $this->column));
    }

    private function byColumnType()
    {
        static $map = [
            Type::INTEGER => 'integer',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'date',
            Type::DATE => 'date_format:Y-m-d',
            Type::TIME => 'date_format:H:i:s',
        ];

        return $this->quote(Arr::get($map, $this->table->type($this->column)));
    }

    private function decimalType()
    {
        if ($this->table->type($this->column) === Type::DECIMAL) {
            $this->implodable = false;

            return $this->quote('regex:/^(?:\d+\.?|\d*\.\d+)$/');
        }
    }

    private function enumRules()
    {
        if (($valid = $this->table->getEnumValid($this->column)) === null) {
            return;
        }

        $string = implode(',', $valid);

        if (strpos($string, '|') !== false) {
            $this->implodable = false;
        }

        return $this->quote('in:' . implode(',', $valid));
    }

    private function foreignKeys()
    {
        if (($references = $this->table->reference($this->column)) === null) {
            return;
        }

        [$foreignTable, $foreignColumn] = $references;

        $rule =
            $foreignColumn === $this->column
            ? "exists:$foreignTable"
            : "exists:$foreignTable,$foreignColumn";

        return $this->quote($rule);
    }

    private function uniqueRules()
    {
        if (! $this->table->unique($this->column)) {
            return;
        }

        $this->implodable = false;

        $this->needsModel = true;

        $tablename = $this->table->name();

        return "Rule::unique('$tablename')->ignore(\$model)";
    }

    private function ignoreColumn()
    {
        $toIgnore = [$this->table->primaryKey(), 'created_at', 'updated_at', 'deleted_at'];

        return in_array($this->column, $toIgnore);
    }

    public function needsModel()
    {
        return $this->needsModel;
    }

    private function quote(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }

        if (strpos($string, "'") === false) {
            if (Str::endsWith($string, '\\')) {
                // Double the amount of backslashes in the end of the string
                $end = rtrim($string, '\\');
                $string .= str_repeat('\\', strlen($string) - strlen($end));
            }

            return "'$string'";
        } else {
            $string = str_replace('\\', '\\\\', $string);
            $string = str_replace('$', '\\$', $string);
            $string = str_replace('"', '\\"', $string);

            return "\"$string\"";
        }
    }
}
