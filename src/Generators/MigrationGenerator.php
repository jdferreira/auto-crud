<?php

namespace Ferreira\AutoCrud\Generators;

use Exception;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Generators\PhpGenerator;
use Ferreira\AutoCrud\Generators\MigrationGeneratorHelper as Helper;

class MigrationGenerator extends PhpGenerator
{
    /** @var bool */
    private $pivot;

    /** @var string */
    private $tablename;

    /** @var string[] */
    private $specs;

    /** @var string[] */
    private $schema;

    /** @var string[] */
    private $existing;

    /** @var null|string */
    private $dir;

    /** @var null|int */
    private $order;

    /** @var string[] */
    private $columnNames;

    /** @var string[][] */
    private $foreignReferences;

    public function __construct(Filesystem $files, $existing = [])
    {
        parent::__construct($files);

        $this->existing = $existing;
        $this->dir = null;
        $this->order = null;
        $this->columnNames = [];
        $this->foreignReferences = [];

        // 2% probability of this being a pivot table, if possible
        $this->pivot = count($existing) >= 2 && Helper::rand() <= 0.2;

        $this->schema = $this->buildSchema();
    }

    private function buildSchema()
    {
        $result = [];

        if (! $this->pivot) {
            $this->tablename = Str::plural(Helper::sqlName(1, 2));
        }

        $totalColumns = $this->pivot ? 2 : Helper::rand(2, 12);

        while (count($this->columnNames) < $totalColumns) {
            // 5% probability of this being a foreign key column
            $foreignKey = $this->pivot || (count($this->existing) > 0 && Helper::rand() <= 0.05);

            if ($foreignKey) {
                // Randomly select a previously existing table. Notice that we
                // do not want repeating references, so we remove the selected
                // element from the local copy of the array specifying the
                // existing tables
                [$foreignTable, $foreignId, $foreignType] = Arr::pull(
                    $this->existing,
                    Arr::random(array_keys($this->existing))
                );

                // 90% of the time, this column is named as expected from
                // laravel convention; 5% of the time, it's a `<random>_id`;
                // otherwise it's a random 1 or 2 letter word
                do {
                    $rand = Helper::rand();

                    if ($rand <= 0.9) {
                        $name = Str::singular($foreignTable) . '_id';
                    } elseif ($rand <= 0.95) {
                        $name = Helper::sqlName() . '_id';
                    } else {
                        $name = Helper::sqlName(Helper::rand() <= 0.1 ? 2 : 1);
                    }
                } while ($this->columnNameExists($name));

                $this->foreignReferences[] = [
                    $name,
                    $foreignId,
                    $foreignTable,
                ];

                $column = Helper::$idColumns[$foreignType];
                $type = 'simple';

                if ($this->pivot && count($this->foreignReferences) === 2) {
                    if (strcmp($this->foreignReferences[0][2], $this->foreignReferences[1][2]) < 0) {
                        $this->tablename = "{$this->foreignReferences[0][2]}_{$this->foreignReferences[1][2]}";
                    } else {
                        $this->tablename = "{$this->foreignReferences[1][2]}_{$this->foreignReferences[0][2]}";
                    }
                }
            } else {
                $column = Arr::random(array_keys(Helper::$columnTypes));
                $type = Helper::$columnTypes[$column];

                do {
                    // 10% probability of a compound name
                    $name = Helper::sqlName(Helper::rand() <= 0.1 ? 2 : 1);

                    // 50% of the integer columns have a plural name
                    if (in_array($column, Helper::$countColumns) && Helper::rand() <= 0.5) {
                        $name = Str::plural($name);
                    }
                } while ($this->columnNameExists($name));
            }

            if ($type === 'simple') {
                $method = "$column('$name')";
            } elseif ($type === 'char') {
                $method = "$column('$name', 100)";
            } elseif ($type === 'decimal') {
                $method = "$column('$name', 8, 2)";
            } elseif ($type === 'set') {
                $validValues = collect($this->randomSet())->map(function ($value) {
                    return "'$value'";
                });

                $set = $validValues->join(', ');

                $method = "$column('$name', [$set])";
            }

            // 20% probability of nullable
            if (! $this->pivot && random_int(1, 100) <= 20) {
                $method .= '->nullable()';
            }

            // 20% probability of unique, but not if boolean
            if (! $this->pivot && $column !== 'boolean' && random_int(1, 100) <= 20) {
                $method .= '->unique()';
            }

            // If not a foreign key, 10% probability of a default value
            if (! $foreignKey && random_int(1, 100) <= 10) {
                if (in_array($column, Helper::$dateLikeCollumns)) {
                    $method .= '->useCurrent()';
                } else {
                    if ($type === 'set') {
                        $default = $validValues->random();
                    } else {
                        $default = Helper::$defaultValues[$column];
                    }

                    $method .= "->default($default)";
                }
            }

            $result[] = "\$table->$method;";

            $this->columnNames[] = $name;
        }

        // 90% probability of timestamp
        if (random_int(1, 100) <= 90) {
            $result[] = '$table->timestamps();';
        }

        // 50% probability of soft deletes
        if (! $this->pivot && random_int(1, 100) <= 50) {
            $result[] = '$table->softDeletes();';
        }

        foreach ($this->foreignReferences as [
            $name,
            $foreignId,
            $foreignTable,
        ]) {
            $result = array_merge($result, [
                '',
                '$table',
                "    ->foreign('$name')",
                "    ->references('$foreignId')",
                "    ->on('$foreignTable');",
            ]);
        }

        // ID column. We compute this at the end because we need to know the
        // tablename, and that depends on the columns, if the table is a pivot
        // table.
        $column = Arr::random(array_keys(Helper::$idColumns));

        do {
            // 80% probability of being called 'id'
            // 10% of being called 'tablename_id'
            // 10% of a single word
            $rand = Helper::rand();

            if ($rand <= 0.8) {
                $name = 'id';
            } elseif ($rand <= 0.9) {
                $name = Str::singular($this->tablename) . '_id';
            } else {
                $name = Helper::sqlName();
            }
        } while ($this->columnNameExists($name));

        $result = array_merge(
            [
                "\$table->$column('$name');",
            ],
            $result
        );

        // Only non-pivot tables can be used as reference to other tables
        $this->specs = $this->pivot
            ? []
            : [$this->tablename, $name, $column];

        // TODO: Add columns `json`/`jsonb`, `set` and `year`.

        return $result;
    }

    private function columnNameExists($name): bool
    {
        return in_array($name, $this->columnNames);
    }

    private function tablenameStudly()
    {
        return Word::classPlural($this->tablename);
    }

    private function randomSet()
    {
        return Helper::words(random_int(3, 8));
    }

    protected function stub(): string
    {
        return 'migration.php.stub';
    }

    protected function replacements(): array
    {
        return [
            'tablenameStudly' => $this->tablenameStudly(),
            'tablename' => $this->tablename(),
            'schema' => $this->schema,
        ];
    }

    protected function filename(): string
    {
        if ($this->dir === null || $this->order === null) {
            throw new Exception('Call `setSaveDetails` first.');
        }

        $order = sprintf('%06d', $this->order);

        return implode(DIRECTORY_SEPARATOR, [
            $this->dir,
            date('Y_m_d') . "_{$order}_create_{$this->tablename}_table.php",
        ]);
    }

    public function setSaveDetails(string $dir, int $order)
    {
        $this->dir = $dir;
        $this->order = $order;
    }

    public function tablename()
    {
        return $this->tablename;
    }

    public function specs()
    {
        return $this->specs;
    }
}
