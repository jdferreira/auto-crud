<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ferreira\AutoCrud\Database\OneToOne;
use Ferreira\AutoCrud\Database\OneToMany;
use Ferreira\AutoCrud\Database\ManyToMany;
use Ferreira\AutoCrud\Database\OneToOneOrMany;

class ModelGenerator extends BaseGenerator
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
        $filename = $this->class() . '.php';

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
        return [
            'namespace' => $this->namespace(),
            'class' => $this->class(),
            'importSoftDeletesTrait' => $this->importSoftDeletes(),
            'useSoftDeletesTrait' => $this->useSoftDeletes(),
            'customPrimaryKey' => $this->primaryKey(),
            'casts' => $this->casts(),
            'relationships' => $this->relationships(),
            'path' => $this->path(),
        ];
    }

    protected function namespace()
    {
        if ($this->dir === '') {
            return 'App';
        } else {
            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }
    }

    private function class()
    {
        return Str::studly(Str::singular($this->table->name()));
    }

    protected function importSoftDeletes()
    {
        return $this->table->softDeletes()
            ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;'
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
            Type::DATE => 'date',
            // For the next line, laravel does not have a 'time' cast; so cast
            // to datetime and format appropriately when needed
            Type::TIME => 'datetime',
            Type::DECIMAL => 'decimal:2',
        ];

        $casts = [];

        foreach ($this->table->columns() as $name) {
            if (in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            if ($name === $this->table->primaryKey()) {
                continue;
            }

            $isForeignKey = $this->table->reference($name) !== null;

            if ($isForeignKey) {
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
            ? Str::camel(Str::singular($relation->table))
            : Str::camel(Str::plural($relation->table));

        $eloquentMethod = $relation instanceof OneToOne
            ? 'hasOne'
            : 'hasMany';

        $args = [
            Str::studly(Str::singular($relation->table)) . '::class',
            "'" . $relation->column . "'",
            "'" . $relation->foreignColumn . "'",
        ];

        $defaultValues = [
            "'" . $this->db->foreignKey($relation->foreignTable) . "'",
            "'" . $this->db->primaryKey($relation->foreignTable) . "'",
        ];

        $args = BaseGenerator::removeDefaults($args, $defaultValues);

        $other = $relation instanceof OneToOne
            ? str_replace('_', ' ', Str::singular($relation->table))
            : str_replace('_', ' ', Str::plural($relation->table));

        $self = str_replace('_', ' ', Str::singular($relation->foreignTable));

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
        $modelMethod = substr($relation->column, -3) === '_id'
            ? Str::camel(Str::singular(substr($relation->column, 0, -3)))
            : Str::camel(Str::singular($relation->foreignTable));

        $args = [
            Str::studly(Str::singular($relation->foreignTable)) . '::class',
            "'" . $relation->column . "'",
            "'" . $relation->foreignColumn . "'",
        ];

        $defaultValues = [
            "'" . $this->db->foreignKey($relation->foreignTable) . "'",
            "'" . $this->db->primaryKey($relation->foreignTable) . "'",
        ];

        $args = BaseGenerator::removeDefaults($args, $defaultValues);

        $self = str_replace('_', ' ', Str::singular($relation->table));

        if (substr($relation->column, -3) === '_id') {
            $other = str_replace('_', ' ', Str::singular(substr($relation->column, 0, -3)));
            $docblock = "Retrieve the $other of this $self.";
        } else {
            $other = str_replace('_', ' ', Str::singular($relation->foreignTable));
            $docblock = "Retrieve the $other this $self belongs to.";
        }

        return [
            '/**',
            " * $docblock",
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

        $modelMethod = Str::camel(Str::plural($other));
        $eloquentMethod = 'belongsToMany';

        $args = [
            Str::studly(Str::singular($other)) . '::class',
            "'" . $relation->pivot . "'",
            "'" . $otherPivotCol . "'",
            "'" . $myPivotCol . "'",
            "'" . $otherCol . "'",
            "'" . $myCol . "'",
        ];

        if ($me < $other) {
            $pivot = Str::singular($me) . '_' . Str::singular($other);
        } else {
            $pivot = Str::singular($other) . '_' . Str::singular($me);
        }

        $defaultValues = [
            "'" . $pivot . "'",
            "'" . $this->db->foreignKey($other) . "'",
            "'" . $this->db->foreignKey($me) . "'",
            "'" . $this->db->primaryKey($other) . "'",
            "'" . $this->db->primaryKey($me) . "'",
        ];

        $args = BaseGenerator::removeDefaults($args, $defaultValues);

        $other = str_replace('_', ' ', Str::plural($other));

        $self = str_replace('_', ' ', $me);

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

        $model = str_replace('_', ' ', Str::singular($tablename));

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
