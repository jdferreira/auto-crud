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
            '$this->assertHTML("//textarea[@name=\'motto\' and text()=\'Draco dormiens nunquam titillandus\']", $document);',
            '$this->assertHTML("//input[@name=\'magical\' and @type=\'checkbox\' and @checked]", $document);',
            '$this->assertHTML("//select[@name=\'country\']/option[@name=\'uk\' and @selected]", $document);',
        ], $lines);
    }

    /** @test */
    public function it_handles_datetime_with_current_timestamp_as_default_value()
    {
        $generator = $this->generator(
            $this->mockTable('tablename', [
                'datetime' => [
                    'default' => 'CURRENT_TIMESTAMP',
                    'type' => Type::DATETIME,
                ],
                'date' => [
                    'default' => 'CURRENT_TIMESTAMP',
                    'type' => Type::DATE,
                ],
                'hour' => [
                    'default' => 'CURRENT_TIMESTAMP',
                    'type' => Type::TIME,
                ],
            ])
        );

        $lines = $generator->assertDefaultValuesOnCreateForm();

        $this->assertEquals([
            '\Carbon\Carbon::setTestNow(\'2020-01-01 01:02:03\');',
            '',
            '$this->assertHTML("//input[@name=\'datetime\' and @type=\'datetime\' and @value=\'2020-01-01 01:02:03\']", $document);',
            '$this->assertHTML("//input[@name=\'date\' and @type=\'date\' and @value=\'2020-01-01\']", $document);',
            '$this->assertHTML("//input[@name=\'hour\' and @type=\'time\' and @value=\'01:02:03\']", $document);',
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

            '$this->assertHTML($this->xpath("//*[@name=\'lunch\' and @value=\'%s\']", $student->lunch->format(\'H:i:s\')), $document);',
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
            '$this->assertHTML($this->xpath("//*[@name=\'notes\' and text()=\'%s\']", $student->notes), $document);',
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
            ['required' => false, 'expected' => null],
            ['type' => Type::INTEGER, 'expected' => "'???'"],
            ['type' => Type::BOOLEAN, 'expected' => "'???'"],
            ['type' => Type::DATETIME, 'expected' => "'???'"],
            ['type' => Type::DATE, 'expected' => "'???'"],
            ['type' => Type::TIME, 'expected' => "'???'"],
            ['type' => Type::DECIMAL, 'expected' => "'???'"],
            ['enum' => ['red', 'green', 'blue'], 'expected' => "'???'"],
            ['reference' => ['other', 'id'], 'expected' => "'???'"],
            ['name' => 'email', 'expected' => "'???'"],
            ['name' => 'uuid', 'expected' => "'???'"],
        ];

        foreach ($specs as $spec) {
            $columnName = Arr::pull($spec, 'name', 'column');
            $expectedInvalidValue = Arr::pull($spec, 'expected');

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
            public function it_starts_the_student_create_form_with_the_default_values()
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
                'name' => ['unique' => 'true'],
                'magical' => ['type' => Type::BOOLEAN, 'required' => false],
                'foundation_year' => ['type' => Type::INTEGER],
            ])
        );

        $lines = $generator->assertFields();

        $this->assertEquals([
            '// Create one school to test fields that should contain unique values',
            'factory(School::class)->create([',
            '    \'name\' => \'John Doe\',',
            ']);',
            '',
            '$this->assertField(\'name\')',
            '    ->accepts(\'Jane Doe\')',
            '    ->rejects(\'John Doe\') // Duplicate values must be rejected',
            '    ->rejects(null);',
            '',
            '$this->assertField(\'magical\')',
            '    ->accepts(true)',
            '    ->accepts(false)',
            '    ->rejects(\'yes\')',
            '    ->rejects(\'no\')',
            '    ->rejects(\'2\')',
            '    ->accepts(null);',
            '',
            '$this->assertField(\'foundation_year\')',
            '    ->accepts(0)',
            '    ->accepts(10)',
            '    ->accepts(-10)',
            '    ->rejects(\'3.14\')',
            '    ->rejects(\'not-a-number\')',
            '    ->rejects(null);',
        ], $lines);
    }
}
