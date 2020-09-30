<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Ferreira\AutoCrud\VersionChecker;
use Ferreira\AutoCrud\Database\OneToOne;
use Ferreira\AutoCrud\Database\OneToMany;
use Ferreira\AutoCrud\Database\ManyToMany;
use Ferreira\AutoCrud\Database\OneToOneOrMany;

class ModelGenerator extends TableBasedGenerator
{
    /**
     * Get the stub filename.
     */
    protected function stub(): string
    {
        return 'model.php.stub';
    }

    /**
     * Get the output filename. The returned value is relative to the
     * application's base directory (usually `app/`).
     */
    protected function filename(): string
    {
        $filename = $this->modelClass() . '.php';

        if ($this->dir === '') {
            $parts = [$filename];
        } else {
            $parts = explode(DIRECTORY_SEPARATOR, $this->dir);

            $parts[] = $filename;
        }

        return app_path(implode(DIRECTORY_SEPARATOR, $parts));
    }

    /**
     * Get the replacements to use with the stub, based on these generator options.
     */
    protected function replacements(): array
    {
        $result = [
            'namespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'importSoftDeletesTrait' => $this->importSoftDeletes(),
            'disableTimestamps' => $this->disableTimestamps(),
            'useSoftDeletesTrait' => $this->useSoftDeletes(),
            'customPrimaryKey' => $this->primaryKey(),
            'casts' => $this->casts(),
            'fillable' => $this->fillable(),
            'hidden' => $this->hidden(),
            'relationships' => $this->relationships(),
            'path' => $this->path(),
        ];

        if (App::make(VersionChecker::class)->after('8.0.0')) {
            $result['importHasFactory'] = 'use Illuminate\Database\Eloquent\Factories\HasFactory;';
            $result['useHasFactory'] = 'use HasFactory;';
        }

        return $result;
    }

    protected function importSoftDeletes()
    {
        return $this->table->softDeletes()
            ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;'
            : '';
    }

    protected function disableTimestamps()
    {
        return ! $this->table->has('created_at') || ! $this->table->has('updated_at')
            ? 'public $timestamps = false;'
            : '';
    }

    protected function useSoftDeletes()
    {
        return $this->table->softDeletes()
            ? 'use SoftDeletes;'
            : '';
    }

    protected function primaryKey(): array
    {
        $pk = $this->table->primaryKey();

        if ($pk === 'id') {
            return [];
        }

        if (! is_array($pk)) {
            return [
                '/**',
                ' * The primary key associated with the table.',
                ' *',
                ' * @var string',
                ' */',
                "protected \$primaryKey = '$pk';", // We assume that the key does not have an apostrophe
            ];
        } else {
            // There's a lot that needs to be added in this case.
            // TODO: Add me later

            return [];
        }
    }

