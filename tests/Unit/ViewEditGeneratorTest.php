<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ViewEditGenerator;

class ViewEditGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ViewEditGenerator
     */
    private function generator(TableInformation $table): ViewEditGenerator
    {
        return app(ViewEditGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(resource_path('views/students/edit.blade.php'));
    }

    /** @test */
    public function it_shows_a_form()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('<form method="POST">', $code);
        $this->assertStringContainsString('</form>', $code);
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('<h1>Edit student</h1>', $code);

        $code = $this->generator(
            $this->mockTable('staff_facilities')
        )->generate();

        $this->assertStringContainsString('<h1>Edit staff facility</h1>', $code);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'id' => ['primaryKey' => true],
                'name' => [],
                'house' => [],
            ])
        )->generate();

        $this->assertStringContainsString('<label for="name">Name</label>', $code);
        $this->assertStringContainsString('<label for="house">House</label>', $code);

        $this->assertStringNotContainsString('<label for="id">', $code);
    }

    /** @test */
    public function it_converts_names_to_kebab_case()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'has_pet' => ['type' => Type::BOOLEAN],
            ])
        )->generate();

        $this->assertStringContainsString('<label for="has-pet">Has pet</label>', $code);
    }

    /** @test */
    public function it_renders_input_fields_according_to_field_type()
    {
        // TODO: These should have better tests that do not depend on the order
        // of attributes, and such. For the time being, let's not worry about
        // it, though.

        $code = $this->generator(
            $this->mockTable('students', [
                'notes' => ['type' => Type::TEXT, 'required' => false],
                'name' => ['type' => Type::STRING],
                'birthday' => ['type' => Type::DATE],
                'height' => ['type' => Type::DECIMAL],
                'has_pet' => ['type' => Type::BOOLEAN],
                'current_year' => ['type' => Type::INTEGER],
                'letter_sent_at' => ['type' => Type::DATETIME],
                'preferred_lunch_time' => ['type' => Type::TIME],
                'house' => ['enum' => ['gryffindor', 'slytherin', 'ravenclaw', 'hufflepuff']],
            ])
        )->generate();

        $this->assertRegExp('/<textarea name="notes">.*<\/textarea>/', $code);
        $this->assertRegExp('/<input name="name" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="birthday" required value=".*" type="date">/', $code);
        $this->assertRegExp('/<input name="height" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="current-year" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="letter-sent-at" required value=".*" type="datetime">/', $code);
        $this->assertRegExp('/<input name="preferred-lunch-time" required value=".*" type="time">/', $code);

        $this->assertRegExp('/<input name="has-pet" .* type="checkbox" value="1">/', $code);
        $this->assertRegExp('/<input name="has-pet" type="hidden" value="0">/', $code);

        $this->assertRegExp('/<select name="house" required>/', $code);
        $this->assertRegExp('/<option value="gryffindor" .*>Gryffindor<\/option>/', $code);
        $this->assertRegExp('/<option value="slytherin" .*>Slytherin<\/option>/', $code);
        $this->assertRegExp('/<option value="ravenclaw" .*>Ravenclaw<\/option>/', $code);
        $this->assertRegExp('/<option value="hufflepuff" .*>Hufflepuff<\/option>/', $code);
    }

    /** @test */
    public function it_adds_a_hidden_field_with_value_0_for_checkboxes()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'has_pet' => ['type' => Type::BOOLEAN],
            ])
        )->generate();

        $this->assertCodeContains('
            <input name="has-pet" {{ (old(\'has-pet\') ?? $student->has_pet) ? \'checked\' : \'\' }} type="checkbox" value="1">
            <input name="has-pet" type="hidden" value="0">
        ', $code);
    }

    /** @test */
    public function it_binds_the_model_attributes_to_the_input_values()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'notes' => ['type' => Type::TEXT, 'required' => false],
                'name' => ['type' => Type::STRING],
                'birthday' => ['type' => Type::DATE],
                'height' => ['type' => Type::DECIMAL],
                'has_pet' => ['type' => Type::BOOLEAN],
                'current_year' => ['type' => Type::INTEGER],
                'letter_sent_at' => ['type' => Type::DATETIME],
                'preferred_lunch_time' => ['type' => Type::TIME],
                'house' => ['enum' => ['gryffindor', 'slytherin', 'ravenclaw', 'hufflepuff']],
            ])
        )->generate();

        $this->assertStringContainsString('old(\'notes\') ?? $student->notes', $code);
        $this->assertStringContainsString('old(\'name\') ?? $student->name', $code);
        $this->assertStringContainsString('old(\'birthday\') ?? $student->birthday->format(\'Y-m-d\')', $code);
        $this->assertStringContainsString('old(\'height\') ?? $student->height', $code);
        $this->assertStringContainsString('old(\'has-pet\') ?? $student->has_pet', $code);
        $this->assertStringContainsString('old(\'current-year\') ?? $student->current_year }}', $code);
        $this->assertStringContainsString('old(\'letter-sent-at\') ?? $student->letter_sent_at->format(\'Y-m-d H:i:s\')', $code);
        $this->assertStringContainsString('old(\'preferred-lunch-time\') ?? $student->preferred_lunch_time', $code);
        $this->assertStringContainsString('old(\'house\') ?? $student->house', $code);
    }

    /** @test */
    public function it_renders_a_submit_button()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('<button type="submit">Submit</button>', $code);
    }

    /** @test */
    public function it_renders_many_to_many_relationships()
    {
        $students = $this->mockTable('students', [
            'id' => ['primaryKey' => true],
        ]);

        $classes = $this->mockTable('classes', [
            'id' => ['primaryKey' => true, 'type' => Type::INTEGER],
            'name' => [],
        ]);

        $pivot = $this->mockTable('class_student', [
            'student_id' => ['reference' => ['students', 'id']],
            'class_id' => ['reference' => ['classes', 'id']],
        ]);

        $this->mockDatabase($students, $classes, $pivot);

        $this->assertCodeContains('
            <label for="classes">Classes</label>
            <select name="classes" multiple>
                @foreach (\App\Class::all() as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
        ', $this->generator($students)->generate());
    }
}
