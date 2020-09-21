<?php

namespace Tests;

use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\Stub\StubRenderer;
use Tests\MigrationGeneratorHelper as Helper;

class MigrationGenerator
{
    /** @var bool */
    private $pivot;

    /** @var string */
    private $tablename;

    /** @var string[] */
    private $existing;

    /** @var string[] */
    private $specs;

    /** @var string */
    private $code;

    public function __construct($existing = [])
    {
        $this->existing = $existing;

        // 2% probability of this being a pivot table, if possible
        $this->pivot = count($existing) >= 2 && Helper::rand() <= 0.2;

        $this->specs = [];

        $schema = $this->schema();

        $this->code = StubRenderer::render(
            file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'migration.php.stub'),
            [
                'tablenameStudly' => $this->tablenameStudly(),
                'tablename' => $this->tablename(),
                'schema' => $schema,
            ]
        );
    }

    public function code()
    {
        return $this->code;
    }

    public function save(string $dir, $order)
    {
        $order = sprintf('%06d', $order);

        $filename = implode(DIRECTORY_SEPARATOR, [
            $dir,
            date('Y_m_d') . "_{$order}_create_{$this->tablename}_table.php",
        ]);

        file_put_contents($filename, $this->code);
    }

    private function tablenameStudly()
    {
        return Word::classPlural($this->tablename);
    }

    private function schema()
    {
        $schema = [];

        if (! $this->pivot) {
            $this->tablename = Str::plural(Helper::sqlName(1, 2));
        }

        $foreignReferences = [];
        $namesUsed = [];
        $totalColumns = $this->pivot ? 2 : Helper::rand(2, 12);

        while (count($namesUsed) < $totalColumns) {
            // 5% probability of this being a foreign key column
            if ($this->pivot || (count($this->existing) > 0 && Helper::rand() <= 0.05)) {
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
                } while (in_array($name, $namesUsed));

                $foreignReferences[] = [
                    $name,
                    $foreignId,
                    $foreignTable,
                ];

                if ($this->pivot && count($foreignReferences) === 2) {
                    if (strcmp($foreignReferences[0][2], $foreignReferences[1][2]) < 0) {
                        $this->tablename = "{$foreignReferences[0][2]}_{$foreignReferences[1][2]}";
                    } else {
                        $this->tablename = "{$foreignReferences[1][2]}_{$foreignReferences[0][2]}";
                    }
                }

                $column = Helper::$idColumns[$foreignType];
                $type = 'simple';

                $foreignKey = true;
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
                } while (in_array($name, $namesUsed));

                $foreignKey = false;
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

            // 20% probability of unique
            if (! $this->pivot && random_int(1, 100) <= 20) {
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

            $schema[] = "\$table->$method;";

            $namesUsed[] = $name;
        }

        // 90% probability of timestamp
        if (random_int(1, 100) <= 90) {
            $schema[] = '$table->timestamps();';
        }

        // 50% probability of soft deletes
        if (! $this->pivot && random_int(1, 100) <= 50) {
            $schema[] = '$table->softDeletes();';
        }

        foreach ($foreignReferences as [
            $name,
            $foreignId,
            $foreignTable,
        ]) {
            $schema = array_merge($schema, [
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

        // Only non-pivot tables can be used as reference to other tables
        if (! $this->pivot) {
            $this->specs = [$this->tablename, $name, $column];
        }

        $schema = array_merge(
            [
                "\$table->$column('$name');",
            ],
            $schema
        );

        // TODO: Add columns `json`/`jsonb`, `set` and `year`.

        return $schema;
        // TODO: Ensure no duplicate names
    }

    private function randomSet()
    {
        return Helper::words(random_int(3, 8));
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
