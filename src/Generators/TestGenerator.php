<?php

namespace Ferreira\AutoCrud\Generators;

use Exception;
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

        if ($this->oneConstraintField === null) {
            $code = $this->removeTest("it_keeps_old_values_on_unsuccessful_${placeholder}_update", $code);
        }

        return $code;
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

    public function assertSeeColumnValuesOnIndexOrShow()
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

    /**
     * Wrap the given XPath expression in an assertion:.
     *
     * ```php
     * $xpath = "//input[@name='test']"
     * $this->wrapXPath($xpath)
     * // returns '$this->assertHTML("//input[@name=\'test\'])", $document);'
     * ```
     *
     * @param string $xpath
     * @param null|string[] $arguments
     * @param string $document
     *
     * @return string
     */
    private function wrapXPath(string $xpath, array $arguments = null, string $document = '$document'): string
    {
        $expectedNumberOfArguments = substr_count($xpath, '%s');

        if (
            ($arguments === null && $expectedNumberOfArguments !== 0) ||
            ($arguments !== null && count($arguments) !== $expectedNumberOfArguments)
        ) {
            throw new Exception('Wrong number of arguments');
        }

        if ($arguments === null) {
            return "\$this->assertHTML(\"$xpath\", $document);";
        }

        $arguments = implode(', ', $arguments);

        return "\$this->assertHTML(\$this->xpath(\"$xpath\", $arguments), $document);";
    }

    public function assertHTMLOnForm()
    {
        $selects = $this->fieldsExcept(['id'])
            ->filter(function (string $column) {
                return $this->table->type($column) === Type::ENUM;
            })
            ->flatMap(function (string $column) {
                $name = $this->quoteName($column);

                return collect($this->table->getEnumValid($column))
                    ->map(function ($valid) use ($name) {
                        $valid = e($valid);

                        return $this->wrapXPath("//select[@name='$name']/option[@value='$valid']");
                    })
                    ->prepend(
                        $this->wrapXPath("//select[@name='$name']")
                    )
                    ->all();
            });

        $other = $this->fieldsExcept(['id'])
            ->filter(function (string $column) {
                return $this->table->type($column) !== Type::ENUM;
            })
            ->map(function ($column) {
                $type = $this->table->type($column);

                $name = $this->quoteName($column);

                if ($type === Type::TEXT) {
                    return $this->wrapXPath("//textarea[@name='$name']");
                }

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

                return $this->wrapXPath("//input[@name='$name' and @type='$type']");
            });

        $result = $other;

        if ($result->count() > 0 && $selects->count() > 0) {
            // TODO: This is a block merging operation, which can be abstracted
            // (some place else on the code uses this as well)
            $result->push('');
            $result = $result->merge($selects);
        }

        return $result->all();
    }

    public function assertDefaultValuesOnCreateForm()
    {
        $hasDates = false;

        $lines = $this->fieldsExcept(['id'])
            ->filter(function ($column) {
                return $this->table->hasDefault($column);
            })
            ->map(function ($column) use (&$hasDates) {
                $type = $this->table->type($column);

                $name = $this->quoteName($column);

                $value = $this->table->default($column);

                if ($type === Type::ENUM) {
                    return $this->wrapXPath("//select[@name='$name']/option[@name='$value' and @selected]");
                } elseif ($type === Type::TEXT) {
                    return $this->wrapXPath("//textarea[@name='$name' and .='$value']");
                } elseif ($type === Type::BOOLEAN) {
                    $checked = $value ? '@checked' : 'not(@checked)';

                    return $this->wrapXPath("//input[@name='$name' and @type='checkbox' and $checked]");
                }

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

                return $this->wrapXPath("//input[@name='$name' and @type='$type' and @value='$value']");
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

    public function assertEditFormHasValues()
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

        // TODO: We could have a single instance of the AccessorBuilder class
        // for this whole TestGenerator instance
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

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and @value='%s']", [$value]);
            });

        $checkboxInputs = $groups->get('checkbox', collect())
            ->flatMap(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";
                $column = Str::camel($column);

                $name = $this->quoteName($column);

                return [
                    "\${$column}Checked = $value ? '@checked' : 'not(@checked)';",
                    $this->wrapXPath("//*[@name='$name' and \${$column}Checked]"),
                ];
            });

        $selectInputs = $groups->get('select', collect())
            ->map(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name']/option[@value='%s' and @selected]", [$value]);
            });

        $textareaInputs = $groups->get('textarea', collect())
            ->map(function ($column) {
                $value = "\${$this->modelVariableSingular()}->$column";

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and .='%s']", [$value]);
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

    public function oneConstraintField()
    {
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

            return $this->table->required($column)
                || in_array($this->table->type($column), $constraintTypes)
                || in_array($column, ['email', 'uuid'])
                || $this->table->reference($column) !== null;
        })->first();
    }

    public function oneInvalidValue()
    {
        // Let's generate a value that is invalid for the constraint field.
        $column = $this->oneConstraintField;

        // If no constraint column exists, this method is not meaningful (and
        // the code that is generated based on its returned value is going to be
        // cut anyway). As such, just return null and don't worry about it.
        if ($column === null) {
            return null;
        }

        // If the field is required, the empty string is an invalid value
        if ($this->table->required($column)) {
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

    public function assertRequiredFields()
    {
        return $this->fieldsExcept(['id'])
            ->map(function ($column) {
                $required = $this->table->required($column)
                    && $this->table->type($column) !== Type::BOOLEAN;

                $required = $required ? '@required' : 'not(@required)';

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and $required]");
            })
            ->all();
    }

    public function assertNewEqualsModel()
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

    public function assertFields()
    {
        $result = [];

        $uniqueColumns = $this->fieldsExcept(['id'])->filter(function ($column) {
            return $this->table->unique($column);
        });

        if ($uniqueColumns->count() > 0) {
            $model = str_replace('_', ' ', $this->tablenameSingular());
            $modelClass = $this->modelClass();

            $result = array_merge(
                [
                    "// Create one $model to test fields that should contain unique values",
                    "factory($modelClass::class)->create([",
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

                // Also remove the line before the docblock, if it is empty.
                if (trim($lines[$start - 1]) === '') {
                    $start--;
                }
            } elseif (isset($start) && $lines[$i] === '    }') {
                $end = $i;
                break;
            }
        }

        if (isset($start)) {
            for ($i = $start; $i <= $end; $i++) {
                unset($lines[$i]);
            }
        }

        return implode("\n", $lines);
    }

    private function quoteName(string $column)
    {
        return htmlentities(str_replace('_', '-', $column), ENT_QUOTES);
    }
}
