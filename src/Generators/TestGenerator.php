<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Ferreira\AutoCrud\AccessorBuilder;

class TestGenerator extends BaseGenerator
{
    /** @var string[] */
    private $fakes = [];

    protected function stub(): string
    {
        return 'test.php.stub';
    }

    protected function filename(): string
    {
        return base_path('tests/Feature/' . Str::studly($this->table->name()) . 'CrudTest.php');
    }

    protected function replacements(): array
    {
        return [
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'modelClassPlural' => $this->modelClassPlural(),
            'tablename' => $this->tablename(),
            'modelVariablePlural' => $this->modelVariablePlural(),
            'modelVariableSingular' => $this->modelVariableSingular(),
            'assertSeeColumnValuesOnIndexOrShow' => $this->assertSeeColumnValuesOnIndexOrShow(),
            'tablenameSingularWithArticle' => $this->tablenameSingularWithArticle(),
            'tablenameSingular' => $this->tablenameSingular(),
            'assertHTMLOnForm' => $this->assertHTMLOnForm(),
            'assertEditFormHasValues' => $this->assertEditFormHasValues(),
            'oneRequiredField' => $this->oneRequiredField(),
            'assertRequiredFields' => $this->assertRequiredFields(),
            'assertRawEqualsCreated' => $this->assertRawEqualsCreated(),
            'assertNewEqualsFresh' => $this->assertNewEqualsFresh(),
            'assertFields' => $this->assertFields(),
        ];
    }

    private function modelNamespace(): string
    {
        if ($this->dir === '') {
            return 'App';
        } else {
            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }
    }

    private function modelClass()
    {
        return Str::studly(Str::singular($this->table->name()));
    }

    private function modelClassPlural()
    {
        return Str::studly($this->table->name());
    }

    private function tablename()
    {
        return $this->table->name();
    }

    private function tablenameSingular()
    {
        return Str::singular($this->table->name());
    }

    private function tablenameSingularWithArticle()
    {
        $tablename = $this->table->name();

        $article = in_array(Str::substr($tablename, 0, 1), ['a', 'e', 'i', 'o'])
            ? 'an'
            : 'a';

        return $article . '_' . Str::singular($tablename);
    }

    private function modelVariablePlural()
    {
        return Str::camel($this->table->name());
    }

    private function modelVariableSingular()
    {
        return Str::singular(Str::camel($this->table->name()));
    }

    private function assertSeeColumnValuesOnIndexOrShow()
    {
        return $this->fields()
            ->map(function ($column) {
                return '->assertSeeText(' . $this->castColumn($column) . ')';
            })
            ->all();
    }

    private function fields(): Collection
    {
        $diff = ['created_at', 'updated_at', 'deleted_at'];

        return collect($this->table->columns())->diff($diff);
    }

    private function fieldsExcept(array $diff): Collection
    {
        return collect($this->fields())->diff($diff);
    }

    private function castColumn(string $column)
    {
        return (new AccessorBuilder($this->table, $column))->buildSimpleAccessor();
    }

    private function assertHTMLOnForm()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $type = $this->table->type($column);

                if ($type === Type::ENUM || $this->table->reference($column) !== null) {
                    $tag = 'select';
                } elseif ($type === Type::TEXT) {
                    $tag = 'textarea';
                } else {
                    $tag = 'input';
                }

