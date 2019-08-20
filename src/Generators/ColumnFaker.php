<?php

namespace Ferreira\AutoCrud\Generators;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use Doctrine\DBAL\Schema\Column;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class ColumnFaker
{
    /**
     * @var string
     */
    private $tablename;

    /**
     * @var Column
     */
    private $column;

    public function __construct(string $tablename, Column $column)
    {
        $this->tablename = $tablename;
        $this->column = $column;
    }

    /**
     * Computes a PHP code string that can be used to fake values for the given
     * column.
     *
     * @return string
     */
    public function fake(): string
    {
        $fakers = [
            'ignoredColumns',
            'foreignKeysFaker',
            'knownFakerFormatters',
            'default',
        ];

        $fake = null;

        foreach ($fakers as $method) {
            if (($result = $this->$method()) !== null) {
                $fake = $result;
                break;
            }
        }

        if ($fake === '') {
            return '';
        } elseif ($fake === null) {
            return 'null';
        }

        if (Str::startsWith($fake, 'function')) {
            return $fake;
        }

        if ($modifiers = $this->getModifiers()) {
            $modifiers = implode('->', $modifiers);

            return "\$faker->$modifiers->$fake";
        } else {
            return "\$faker->$fake";
        }
    }

    private function getModifiers(): array
    {
        $modifiers = [];

        if ($this->isNullable()) {
            $modifiers[] = 'optional(0.1)';
        }

        if ($this->isUnique()) {
            $modifiers[] = 'unique()';
        }

        return $modifiers;
    }

    private function isNullable(): bool
    {
        return ! $this->column->getNotnull();
    }

    private function isUnique(): bool
    {
        /**
         * @var DatabaseInformation
         */
        $db = app(DatabaseInformation::class);

        return $db->unique($this->tablename, $this->column->getName()) ?? false;
    }

    private function default()
    {
        static $map = [
            Type::BIGINT => 'numberBetween(10000, 100000)',
            Type::INTEGER => 'numberBetween(0, 10000)',
            Type::SMALLINT => 'numberBetween(0, 1000)',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'dateTimeBetween(\'-10 years\', \'now\')',
            Type::DATETIME_IMMUTABLE => 'dateTimeBetween(\'-10 years\', \'now\')',
            Type::DATETIMETZ => 'dateTimeBetween(\'-10 years\', \'now\', new DateTimeZone(\'UTC\'))',
            Type::DATETIMETZ_IMMUTABLE => 'dateTimeBetween(\'-10 years\', \'now\', new DateTimeZone(\'UTC\'))',
            Type::DATE => 'date',
            Type::DATE_IMMUTABLE => 'date',
            Type::TIME => 'time',
            Type::TIME_IMMUTABLE => 'time',
            Type::FLOAT => 'randomFloat',
            Type::DECIMAL => 'numerify(\'###.##\')',
            Type::STRING => 'sentence',
            Type::TEXT => 'text',
            Type::GUID => 'uuid',
            Type::BINARY => 'passthrough(random_bytes(1024))',
            Type::BLOB => 'passthrough(random_bytes(1024))',
        ];

        return Arr::get($map, $this->column->getType()->getName());
    }

    private function foreignKeysFaker()
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

        $model = Str::studly(Str::singular($foreignTable));

        return implode("\n", [
            'function () {',
            "    return factory($model::class)->create()->$foreignColumn;",
            '}',
        ]);
    }

    private function knownFakerFormatters()
    {
        $name = $this->column->getName();

        $potential = [
            $name,
            Str::camel($name),
            'random' . Str::ucfirst($name),
            'random' . Str::studly($name),
        ];

        foreach ($potential as $name) {
            if ($this->fakerHasFormatter($name)) {
                return $name;
            }
        }
    }

    private function fakerHasFormatter($name)
    {
        try {
            app(\Faker\Generator::class)->getFormatter($name);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    private function ignoredColumns()
    {
        if ($this->column->getAutoincrement() || $this->isTimestamp()) {
            return '';
        }
    }

    private function isTimestamp(): bool
    {
        return
            in_array($this->column->getName(), ['created_at', 'updated_at']) &&
            Str::startsWith($this->column->getType()->getName(), 'datetime');
    }
}
