<?php

namespace Ferreira\AutoCrud\Generators;

use Exception;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Ferreira\AutoCrud\VersionChecker;
use Ferreira\AutoCrud\AccessorBuilder;
use Ferreira\AutoCrud\Database\ManyToMany;

class TestGenerator extends TableBasedGenerator
{
    /** @var bool */
    private $skipApi = false;

    /** @var null|string */
    private $oneConstraintField = null;

    /** @var string[] */
    private $fakes = [];

    /** @var string[] */
    private $otherUses = [];

    protected function stub(): string
    {
        return 'test.php.stub';
    }

    protected function filename(): string
    {
        return base_path('tests/Feature/' . Word::classPlural($this->table->name()) . 'CrudTest.php');
    }

    /**
     * @return $this
     */
    public function skipApi(): self
    {
        $this->skipApi = true;

        return $this;
    }

    protected function replacements(): array
    {
        // We need to generate a few blocks of code first, because they tell us
        // if we need to use additional classes
        $setTime = $this->setTime();
        $assertFields = $this->assertFields();
        $assertForeignFieldsPopulated = $this->assertForeignFieldsPopulated();
        $assertManyToManyRelationships = $this->assertManyToManyRelationships();

        return [
            'modelNamespace' => $this->modelNamespace(),
            'modelClass' => $this->modelClass(),
            'otherUses' => $this->otherUses(),
            'modelClassPlural' => $this->modelClassPlural(),
            'tablename' => $this->tablename(),
            'modelVariablePlural' => $this->modelVariablePlural(),
            'modelVariableSingular' => $this->modelVariableSingular(),
            'assertSeeColumnValuesOnIndexOrShow' => $this->assertSeeColumnValuesOnIndexOrShow(),
            'tablenameSingularWithArticle' => $this->tablenameSingularWithArticle(),
            'tablenameSingular' => $this->tablenameSingular(),
            'assertHTMLOnForm' => $this->assertHTMLOnForm(),
            'setTime' => $setTime,
            'assertDefaultValuesOnCreateForm' => $this->assertDefaultValuesOnCreateForm(),
            'assertEditFormHasValues' => $this->assertEditFormHasValues(),
            'oneConstraintField' => $this->oneConstraintField(),
            'oneInvalidValue' => $this->oneInvalidValue(),
            'assertForeignFieldsPopulated' => $assertForeignFieldsPopulated,
            'assertManyToManyRelationships' => $assertManyToManyRelationships,
            'assertRequiredFields' => $this->assertRequiredFields(),
            'updateNewWithManyToManyRelationships' => $this->updateNewWithManyToManyRelationships(),
            'assertNewEqualsModel' => $this->assertNewEqualsModel(),
            'assertNewEqualsModelForManyToManyRelationships' => $this->assertNewEqualsModelForManyToManyRelationships(),
            'modelVariableStoreSomeManyToManyRelationships' => $this->modelVariableStoreSomeManyToManyRelationships(),
            'assertFields' => $assertFields,
            'assertManyToManyFields' => $this->assertManyToManyFields(),
            'simpleFactory' => $this->simpleFactory(),
            'fullFactory' => $this->fullFactory(),
            'fullFactoryForTwo' => $this->fullFactoryForTwo(),
        ];
    }