    protected function casts(): array
    {
        static $map = [
            Type::INTEGER => 'integer',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'datetime',
            Type::DATE => 'datetime:Y-m-d',
            Type::DECIMAL => 'decimal:2',
        ];

        // Laravel does does not have a 'time' model cast; as such, we use the
        // raw string. To make everything work out, we must validate in the
        // requests that the given values are in an expected time format

        $casts = [];

        foreach ($this->table->columns() as $name) {
            if (in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            if ($name === $this->table->primaryKey()) {
                continue;
            }

            $type = $this->table->type($name);

            if (($cast = Arr::get($map, $type)) !== null) {
                $casts[] = "    '$name' => '$cast',";
            }
        }

        if (count($casts) > 0) {
            return array_merge(
                [
                    '/**',
                    ' * The attributes that should be cast to native types.',
                    ' *',
                    ' * @var array',
                    ' */',
                    'protected $casts = [',
                ],
                $casts,
                [
                    '];',
                ]
            );
        } else {
            return [];
        }
    }

    protected function fillable()
    {
        $fillable = collect($this->table->columns())
            ->map(function ($column) {
                if (
                    in_array($column, ['created_at', 'updated_at', 'deleted_at'])
                    || $column === $this->table->primaryKey()
                ) {
                    return;
                }

                return "    '$column',";
            })
            ->filter()
            ->all();

        return array_merge(
            [
                '/**',
                ' * The attributes that are mass assignable.',
                ' *',
                ' * @var array',
                ' */',
                'protected $fillable = [',
            ],
            $fillable,
            [
                '];',
            ]
        );
    }

    protected function hidden()
    {
        if ($this->table->softDeletes()) {
            return 'protected $hidden = [\'deleted_at\'];';
        } else {
            return '';
        }
    }

    protected function relationships()
    {
        $table = $this->table->name();

        $result = [];

        $relationships = $this->db->relationshipsFor($table);

        foreach ($relationships as $relation) {
            if ($relation instanceof OneToOne) {
                $method = $table === $relation->foreignTable ? 'hasOne' : 'belongsTo';
            } elseif ($relation instanceof OneToMany) {
                $method = $table === $relation->foreignTable ? 'hasMany' : 'belongsTo';
            } elseif ($relation instanceof ManyToMany) {
                $method = 'belongsToMany';
            }

            $result = array_merge($result, [''], $this->$method($relation));
        }

        return $result;
    }

    protected function hasOne(OneToOne $relation): array
    {
        return $this->hasOneOrMany($relation);
    }

    protected function hasMany(OneToMany $relation): array
    {
        return $this->hasOneOrMany($relation);
    }

    protected function hasOneOrMany(OneToOneOrMany $relation): array
    {
        $modelMethod = $relation instanceof OneToOne
            ? Word::methodSingular($relation->table)
            : Word::method($relation->table);

        $eloquentMethod = $relation instanceof OneToOne
            ? 'hasOne'
            : 'hasMany';

        $args = [
            Word::class($relation->table, true),
            "'" . $relation->column . "'",
            "'" . $relation->foreignColumn . "'",
        ];

        $defaultValues = [
            "'" . $this->db->table($relation->foreignTable)->foreignKey() . "'",
            "'" . $this->db->table($relation->foreignTable)->primaryKey() . "'",
        ];

        $args = PhpGenerator::removeDefaults($args, $defaultValues);

        $other = $relation instanceof OneToOne
            ? Word::labelSingular($relation->table)
            : Word::label($relation->table);

        $self = Word::labelSingular($relation->foreignTable);

        return [
            '/**',
            " * Retrieve the $other associated with this $self.",
            ' */',
            "public function $modelMethod()",
            '{',
            "    return \$this->$eloquentMethod($args);",
            '}',
        ];
    }

    protected function belongsTo(OneToOneOrMany $relation): array
    {
        $modelMethod = Word::method($relation->column);

        $args = [
            Word::class($relation->foreignTable, true),
            "'" . $relation->column . "'",
            "'" . $relation->foreignColumn . "'",
        ];

        $defaultValues = [
            "'" . $this->db->table($relation->foreignTable)->foreignKey() . "'",
            "'" . $this->db->table($relation->foreignTable)->primaryKey() . "'",
        ];

        $args = PhpGenerator::removeDefaults($args, $defaultValues);

        $self = Word::labelSingular($relation->table);

        $other = Word::label($relation->column, true);

        return [
            '/**',
            " * Retrieve the $other of this $self.",
            ' */',
            "public function $modelMethod()",
            '{',
            "    return \$this->belongsTo($args);",
            '}',
        ];
    }

    protected function belongsToMany(ManyToMany $relation): array
    {
        if ($this->table->name() === $relation->foreignOne) {
            [$me, $myPivotCol, $myCol] = [$relation->foreignOne, $relation->pivotColumnOne, $relation->foreignOneColumn];
            [$other, $otherPivotCol, $otherCol] = [$relation->foreignTwo, $relation->pivotColumnTwo, $relation->foreignTwoColumn];
        } else {
            [$me, $myPivotCol, $myCol] = [$relation->foreignTwo, $relation->pivotColumnTwo, $relation->foreignTwoColumn];
            [$other, $otherPivotCol, $otherCol] = [$relation->foreignOne, $relation->pivotColumnOne, $relation->foreignOneColumn];
        }

        $modelMethod = Word::method($other);
        $eloquentMethod = 'belongsToMany';

        $args = [
            Word::class($other, true),
            "'" . $relation->pivot . "'",
            "'" . $myPivotCol . "'",
            "'" . $otherPivotCol . "'",
            "'" . $myCol . "'",
            "'" . $otherCol . "'",
        ];

        if ($me < $other) {
            $pivot = Word::snakeSingular($me) . '_' . Word::snakeSingular($other);
        } else {
            $pivot = Word::snakeSingular($other) . '_' . Word::snakeSingular($me);
        }

        $defaultValues = [
            "'" . $pivot . "'",
            "'" . $this->db->table($me)->foreignKey() . "'",
            "'" . $this->db->table($other)->foreignKey() . "'",
            "'" . $this->db->table($me)->primaryKey() . "'",
            "'" . $this->db->table($other)->primaryKey() . "'",
        ];

        $args = PhpGenerator::removeDefaults($args, $defaultValues);

        $other = Word::label($other);

        $self = Word::labelSingular($me);

        return [
            '/**',
            " * Retrieve the $other associated with this $self.",
            ' */',
            "public function $modelMethod()",
            '{',
            "    return \$this->$eloquentMethod($args);",
            '}',
        ];
    }

    public function path()
    {
        $tablename = $this->table->name();

        $id = $this->table->primaryKey();

        $model = Word::labelSingular($tablename);

        return [
            '/**',
            " * Returns the URL path fragment used in routes to identify this $model.",
            ' */',
            'public function path()',
            '{',
            "    return '/$tablename/' . \$this->$id;",
            '}',
        ];
    }
}
