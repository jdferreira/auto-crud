<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Ferreira\AutoCrud\Generators\TestGenerator;
use Ferreira\AutoCrud\Database\TableInformation;

class TestGeneratorTest extends TestCase
{
    // TODO: I don't necessarily like how this code turned out. I'm testing the
    // inner methods of the generator, but either the names of the tests don't
    // match what is actually being tested, or the tests seem insufficient or
    // inelegant. I will most likely want to revisit this test case.

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return TestGenerator
     */
    private function generator(TableInformation $table): TestGenerator
    {
        return app(TestGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_test()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(base_path('tests/Feature/StudentsCrudTest.php'));
    }

    /** @test */
    public function it_detects_referenced_models_qualified_name()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('use App\Student;', $code);

        $code = $this->generator(
            $this->mockTable('students')
        )->setModelDirectory('Models')->generate();

        $this->assertCodeContains('use App\Models\Student;', $code);
    }

    /** @test */
    public function it_defines_a_testcase()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('use Tests\TestCase;', $code);
        $this->assertCodeContains('class StudentsCrudTest extends TestCase', $code);
    }

    /** @test */
    public function it_uses_the_necessary_traits()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('use Ferreira\AutoCrud\AssertsHTML;', $code);
        $this->assertCodeContains('use Ferreira\AutoCrud\AssertsField;', $code);
        $this->assertCodeContains('use Illuminate\Foundation\Testing\RefreshDatabase;', $code);

        $this->assertCodeContains('
            use RefreshDatabase,
                AssertsHTML,
                AssertsField;
        ', $code);
    }

    /** @test */
    public function it_uses_the_table_name_on_all_test_methods()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        preg_match_all('/\/\*\* @test \*\/\n *.*function \w+/', $code, $matches);

        foreach ($matches[0] as $match) {
            $this->assertRegExp('/(?:_|\b)(?:students|a_student|the_student)(?:_|\b)/', $match);
        }
    }

    /** @test */
    public function it_generates_valid_PHP_code()
    {
        // TODO: Make an `assertIsValidCode` in `TestCase`, and use it here, in
        // `MigrationGeneratorTest`, and in all generators and injectors
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $cmd = 'php -l';
        $specs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $specs, $pipes);

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        // Apparently we need to read the STDOUT pipe, or the process fails with a 255 code
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        $this->assertEquals(0, $exitCode, 'File contains syntax errors:' . PHP_EOL . $errors);
    }

    /** @test */
    public function it_tests_that_the_columns_appear_on_the_index_and_show_views()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'name' => [],
            ])
        );

        $lines = $generator->assertSeeColumnValuesOnIndexOrShow();

        $this->assertEquals([
            '->assertSeeText($student->name)',
        ], $lines);
    }

    /** @test */
    public function it_uses_kebab_case_on_the_html_name_attribute()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'current_year' => ['type' => Type::INTEGER],
            ])
        );

        $this->assertStringContainsString(
            'current-year',
            implode("\n", $generator->assertHTMLOnForm())
        );

        // TODO: What other methods generate HTML name attributes? Test them here.
    }

    /** @test */
    public function it_tests_that_fields_appear_on_create_and_edit_forms()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'lunch' => ['type' => Type::TIME],
                'year' => ['type' => Type::INTEGER],
                'squib' => ['type' => Type::BOOLEAN],
                'birthday' => ['type' => Type::DATE],
                'height' => ['type' => Type::DECIMAL],
                'letter' => ['type' => Type::DATETIME],
                'gender' => ['enum' => ['male', 'female']],
                'school' => ['reference' => ['schools', 'id']],
            ])
        );

        $lines = $generator->assertHTMLOnForm();

        $this->assertEquals([
            '$this->assertHTML("//input[@name=\'lunch\' and @type=\'time\']", $document);',
            '$this->assertHTML("//input[@name=\'year\' and @type=\'text\']", $document);',
            '$this->assertHTML("//input[@name=\'squib\' and @type=\'checkbox\']", $document);',
            '$this->assertHTML("//input[@name=\'birthday\' and @type=\'date\']", $document);',
            '$this->assertHTML("//input[@name=\'height\' and @type=\'text\']", $document);',
            '$this->assertHTML("//input[@name=\'letter\' and @type=\'datetime\']", $document);',
            '',
            '$this->assertHTML("//select[@name=\'gender\']", $document);',
            '$this->assertHTML("//select[@name=\'gender\']/option[@value=\'male\']", $document);',
            '$this->assertHTML("//select[@name=\'gender\']/option[@value=\'female\']", $document);',
            '',
            '$this->assertHTML("//select[@name=\'school\']", $document);',
        ], $lines);
    }

    /** @test */
    public function it_tests_that_the_create_form_starts_with_the_default_values()
    {
        $generator = $this->generator(
            $table = $this->mockTable('schools', [
                'name' => [
                    'type' => Type::STRING,
                    'default' => 'Hogwarts',
                ],
                'motto' => [
                    'type' => Type::TEXT,
                    'default' => 'Draco dormiens nunquam titillandus',
                ],
                'magical' => [
                    'type' => Type::BOOLEAN,
                    'default' => true,
                ],
                'country' => [
                    'enum' => ['uk', 'fr', 'de'],
                    'default' => 'uk',
                ],
            ])
        );

        $lines = $generator->assertDefaultValuesOnCreateForm();

        $this->assertEquals([
            '$this->assertHTML("//input[@name=\'name\' and @type=\'text\' and @value=\'Hogwarts\']", $document);',
            '$this->assertHTML("//textarea[@name=\'motto\' and .=\'Draco dormiens nunquam titillandus\']", $document);',
            '$this->assertHTML("//input[@name=\'magical\' and @type=\'checkbox\' and @checked]", $document);',
            '$this->assertHTML("//select[@name=\'country\']/option[@name=\'uk\' and @selected]", $document);',
        ], $lines);
    }

    /** @test */
    public function it_does_not_test_default_values_on_the_create_form_when_the_table_has_none()
    {
        $code = $this->generator(
            $this->mockTable('student', [
                'pet_name' => ['default' => null],
            ])
        )->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_starts_the_student_create_form_with_the_default_values()
        ', $code);

        $code = $this->generator(
            $this->mockTable('student', [
                'pet_name' => [],
            ])
        )->generate();

        $this->assertCodeNotContains('
            /** @test */
            public function it_starts_the_student_create_form_with_the_default_values()
        ', $code);
    }

    /** @test */
    public function it_tests_current_values_on_edit_form()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'notes' => ['type' => Type::TEXT],
                'lunch' => ['type' => Type::TIME],
                'year' => ['type' => Type::INTEGER],
                'squib' => ['type' => Type::BOOLEAN],
                'birthday' => ['type' => Type::DATE],
                'height' => ['type' => Type::DECIMAL],
                'letter' => ['type' => Type::DATETIME],
                'gender' => ['enum' => ['male', 'female']],
            ])
        );

        $lines = $generator->assertEditFormHasValues();

        $this->assertEquals([
            // TODO: I want again to group checkboxes and not checkboxes, and
            // not group all different types of input in their own block.

            '$this->assertHTML($this->xpath("//*[@name=\'lunch\' and @value=\'%s\']", $student->lunch), $document);',
            '$this->assertHTML($this->xpath("//*[@name=\'year\' and @value=\'%s\']", $student->year), $document);',
            '$this->assertHTML($this->xpath("//*[@name=\'birthday\' and @value=\'%s\']", $student->birthday->format(\'Y-m-d\')), $document);',
            '$this->assertHTML($this->xpath("//*[@name=\'height\' and @value=\'%s\']", $student->height), $document);',
            '$this->assertHTML($this->xpath("//*[@name=\'letter\' and @value=\'%s\']", $student->letter->format(\'Y-m-d H:i:s\')), $document);',
            '',
            '$squibChecked = $student->squib ? \'@checked\' : \'not(@checked)\';',
            '$this->assertHTML("//*[@name=\'squib\' and $squibChecked]", $document);',
            '',
            '$this->assertHTML($this->xpath("//*[@name=\'gender\']/option[@value=\'%s\' and @selected]", $student->gender), $document);',
            '',
            '$this->assertHTML($this->xpath("//*[@name=\'notes\' and .=\'%s\']", $student->notes), $document);',
        ], $lines);
    }

    /** @test */
    public function it_tests_old_values_from_a_previous_form_submission()
    {
        // A constraint field is one that fits one of the following:
        // - required
        // - an integer
        // - a boolean
        // - a datetime, date, time
        // - a decimal
        // - an enum
        // - a key to a foreign table
        // - an email or UUID

        // Given a table with a column that is constraint, and the expected
        // invalid value, assert that `$generator->oneConstraintField()` returns
        // that column and that `$generator->oneInvalidValue()` returns the
        // expected invalid value.
        $specs = [
            ['required' => true, 'type' => Type::STRING, 'invalid' => "''"],
            ['required' => true, 'type' => Type::TEXT, 'invalid' => "''"],
            ['required' => true, 'type' => Type::INTEGER, 'invalid' => "''"],
            ['required' => true, 'name' => 'email', 'invalid' => "''"],
            ['required' => false, 'type' => Type::STRING, 'invalid' => null],
            ['required' => false, 'type' => Type::TEXT, 'invalid' => null],
            ['required' => false, 'type' => Type::INTEGER, 'invalid' => "'???'"],
            ['required' => false, 'type' => Type::BOOLEAN, 'invalid' => "'???'"],
            ['required' => false, 'type' => Type::DATETIME, 'invalid' => "'???'"],
            ['required' => false, 'type' => Type::DATE, 'invalid' => "'???'"],
            ['required' => false, 'type' => Type::TIME, 'invalid' => "'???'"],
            ['required' => false, 'type' => Type::DECIMAL, 'invalid' => "'???'"],
            ['required' => false, 'enum' => ['red', 'green', 'blue'], 'invalid' => "'???'"],
            ['required' => false, 'reference' => ['other', 'id'], 'invalid' => "'???'"],
            ['required' => false, 'name' => 'email', 'invalid' => "'???'"],
            ['required' => false, 'name' => 'uuid', 'invalid' => "'???'"],
        ];

        foreach ($specs as $spec) {
            $columnName = Arr::pull($spec, 'name', 'column');
            $expectedInvalidValue = Arr::pull($spec, 'invalid');

            $generator = $this->generator(
                $this->mockTable('students', [
                    $columnName => $spec,
                ])
            );

            // The expected invalid value is null to sentinel the case where
            // there is no actual constraint field. In that case, we want to
            // test that the constraint column is `null`, and not the column
            // that we are working with at the moment.
            if ($expectedInvalidValue === null) {
                $columnName = null;
            }

            $this->assertEquals(
                $columnName,
                $generator->oneConstraintField(),
                sprintf('Expecting to see column \'%s\' as constrained for the spec %s', $columnName, var_export($spec, true))
            );
            $this->assertEquals(
                $expectedInvalidValue,
                $generator->oneInvalidValue(),
                sprintf('Expecting to see expected value \'%s\' as invalid for the spec %s', $expectedInvalidValue, var_export($spec, true))
            );
        }
    }

    /** @test */
    public function it_does_not_test_for_old_values_when_all_fields_are_unconstraint()
    {
        $code = $this->generator(
            $this->mockTable('student', [
                'pet_name' => ['required' => true],
            ])
        )->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_keeps_old_values_on_unsuccessful_student_update()
        ', $code);

        $code = $this->generator(
            $this->mockTable('student', [
                'pet_name' => ['required' => false],
            ])
        )->generate();

        $this->assertCodeNotContains('
            /** @test */
            public function it_keeps_old_values_on_unsuccessful_student_update()
        ', $code);
    }

    /** @test */
    public function it_tests_required_fields()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'name' => ['required' => true],
                'pet' => ['required' => false],
                'alive' => ['required' => true, 'type' => Type::BOOLEAN],
            ])
        );

        $lines = $generator->assertRequiredFields();

        $this->assertEquals([
            '$this->assertHTML("//*[@name=\'name\' and @required]", $document);',
            '$this->assertHTML("//*[@name=\'pet\' and not(@required)]", $document);',
            '$this->assertHTML("//*[@name=\'alive\' and not(@required)]", $document);',
        ], $lines);
    }

    /** @test */
    public function it_tests_that_created_or_updated_models_equal_the_request_input_data()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'name' => ['type' => Type::STRING],
                'birthday' => ['type' => Type::DATE],
            ])
        );

        $lines = $generator->assertNewEqualsModel();

        $this->assertEquals([
            '$this->assertEquals($new[\'name\'], $student->name);',
            '$this->assertEquals($new[\'birthday\'], $student->birthday->format(\'Y-m-d\'));',
        ], $lines);
    }

    /** @test */
    public function it_tests_specific_field_values()
    {
        $generator = $this->generator(
            $this->mockTable('schools', [
                'name' => [],
                'magical' => [
                    'type' => Type::BOOLEAN,
                    'required' => false,
                ],
                'foundation_year' => [
                    'type' => Type::INTEGER,
                ],
                'country_id' => [
                    'type' => Type::INTEGER,
                    'reference' => ['countries', 'id'],
                ],
            ])
        );

        $lines = $generator->assertFields();

        $this->assertEquals([
            "\$this->assertField('name')",
            "    ->accepts('John Doe')",
            "    ->accepts('Jane Doe')",
            '    ->rejects(null);',
            '',
            "\$this->assertField('magical')",
            '    ->accepts(true)',
            '    ->accepts(false)',
            "    ->rejects('yes')",
            "    ->rejects('no')",
            "    ->rejects('2')",
            '    ->accepts(null);',
            '',
            "\$this->assertField('foundation_year')",
            '    ->accepts(0)',
            '    ->accepts(10)',
            '    ->accepts(-10)',
            "    ->rejects('3.14')",
            "    ->rejects('not-a-number')",
            '    ->rejects(null);',
            '',
            "\$this->assertField('country_id')",
            '    ->accepts(factory(Country::class)->create()->id)',
            "    ->rejects(Country::max('id') + 1)",
            '    ->rejects(null);',
        ], $lines);
    }

    /** @test */
    public function it_rejects_duplicate_values_on_unique_fields()
    {
        $generator = $this->generator(
            $this->mockTable('schools', [
                'name' => [
                    'unique' => true,
                ],
                'country_id' => [
                    'type' => Type::INTEGER,
                    'reference' => ['countries', 'id'],
                    'unique' => true,
                ],
            ])
        );

        $lines = $generator->assertFields();

        $this->assertEquals([
            '// Create one school to test fields that should contain unique values',
            "\$school = factory(School::class)->state('full_model')->create([",
            "    'name' => 'John Doe',",
            ']);',
            '',
            "\$this->assertField('name')",
            "    ->accepts('Jane Doe')",
            "    ->rejects('John Doe') // Duplicate values must be rejected",
            '    ->rejects(null);',
            '',
            "\$this->assertField('country_id')",
            '    ->accepts(factory(Country::class)->create()->id)',
            "    ->rejects(Country::max('id') + 1)",
            '    ->rejects($school->country_id)',
            '    ->rejects(null);',
        ], $lines);
    }

    /** @test */
    public function it_creates_a_model_even_if_all_the_unique_fields_are_foreign_keys()
    {
        $generator = $this->generator(
            $this->mockTable('schools', [
                'country_id' => [
                    'type' => Type::INTEGER,
                    'reference' => ['countries', 'id'],
                    'unique' => true,
                ],
            ])
        );

        $lines = $generator->assertFields();

        $this->assertEquals([
            '// Create one school to test fields that should contain unique values',
            "\$school = factory(School::class)->state('full_model')->create();",
        ], array_slice($lines, 0, 2));
    }

    /** @test */
    public function it_uses_models_needed_for_validation()
    {
        $generator = $this->generator(
            $this->mockTable('schools', [
                'country' => ['type' => Type::INTEGER, 'reference' => ['countries', 'id']],
            ])
        );

        $generator->assertFields(); // This finds the other models to use
        $lines = $generator->otherUses();

        $this->assertEquals([
            'use App\Country;',
        ], $lines);
    }

    /** @test */
    public function it_uses_carbon_and_sets_test_now_when_needed()
    {
        $generator = $this->generator(
            $this->mockTable('schools', [
                'start_of_next_year' => ['type' => Type::DATE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
        );

        $this->assertEquals(
            'Carbon::setTestNow(\'2020-01-01 01:02:03\');',
            $generator->setTime()
        );

        // Recreate the generator to ensure that the state of the otherUses is
        // not kept between calls.
        $generator = $this->generator(
            $this->mockTable('schools', [
                'start_of_next_year' => ['type' => Type::DATE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
        );

        $this->assertStringContainsString(
            'use Carbon\Carbon;',
            $generator->generate()
        );
    }

    /** @test */
    public function it_does_not_incude_primary_keys_on_fields()
    {
        $generator = $this->generator(
            $this->mockTable('students', [
                'student_code' => ['primaryKey' => true],
            ])
        );

        $this->assertNotContains('student_code', $generator->fieldsExceptPrimary());
    }

    /** @test */
    public function it_populates_foreign_keys_on_create_and_edit_forms()
    {
        $students = $this->mockTable('students', [
            'id' => ['primaryKey' => true],
            'name' => [],
        ]);

        $pets = $this->mockTable('pets', [
            'student_id' => ['reference' => ['students', 'id']],
        ]);

        $this->mockDatabase($students, $pets);

        $generator = $this->generator($pets);

        $this->assertEquals([
            '$students = factory(Student::class, 30)->create();',
            '',
            'foreach ([\'create\', \'edit\'] as $path) {',
            '    $document = $this->getDOMDocument($this->get("/pets/$path"));',
            '',
            '    foreach ($students as $student) {',
            '        $this->assertHTML($this->xpath(',
            '            "//select[@name=\'student-id\']/option[@value=\'%s\' and text()=\'%s\']",',
            '            $student->id,',
            '            $student->name',
            '        ), $document);',
            '    }',
            '}',
        ], $generator->assertForeignFieldsPopulated());
    }

    /** @test */
    public function it_renders_the_foreign_key_test_on_create_and_edit_forms_only_if_needed()
    {
        $students = $this->mockTable('students', [
            'id' => ['primaryKey' => true],
            'name' => [],
        ]);

        $pets = $this->mockTable('pets', [
            'student_id' => [
                'reference' => ['students', 'id'],
            ],
        ]);

        $this->mockDatabase($students, $pets);

        $this->assertCodeContains('
            /** @test */
            public function it_populates_foreign_keys_on_the_create_and_edit_forms_of_pets()
        ', $this->generator($pets)->generate());

        $pets = $this->mockTable('pets', [
            'name' => [],
        ]);

        $this->assertCodeNotContains('
            /** @test */
            public function it_populates_foreign_keys_on_the_create_and_edit_forms_of_pets()
        ', $this->generator($pets)->generate());
    }

    /** @test */
    public function it_populates_the_foreign_keys_of_the_create_and_edit_forms_that_do_not_have_labels_with_the_id()
    {
        $students = $this->mockTable('students', [
            'id' => ['primaryKey' => true, 'type' => Type::INTEGER],
        ]);

        $pets = $this->mockTable(
            'pets',
            [
                'student_id' => ['reference' => ['students', 'id']],
            ]
        );

        $this->mockDatabase($students, $pets);

        $generator = $this->generator($pets);

        $this->assertEquals([
            '$students = factory(Student::class, 30)->create();',
            '',
            'foreach ([\'create\', \'edit\'] as $path) {',
            '    $document = $this->getDOMDocument($this->get("/pets/$path"));',
            '',
            '    foreach ($students as $student) {',
            '        $this->assertHTML($this->xpath(',
            '            "//select[@name=\'student-id\']/option[@value=\'%s\' and text()=\'%s\']",',
            '            $student->id,',
            '            \'Student #\' . $student->id',
            '        ), $document);',
            '    }',
            '}',
        ], $generator->assertForeignFieldsPopulated());
    }
}
