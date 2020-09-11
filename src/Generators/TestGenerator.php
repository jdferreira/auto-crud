<?php

namespace Ferreira\AutoCrud\Generators;

use Carbon\Carbon;
use Ferreira\AutoCrud\Type;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Ferreira\AutoCrud\AccessorBuilder;

class TestGenerator extends BaseGenerator
{
    /** @var null|string */
    private $oneConstraintField = null;

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
            'assertDefaultValuesOnCreateForm' => $this->assertDefaultValuesOnCreateForm(),
            'assertEditFormHasValues' => $this->assertEditFormHasValues(),
            'oneConstraintField' => $this->oneConstraintField(),
            'oneInvalidValue' => $this->oneInvalidValue(),
            'assertRequiredFields' => $this->assertRequiredFields(),
            'assertNewEqualsModel' => $this->assertNewEqualsModel(),
            'assertFields' => $this->assertFields(),
        ];
    }

    protected function postProcess(string $code): string
    {
        $placeholder = $this->tablenameSingular();

        $countDefaultFields = $this->fields()->filter(function ($column) {
            return $this->table->hasDefault($column);
        })->count();

        if ($countDefaultFields === 0) {
            $code = $this->removeTest("it_starts_the_${placeholder}_create_form_with_the_default_values", $code);
        }

        $countRequiredFields = $this->fields()->filter(function ($column) {
            return $this->table->required($column);
        })->count();

        if ($countRequiredFields === 0) {
            $code = $this->removeTest("it_keeps_old_values_on_unsuccessful_${placeholder}_update", $code);
        }

        return $code;
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
        return app(
            AccessorBuilder::class,
            ['table' => $this->table]
        )->simpleAccessorFormatted($column);
    }

    private function assertHTMLOnForm()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $type = $this->table->type($column);

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                if ($type === Type::ENUM || $this->table->reference($column) !== null) {
                    $xpath = "//select[@name='$name']";
                } elseif ($type === Type::TEXT) {
                    $xpath = "//textarea[@name='$name']";
                } else {
                    if ($column === 'email' && $type === Type::STRING) {
                        $type = 'email';
                    } elseif ($type === Type::DATE) {
                        $type = 'date';
                    } elseif ($type === Type::TIME) {
                        $type = 'time';
                    } elseif ($type === Type::DATETIME) {
                        $type = 'datetime';
                    } elseif ($type === Type::BOOLEAN) {
                        $type = 'checkbox';
                    } else {
                        $type = 'text';
                    }

                    $xpath = "//input[@name='$name' and @type='$type']";
                }

                return '$this->assertHTML("' . $xpath . '", $document);';
            })
            ->all();
    }

    public function assertDefaultValuesOnCreateForm()
    {
        $hasDates = false;

        $lines = $this->fieldsExcept(['id'])
            ->map(function ($column) use (&$hasDates) {
                if (! $this->table->hasDefault($column)) {
                    return;
                }

                $type = $this->table->type($column);

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                $value = $this->table->default($column);

                if ($type === Type::ENUM || $this->table->reference($column) !== null) {
                    $xpath = "//select[@name='$name']/option[@name='$value' and @selected]";
                } elseif ($type === Type::TEXT) {
                    $xpath = "//textarea[@name='$name' and text()='$value']";
                } elseif ($type === Type::BOOLEAN) {
                    $checked = $value ? '@checked' : 'not(@checked)';

                    $xpath = "//input[@name='$name' and @type='checkbox' and $checked]";
                } else {
                    if ($column === 'email' && $type === Type::STRING) {
                        $type = 'email';
                    } elseif ($type === Type::DATE) {
                        $type = 'date';
                        $hasDates = true;
                        $value = '2020-01-01';
                    } elseif ($type === Type::TIME) {
                        $type = 'time';
                        $hasDates = true;
                        $value = '01:02:03';
                    } elseif ($type === Type::DATETIME) {
                        $type = 'datetime';
                        $hasDates = true;
                        $value = '2020-01-01 01:02:03';
                    } else {
                        $type = 'text';
                    }

                    $xpath = "//input[@name='$name' and @type='$type' and @value='$value']";
                }

                return '$this->assertHTML("' . $xpath . '", $document);';
            })
            ->filter()
            ->all();

        if ($hasDates) {
            $lines = array_merge(
                [
                    '\Carbon\Carbon::setTestNow(\'2020-01-01 01:02:03\');',
                    '',
                ],
                $lines
            );
        }

        return $lines;
    }

    private function assertEditFormHasValues()
    {
        $groups = $this->fieldsExcept(['id'])->groupBy(function ($column) {
            $type = $this->table->type($column);

            if ($type === Type::BOOLEAN) {
                return 'checkbox';
            } elseif ($type === Type::ENUM) {
                return 'select';
            } elseif ($type === Type::TEXT) {
                return 'textarea';
            } else {
                return 'regular';
            }
        });

        $builder = app(AccessorBuilder::class, [
            'table' => $this->table,
        ]);

        $regularInputs = $groups->get('regular', collect())
            ->map(function ($column) use ($builder) {
                $value = $builder->simpleAccessor(
                    $column,
                    '$' . $this->modelVariableSingular()
                );

                if (Type::dateTimeFormat($this->table->type($column)) !== null) {
                    $value = $builder->formatAccessor($value, $column);
                }

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                return "\$this->assertHTML(\$this->xpath(\"//*[@name='$name' and @value='%s']\", $value), \$document);";
            });

        $checkboxInputs = $groups->get('checkbox', collect())
            ->flatMap(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";
                $column = Str::camel($column);

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                return [
                    "\${$column}Checked = $value ? '@checked' : 'not(@checked)';",
                    "\$this->assertHTML(\"//*[@name='$name' and \${$column}Checked]\", \$document);",
                ];
            });

        $selectInputs = $groups->get('select', collect())
            ->map(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                return "\$this->assertHTML(\$this->xpath(\"//*[@name='$name']/option[@value='%s' and @selected]\", $value), \$document);";
            });

        $textareaInputs = $groups->get('textarea', collect())
            ->map(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                return "\$this->assertHTML(\$this->xpath(\"//*[@name='$name' and text()='%s']\", $value), \$document);";
            });

        $inputs = $regularInputs;

        foreach ([$checkboxInputs, $selectInputs, $textareaInputs] as $partial) {
            if ($inputs->count() > 0 && $partial->count() > 0) {
                $inputs->push('');
                $inputs = $inputs->merge($partial);
            }
        }

        return $inputs->all();
    }

    private function oneConstraintField()
    {
        // A constraint field is one that fits one of the following:
        // - required without a default value
        // - an integer
        // - a boolean
        // - a datetime, date, time
        // - a decimal
        // - an enum
        // - a key to a foreign table
        // - an email or UUID

        return $this->oneConstraintField = $this->fieldsExcept(['id'])->filter(function ($column) {
            static $constraintTypes = [
                Type::INTEGER,
                Type::BOOLEAN,
                Type::DATETIME,
                Type::DATE,
                Type::TIME,
                Type::DECIMAL,
                Type::ENUM,
            ];

            return ($this->table->required($column) && ! $this->table->hasDefault($column))
                || in_array($this->table->type($column), $constraintTypes)
                || in_array($column, ['email', 'uuid'])
                || $this->table->reference($column) !== null;
        })->first();
    }

    private function oneInvalidValue()
    {
        // Let's generate a value that is invalid for the constraint field.
        $column = $this->oneConstraintField;

        // If no constraint column exists, this method is not meaningful (and
        // the code that is generated based on its returned value is going to be
        // cut anyway). As such, just return null and don't worry about it.
        if ($column === null) {
            return null;
        }

        // If the field is required without a default value, just send an empty string
        if ($this->table->required($column) && ! $this->table->hasDefault($column)) {
            return "''";
        }

        // The string '???' is invalid for all constraint types (integer,
        // boolean, datetime, date, time, decimal), all emails and all UUIDs. It
        // is also invalid for foreign keys. It should also be invalid for enum
        // columns that do not contain that string as a valid value (which is
        // likely!).
        if (
            $this->table->type($column) !== Type::ENUM
            || ! in_array('???', $validValues = $this->table->getEnumValid($column))
        ) {
            return "'???'";
        }

        // Otherwise, we are dealing with an enum column that accepts the string
        // '???'. Let's find an invalid value for this column as well. If we
        // concatenate all values and append an X, the result is not valid
        return '"' . substr('"', '', collect($validValues)->join('')) . 'X' . '"';
    }

    private function assertRequiredFields()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $required = $this->table->required($column)
                    && $this->table->type($column) !== Type::BOOLEAN;

                $required = $required ? '@required' : 'not(@required)';

                $name = htmlentities(str_replace('_', '-', $column), ENT_QUOTES);

                return "\$this->assertHTML(\"//*[@name='$name' and $required]\", \$document);";
            })
            ->all();
    }

    private function assertNewEqualsModel()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $expected = "\$new['$column']";
                $retrieved = "\${$this->modelVariableSingular()}->$column";

                $type = $this->table->type($column);

                if (($format = Type::dateTimeFormat($type)) !== null) {
                    if ($this->table->required($column)) {
                        $retrieved .= "->format($format)";
                    } else {
                        $retrieved = "$retrieved !== null ? ${retrieved}->format($format) : null";
                    }
                }

                return "\$this->assertEquals($expected, $retrieved);";
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

        $required = $this->table->required($column);

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
                true,
                false,
            ];
        } elseif ($this->table->type($column) === Type::DATETIME) {
            return [
                '2020-01-01 00:00:00', // TODO: This needs check with MDN on datetime input value format...
                '2021-12-31 23:59:59',
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
        } elseif ($this->table->type($column) === Type::INTEGER) {
            return ['3.14', 'not-a-number'];
        } elseif ($this->table->type($column) === Type::BOOLEAN) {
            return ['yes', 'no', '2'];
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
                '2.7.1',
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

    private function quoteValue($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif ($value === true) {
            return 'true';
        } elseif ($value === false) {
            return 'false';
        } elseif (is_int($value)) {
            return $value;
        } else {
            $value = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

            return "'$value'";
        }
    }

    private function removeTest(string $testName, string $code): string
    {
        $lines = explode("\n", $code);

        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], $testName) !== false) {
                $start = $i - 1; // Remove the "/** @test */" line as well
            } elseif (isset($start) && $lines[$i] === '    }') {
                $end = $i;
                break;
            }
        }

        if (isset($start)) {
            for ($i = $start; $i < $end; $i++) {
                unset($lines[$i]);
            }
        }

        return implode("\n", $lines);
    }
}