    protected function postProcess(string $code): string
    {
        $tablename = $this->tablename();
        $placeholder = $this->tablenameSingular();

        $countDefaultFields = $this->fields()->filter(function ($column) {
            return $this->table->hasDefault($column);
        })->count();

        if ($countDefaultFields === 0) {
            $code = $this->removeMethod("it_starts_the_${placeholder}_create_form_with_the_default_values", $code);
        }

        if ($this->oneConstraintField === null) {
            $code = $this->removeMethod("it_keeps_old_values_on_unsuccessful_updates_of_${tablename}", $code);
            $code = $this->removeMethod("it_keeps_old_values_on_unsuccessful_api_updates_of_${tablename}", $code);
        }

        if (count($this->table->allReferences()) === 0) {
            $code = $this->removeMethod("it_populates_foreign_keys_on_the_create_and_edit_forms_of_${tablename}", $code);
        }

        if (count($this->db->manyToMany($this->table->name())) === 0) {
            $code = $this->removeMethod("it_populates_many_to_many_relationships_on_the_create_and_edit_forms_of_${tablename}", $code);
        }

        if ($this->skipApi) {
            $singular = $this->tablenameSingularWithArticle();

            $code = $this->removeMethod("it_shows_existing_{$tablename}_in_the_api_index", $code);
            $code = $this->removeMethod("it_shows_the_values_of_{$singular}_in_the_api_show_view", $code);
            $code = $this->removeMethod("it_keeps_old_values_on_unsuccessful_api_updates_of_{$tablename}", $code);
            $code = $this->removeMethod("it_validates_field_values_when_creating_{$singular}_with_api", $code);
            $code = $this->removeMethod("it_creates_{$tablename}_with_api_when_asked_to", $code);
            $code = $this->removeMethod("it_validates_field_values_when_updating_{$singular}_with_api", $code);
            $code = $this->removeMethod("it_updates_{$tablename}_with_api_when_asked_to", $code);
            $code = $this->removeMethod("it_deletes_{$tablename}_with_api_when_asked_to", $code);
        }

        return $code;
    }

    public function otherUses()
    {
        return collect($this->otherUses)
            ->unique()
            ->map(function ($class) {
                return "use $class;";
            })
            ->all();
    }

    private function modelClassPlural()
    {
        return Word::classPlural($this->table->name());
    }

    private function tablename()
    {
        return $this->table->name();
    }

    private function tablenameSingular()
    {
        return Word::snakeSingular($this->table->name());
    }

    private function tablenameSingularWithArticle()
    {
        $tablename = $this->table->name();

        $article = in_array(Str::substr($tablename, 0, 1), ['a', 'e', 'i', 'o'])
            ? 'an'
            : 'a';

        return $article . '_' . Word::snakeSingular($tablename);
    }

    private function modelVariablePlural()
    {
        return Word::variable($this->table->name());
    }

    private function modelVariableSingular()
    {
        return Word::variableSingular($this->table->name());
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

    public function fieldsExceptPrimary(): Collection
    {
        return collect($this->fields())->diff($this->table->primaryKey());
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
        $groups = $this->fieldsExceptPrimary()
            ->groupBy(function ($column) {
                if ($this->table->reference($column) !== null) {
                    return 'reference';
                } elseif ($this->table->type($column) === Type::ENUM) {
                    return 'enum';
                } else {
                    return 'other';
                }
            });

        $referenceColumns = $groups->get('reference', collect())
            ->map(function ($column) {
                $name = $this->quoteName($column);

                return $this->wrapXPath("//select[@name='$name']");
            });

        $enumColumns = $groups->get('enum', collect())
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

        $otherColumns = $groups->get('other', collect())
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

        $manyToManyInputs = collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $name = Word::kebab($relationship->foreignTwo);

                return $this->wrapXPath("//select[@name='$name' and @multiple]");
            });