                return "\$this->assertHTML(\$this->getXPath('$tag', '$column'), \$document);";
            })
            ->all();
    }

    private function assertEditFormHasValues()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $type = $this->table->type($column);

                if ($type === Type::ENUM || $this->table->reference($column) !== null) {
                    $tag = 'select';
                } elseif ($type === Type::TEXT) {
                    $tag = 'textarea';
                } else {
                    $tag = 'input';
                }

                return "\$this->assertHTML(\$this->getXPath('$tag', '$column', \${$this->modelVariableSingular()}->$column), \$document);";
            })
            ->all();
    }

    private function oneRequiredField()
    {
        return $this->fieldsExcept(['id'])->filter(function ($column) {
            return $this->table->required($column)
                && ! $this->table->hasDefault($column)
                && $this->table->type($column) !== Type::BOOLEAN;
        })->first();
    }

    private function assertRequiredFields()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $type = $this->table->type($column);

                if ($type === Type::ENUM || $this->table->reference($column) !== null) {
                    $tag = 'select';
                } elseif ($type === Type::TEXT) {
                    $tag = 'textarea';
                } else {
                    $tag = 'input';
                }

                $required = $this->table->required($column)
                    && ! $this->table->hasDefault($column)
                    && $this->table->type($column) !== Type::BOOLEAN;
                $required = $required ? 'true' : 'false';

                return "\$this->assertHTML(\$this->getXPath('$tag', '$column', null, $required), \$document);";
            })
            ->all();
    }

    private function assertRawEqualsCreated()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                return "\$this->assertEquals(\${$this->modelVariableSingular()}->$column, \$created->$column);";
            })
            ->all();
    }

    private function assertNewEqualsFresh()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                return "\$this->assertEquals(\$new->$column, \$fresh->$column);";
            })
            ->all();
    }

    private function assertFields()
    {
        $result = [];

        $uniqueColumns = $this->fieldsExcept(['id'])->filter(function ($column) {
            return $this->table->unique($column);
        });

        if ($uniqueColumns->count() > 0) {
            $model = str_replace('_', ' ', $this->tablenameSingular());

            $result = array_merge(
                [
                    "// Create one $model to test fields that should contain unique values",
                    "factory({$this->modelClass()}::class)->create([",
                ],
                $uniqueColumns->map(function ($column) {
                    $fake = $this->fakeForUnique($column);

                    return "    '$column' => $fake,";
                })->all(),
                [
                    ']);',
                    '',
                ]
            );
        }

        $first = true;

        foreach ($this->fieldsExcept(['id']) as $column) {
            if (! $first) {
                $result = array_merge($result, ['']);
            }

            $result = array_merge($result, $this->assertField($column));

            $first = false;
        }

        return $result;
    }

    private function fakeForUnique($column)
    {
        $fake = $this->quoteValue(
            $this->fakeValid($column)[0]
        );

        $this->fakes[$column] = $fake;

        return $fake;
    }

    private function assertField(string $column)
    {
        $accepts = $this->fakeValid($column);
        $rejects = $this->fakeInvalid($column);

        if (array_key_exists($column, $this->fakes)) {
            $duplicateValue = array_shift($accepts);

            $rejects[] = [$duplicateValue, 'Duplicate values must be rejected'];
        }

        $assertions = array_merge(
            $this->stackFieldAssertion($accepts, 'accepts'),
            $this->stackFieldAssertion($rejects, 'rejects')
        );

        $required =
            $this->table->required($column)
            && ! $this->table->hasDefault($column)
            && $this->table->type($column) !== Type::BOOLEAN;

        $assertions[] = $required
            ? '    ->rejects(null);'
            : '    ->accepts(null);';

        return array_merge(
            [
                "\$this->assertField('$column')",
            ],
            $assertions
        );
    }

    private function stackFieldAssertion($values, $method)
    {
        return collect($values)
            ->map(function ($value) use ($method) {
                if (is_array($value)) {
                    [$value, $comment] = $value;

                    $comment = " // $comment";
                } else {
                    $comment = '';
                }

                $value = $this->quoteValue($value);

                return "    ->$method($value)$comment";
            })
            ->all();
    }

    /**
     * @return string[]
     */
    private function fakeValid(string $column): array
    {
        if ($this->table->type($column) === Type::STRING) {
            if ($column === 'name') {
                return [
                    'John Doe',
                    'Jane Doe',
                ];
            } elseif ($column === 'email') {
                return [
                    'mail@example.com',
                    'johndoe@example.com',
                ];
            } else {
                return [
                    'One string',
                    'Another string',
                ];
            }
        } elseif ($this->table->type($column) === Type::TEXT) {
            return [
                'One long comment or sentence.',
            ];
        } elseif ($this->table->type($column) === Type::INTEGER) {
            return [
                0,
                10,
                -10,
            ];
        } elseif ($this->table->type($column) === Type::BOOLEAN) {
            return [
                'on',
            ];
        } elseif ($this->table->type($column) === Type::DATETIME) {
            return [
                '2020-01-01', // TODO: This needs check with MDN on datetime input value format...
                '2021-12-31',
            ];
        } elseif ($this->table->type($column) === Type::DATE) {
            return [
                '2020-01-01',
                '2021-12-31',
            ];
        } elseif ($this->table->type($column) === Type::TIME) {
            return [
                '00:01:02',
                '23:59:59',
            ];
        } elseif ($this->table->type($column) === Type::DECIMAL) {
            return [
                '0',
                '13.37',
                '-12.34',
            ];
        } elseif ($this->table->type($column) === Type::BINARY) {
            // TODO: What here?!
        } elseif ($this->table->type($column) === Type::ENUM) {
            return $this->table->getEnumValid($column);
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function fakeInvalid(string $column): array
    {
        if ($this->table->type($column) === Type::STRING) {
            return []; // All strings are allowed
        } elseif ($this->table->type($column) === Type::TEXT) {
            return []; // All strings are allowed
        } elseif ($this->table->type($column) === Type::BINARY) {
            return []; // All binary data is valid
        } elseif ($this->table->type($column) === Type::INTEGER) {
            return ['3.14', 'not-a-number'];
        } elseif ($this->table->type($column) === Type::BOOLEAN) {
            return ['yes', 'true'];
        } elseif ($this->table->type($column) === Type::DATETIME) {
            return [
                '2020-13-01 00:00:00',
                '2020-01-32 00:00:00',
                '2020-01-01 25:00:00',
                '2020-01-01 00:61:00',
                '2020-01-01 00:00:61',
                'not-a-datetime',
            ];
        } elseif ($this->table->type($column) === Type::DATE) {
            return [
                '2020-13-01',
                '2020-01-32',
                'not-a-date',
            ];
        } elseif ($this->table->type($column) === Type::TIME) {
            return [
                '25:00:00',
                '00:61:00',
                '00:00:61',
                'not-a-time',
            ];
        } elseif ($this->table->type($column) === Type::DECIMAL) {
            return [
                '2.718281828',
                'not-a-number',
            ];
        } elseif ($this->table->type($column) === Type::ENUM) {
            foreach (['black', 'seven', 'ioha', 'saturn'] as $value) {
                if (! in_array($value, $this->table->getEnumValid($column))) {
                    return [$value];
                }
            }
        }

        return [];
    }

    private function quoteValue(?string $value): string
    {
        if ($value === null) {
            return 'null';
        } else {
            $value = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

            return "'$value'";
        }
    }
}
