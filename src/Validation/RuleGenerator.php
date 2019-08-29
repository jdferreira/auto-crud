<?php

namespace Ferreira\AutoCrud\Validation;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Connection;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class RuleGenerator
{
    /**
     * @var string
     */
    private $tablename;

    /**
     * @var Column
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

    public function __construct(string $tablename, Column $column)
    {
        $this->tablename = $tablename;
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
            $this->column->getNotnull() && $this->column->getDefault() === null
                ? 'required'
                : 'nullable'
        );
    }

    private function byColumnName()
    {
        $customs = [
            'email' => 'email:rfc',
            'uuid' => 'uuid',
        ];

        return $this->quote(Arr::get($customs, $this->column->getName()));
    }

    private function byColumnType()
    {
        static $map = [
            Type::BIGINT => 'integer',
            Type::INTEGER => 'integer',
            Type::SMALLINT => 'integer',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'date',
            Type::DATETIME_IMMUTABLE => 'date',
            Type::DATETIMETZ => 'date',
            Type::DATETIMETZ_IMMUTABLE => 'date',
            Type::DATE => 'date_format:Y-m-d',
            Type::DATE_IMMUTABLE => 'date_format:Y-m-d',
            Type::TIME => 'date_format:H:i:s',
            Type::TIME_IMMUTABLE => 'date_format:H:i:s',
            Type::FLOAT => 'numeric',
            Type::GUID => 'uuid',
        ];

        return $this->quote(Arr::get($map, $this->column->getType()->getName()));
    }

    private function decimalType()
    {
        if ($this->column->getType()->getName() === Type::DECIMAL) {
            $this->implodable = false;

            return $this->quote('regex:/^(?:\d+\.?|\d*\.\d+)$/');
        }
    }

    private function enumRules()
    {
        if (! $this->column->getType()->getName() === Type::STRING) {
            return;
        }

        if (($valid = $this->getEnumValid()) === null) {
            return;
        }

        $string = implode(',', $valid);

        if (strpos($string, '|') !== false) {
            $this->implodable = false;
        }

        return $this->quote('in:' . implode(',', $valid));
    }

    private function getEnumValid(): ?array
    {
        switch (app(Connection::class)->getDriverName()) {
            case 'mysql':
                return (new MySqlEnumChecker($this->tablename, $this->column))->valid();
            case 'sqlite':
                return (new SQLiteEnumChecker($this->tablename, $this->column))->valid();
            case 'pgsql':
                // TODO: We need to implement PostgresEnumChecker
                return (new PostgresEnumChecker($this->tablename, $this->column))->valid();
            case 'sqlsrv':
                // TODO: We need to implement SqlServerEnumChecker
                return (new SqlServerEnumChecker($this->tablename, $this->column))->valid();
            default:
                return null;
        }
    }

    private function foreignKeys()
    {
        /**
         * @var DatabaseInformation
         */
        $db = app(DatabaseInformation::class);

        $references = $db->foreignKeysReferences($this->tablename, $this->column->getName());

        if ($references === null) {
            return;
        }

        [$foreignTable, $foreignColumn] = $references;

        $rule =
            $foreignColumn === $this->column->getName()
            ? "exists:$foreignTable"
            : "exists:$foreignTable,$foreignColumn";

        return $this->quote($rule);
    }

    private function uniqueRules()
    {
        /**
         * @var DatabaseInformation
         */
        $db = app(DatabaseInformation::class);

        if (! $db->unique($this->tablename, $this->column->getName())) {
            return;
        }

        $this->implodable = false;

        $this->needsModel = true;

        return "Rule::unique('$this->tablename')->ignore(\$model)";
    }

    private function ignoreColumn()
    {
        return $this->column->getAutoincrement() || $this->isTimestamp();
    }

    private function isTimestamp(): bool
    {
        return
            in_array($this->column->getName(), ['created_at', 'updated_at', 'deleted_at']) &&
            Str::startsWith($this->column->getType()->getName(), 'datetime');
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