        return $this->joinBlocks($otherColumns, $enumColumns, $referenceColumns, $manyToManyInputs);
    }

    public function setTime()
    {
        // We need to set a test timestamp when there are date-like columns that
        // have a default value of CURRENT_TIMESTAMP.

        $needsCarbonSetTestNow = collect($this->table->columns())
            ->filter(function ($column) {
                return in_array($this->table->type($column), [Type::DATE, Type::TIME, Type::DATETIME])
                    && $this->table->default($column) === 'CURRENT_TIMESTAMP';
            })
            ->count() > 0;

        if ($needsCarbonSetTestNow) {
            $this->otherUses[] = 'Carbon\\Carbon';

            return 'Carbon::setTestNow(\'2020-01-01 01:02:03\');';
        } else {
            return;
        }
    }

    public function assertDefaultValuesOnCreateForm()
    {
        $lines = $this->fieldsExceptPrimary()
            ->filter(function ($column) {
                return $this->table->hasDefault($column);
            })
            ->map(function ($column) {
                $type = $this->table->type($column);

                $name = $this->quoteName($column);

                $value = $this->table->default($column);

                if ($type === Type::ENUM) {
                    return $this->wrapXPath("//select[@name='$name']/option[@value='$value' and @selected]");
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
                    $value = '2020-01-01';
                } elseif ($type === Type::TIME) {
                    $type = 'time';
                    $value = '01:02:03';
                } elseif ($type === Type::DATETIME) {
                    $type = 'datetime';
                    $value = '2020-01-01 01:02:03';
                } else {
                    $type = 'text';
                }

                return $this->wrapXPath("//input[@name='$name' and @type='$type' and @value='$value']");
            })
            ->filter()
            ->all();

        return $lines;
    }

    public function assertForeignFieldsPopulated()
    {
        $tablename = $this->tablename();

        $references = $this->table->allReferences();

        $modelCreations = [];

        foreach ($references as $_ => [$foreignTable, $_]) {
            $models = Word::variable($foreignTable);
            $modelClass = Word::class($foreignTable);
            $modelCreations[] = "$models = factory($modelClass::class, 30)->create();";

            $this->otherUses[] = $this->modelNamespace() . '\\' . $modelClass;
        }

        $assertions = [];

        foreach ($references as $column => [$foreignTable, $_]) {
            $model = Word::variableSingular($foreignTable);
            $models = Word::variable($foreignTable);

            $name = Word::kebab($column);
            $primaryKey = $this->db->table($foreignTable)->primaryKey();
            $labelColumn = $this->db->table($foreignTable)->labelColumn();

            if ($labelColumn !== null) {
                $text = "$model->$labelColumn";
            } else {
                $word = Word::labelUpperSingular($foreignTable);
                $text = "'$word #' . $model->$primaryKey";
            }

            $assertions = array_merge($assertions, [
                '',
                "    foreach ($models as $model) {",
                '        $this->assertHTML($this->xpath(',
                "            \"//select[@name='$name']/option[@value='%s' and .='%s']\",",
                "            $model->$primaryKey,",
                "            $text",
                '        ), $document);',
                '    }',
            ]);
        }

        $modelClass = Word::class($this->table->name(), true);

        return array_merge(
            $modelCreations,
            [
                '',
                "foreach (['/$tablename/create', factory($modelClass)->create()->path() . '/edit'] as \$path) {",
                '    $document = $this->getDOMDocument($this->get($path));',
            ],
            $assertions,
            [
                '}',
            ]
        );
    }

    public function assertManyToManyRelationships()
    {
        $modelCreationLines = collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;

                $foreignModels = Word::variable($foreignTable);
                $foreignClass = Word::class($foreignTable);

                $this->otherUses[] = $this->modelNamespace() . '\\' . $foreignClass;

                return "$foreignModels = factory($foreignClass::class, 3)->create();";
            })
            ->all();

        $assertionBlocks = collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;

                $foreignModels = Word::variable($foreignTable);
                $foreignModel = Word::variableSingular($foreignTable);

                $primaryKey = $this->db->table($foreignTable)->primaryKey();
                $labelColumn = $this->db->table($foreignTable)->labelColumn();

                $foreignLabel = $labelColumn !== null
                    ? "$foreignModel->$labelColumn"
                    : "'" . Word::labelUpperSingular($foreignTable) . " #' . $foreignModel->$primaryKey";

                $name = Word::kebab($foreignTable);

                return collect([
                    "    foreach ($foreignModels as $foreignModel) {",
                    '        $this->assertHTML($this->xpath(',
                    "            \"//select[@name='$name' and @multiple]/option[@value='%s' and .='%s']\",",
                    "            $foreignModel->$primaryKey,",
                    "            $foreignLabel",
                    '        ), $document);',
                    '    }',
                ]);
            });

        if ($assertionBlocks->count() === 0) {
            return [];
        }

        $assertionBlocks = $this->joinBlocks(...$assertionBlocks);

        $route = $this->tablename();
        $thisClass = Word::class($this->table->name(), true);

        return array_merge(
            $modelCreationLines,
            [
                '',
                "foreach (['/$route/create', factory($thisClass)->create()->path() . '/edit'] as \$path) {",
                '    $document = $this->getDOMDocument($this->get($path));',
                '',
            ],
            $assertionBlocks,
            [
                '}',
            ],
        );
    }

    public function assertEditFormHasValues()
    {
        $groups = $this->fieldsExceptPrimary()->groupBy(function ($column) {
            $type = $this->table->type($column);

            if ($this->table->reference($column) !== null) {
                return 'reference';
            } elseif ($type === Type::BOOLEAN) {
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
                    $this->modelVariableSingular()
                );

                if (Type::dateTimeFormat($this->table->type($column)) !== null) {
                    $value = $builder->formatAccessor($value, $column);
                }

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and @value='%s']", [$value]);
            });

        $checkboxInputs = $groups->get('checkbox', collect())
            ->flatMap(function ($column) {
                $value = "{$this->modelVariableSingular()}->$column";
                $name = $this->quoteName($column);

                $columnChecked = Word::variable($column) . 'Checked';

                return [
                    "$columnChecked = $value ? '@checked' : 'not(@checked)';",
                    $this->wrapXPath("//*[@name='$name' and $columnChecked]"),
                ];
            });

        $selectInputs = $groups->get('select', collect())
            ->map(function ($column) {
                $value = "{$this->modelVariableSingular()}->$column";

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name']/option[@value='%s' and @selected]", [$value]);
            });

        $textareaInputs = $groups->get('textarea', collect())
            ->map(function ($column) {
                $value = "{$this->modelVariableSingular()}->$column";

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and .='%s']", [$value]);
            });

        $referenceInputs = $groups->get('reference', collect())
            ->map(function ($column) {
                $value = "{$this->modelVariableSingular()}->$column";

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name']/option[@value='%s' and @selected]", [$value]);
            });

        return $this->joinBlocks($regularInputs, $checkboxInputs, $selectInputs, $textareaInputs, $referenceInputs);
    }

    public function oneConstraintField()
    {
        return $this->oneConstraintField = $this->fieldsExceptPrimary()->filter(function ($column) {
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
        return $this->fieldsExceptPrimary()
            ->map(function ($column) {
                $required = $this->table->required($column)
                    && $this->table->type($column) !== Type::BOOLEAN;

                $required = $required ? '@required' : 'not(@required)';

                $name = $this->quoteName($column);

                return $this->wrapXPath("//*[@name='$name' and $required]");
            })
            ->all();
    }

    public function updateNewWithManyToManyRelationships()
    {
        return collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;

                $name = Word::kebab($foreignTable);
                $foreignClass = Word::class($foreignTable, true);
                $primaryKey = $this->db->table($foreignTable)->primaryKey();

                return "\$new['$name'] = factory($foreignClass, 5)->create()->random(2)->pluck('$primaryKey')->all();";
            })
            ->all();
    }

    public function assertNewEqualsModel()
    {
        return $this->fieldsExceptPrimary()
            ->map(function ($column) {
                $expected = "\$new['$column']";
                $retrieved = "{$this->modelVariableSingular()}->$column";

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

    public function assertNewEqualsModelForManyToManyRelationships()
    {
        return collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;

                $name = Word::kebab($foreignTable);
                $model = Word::variableSingular($this->table->name());
                $method = Word::method($foreignTable);
                $primaryKey = $this->db->table($foreignTable)->primaryKey();

                return "\$this->assertEquals(\$new['$name'], $model->{$method}->pluck('$primaryKey')->all());";
            })
            ->all();
    }

    public function modelVariableStoreSomeManyToManyRelationships()
    {
        return collect($this->db->manyToMany($this->table->name()))
            ->map(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;
                $model = Word::variableSingular($this->table->name());
                $foreignClass = Word::class($foreignTable, true);
                $method = Word::method($foreignTable);

                return "$model->$method()->saveMany(factory($foreignClass, 5)->make());";
            })
            ->all();
    }

    public function assertFields()
    {
        $result = $this->generateInitialModelForUniqueChecks();

        $first = true;

        foreach ($this->fieldsExceptPrimary() as $column) {
            if (! $first) {
                $result = array_merge($result, ['']);
            }

            $result = array_merge($result, $this->assertField($column));

            $first = false;
        }

        return $result;
    }

    public function assertManyToManyFields()
    {
        return collect($this->db->manyToMany($this->table->name()))
            ->flatMap(function (ManyToMany $relationship) {
                $foreignTable = $relationship->foreignTwo;

                $name = Word::kebab($foreignTable);
                $foreignClass = Word::class($foreignTable);
                $primaryKey = $this->db->table($foreignTable)->primaryKey();

                return [
                    '',
                    "\$this->assertField('$name')",
                    '    ->accepts([])',
                    "    ->accepts(factory($foreignClass::class, 2)->create()->pluck('$primaryKey')->all())",
                    "    ->rejects([$foreignClass::all()->max('$primaryKey') + 1])",
                    '    ->accepts(null);',
                ];
            })
            ->all();
    }

    private function generateInitialModelForUniqueChecks()
    {
        $uniqueColumns = $this->fieldsExceptPrimary()->filter(function ($column) {
            return $this->table->unique($column);
        });

        if ($uniqueColumns->count() === 0) {
            return [];
        }

        $initialState = $uniqueColumns
            ->filter(function ($column) {
                // Fake only columns that do not have foreign keys on them
                return $this->table->reference($column) === null;
            })
            ->map(function ($column) {
                $fake = $this->fakeForUnique($column);

                return "    '$column' => $fake,";
            })
            ->all();

        $model = str_replace('_', ' ', $this->tablenameSingular());
        $modelVariable = Word::variableSingular($this->table->name());
        $factory = $this->fullFactory();

        if (count($initialState) > 0) {
            return array_merge(
                [
                    "// Create one $model to test fields that should contain unique values",
                    "$modelVariable = {$factory}->create([",
                ],
                $initialState,
                [
                    ']);',
                    '',
                ]
            );
        } else {
            return [
                "// Create one $model to test fields that should contain unique values",
                "$modelVariable = {$factory}->create();",
                '',
            ];
        }
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
        if (($reference = $this->table->reference($column)) !== null) {
            $assertions = $this->assertionsForReference($column, $reference[0], $reference[1]);
        } else {
            $assertions = $this->assertionsForRaw($column);
        }

        $assertions[] = $this->table->required($column)
            ? '    ->rejects(null);'
            : '    ->accepts(null);';

        return array_merge(
            [
                "\$this->assertField('$column')",
            ],
            $assertions
        );
    }

    private function assertionsForRaw(string $column)
    {
        $accepts = $this->fakeValid($column);
        $rejects = $this->fakeInvalid($column);

        if (array_key_exists($column, $this->fakes)) {
            $duplicateValue = array_shift($accepts);

            $rejects[] = [$duplicateValue, 'Duplicate values must be rejected'];
        }

        return array_merge(
            $this->stackFieldAssertion($accepts, 'accepts'),
            $this->stackFieldAssertion($rejects, 'rejects')
        );
    }

    private function assertionsForReference(string $column, string $foreignTable, string $foreignColumn)
    {
        $foreignClass = Word::class($foreignTable);

        $this->otherUses[] = $this->modelNamespace() . '\\' . $foreignClass;

        $result = [
            "    ->accepts(factory($foreignClass::class)->create()->$foreignColumn)",
            "    ->rejects($foreignClass::max('$foreignColumn') + 1)",
        ];

        if ($this->table->unique($column)) {
            $modelVariable = Word::variableSingular($this->table->name());

            $result = array_merge($result, [
                "    ->rejects($modelVariable->$column)",
            ]);
        }

        return $result;
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

    public function simpleFactory()
    {
        $modelClass = Word::class($this->table->name());

        if (app(VersionChecker::class)->before('8.0.0')) {
            return "factory($modelClass::class)";
        } else {
            return "$modelClass::factory()";
        }
    }

    public function fullFactory()
    {
        $modelClass = Word::class($this->table->name());

        if (app(VersionChecker::class)->before('8.0.0')) {
            return "factory($modelClass::class)->state('full')";
        } else {
            return "$modelClass::factory()->full()";
        }
    }

    public function fullFactoryForTwo()
    {
        $modelClass = Word::class($this->table->name());

        if (app(VersionChecker::class)->before('8.0.0')) {
            return "factory($modelClass::class, 2)->states('full')";
        } else {
            return "$modelClass::factory()->times(2)->full()";
        }
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

    private function quoteName(string $column)
    {
        return htmlentities(str_replace('_', '-', $column), ENT_QUOTES);
    }

    /**
     * @return string[]
     */
    private function joinBlocks(Collection $first, Collection ...$rest)
    {
        $lines = clone $first;

        foreach ($rest as $block) {
            if ($block->count() > 0) {
                if ($lines->count() > 0) {
                    $lines->push('');
                }

                $lines = $lines->merge($block);
            }
        }

        return $lines->all();
    }
}
