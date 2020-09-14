<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCreateGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ViewCreateGenerator
     */
    private function generator(TableInformation $table): ViewCreateGenerator
    {
        return app(ViewCreateGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(resource_path('views/students/create.blade.php'));
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

        $this->assertStringContainsString('<h1>New student</h1>', $code);

        $code = $this->generator(
            $this->mockTable('staff_facilities')
        )->generate();

        $this->assertStringContainsString('<h1>New staff facility</h1>', $code);
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
        // NOTICE: I'm not testing for
        // - <input type="number"> because this is broken and doesn't work in
        //   some browsers; we'll render it as a regular type="text" input
        // - <input type="password"> because CRUD applications have no need for
        //   a password field
        // - <input type="tel"> because I don't have a test for it; but I should
        //   add one in the future! TODO: Add this.

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

        $this->assertStringContainsString('<textarea name="notes"></textarea>', $code);
        $this->assertStringContainsString('<input name="name" required type="text">', $code);
        $this->assertStringContainsString('<input name="birthday" required type="date">', $code);
        $this->assertStringContainsString('<input name="height" required type="text">', $code);
        $this->assertStringContainsString('<input name="current-year" required type="text">', $code);
        $this->assertStringContainsString('<input name="letter-sent-at" required type="datetime">', $code);
        $this->assertStringContainsString('<input name="preferred-lunch-time" required type="time">', $code);

        // Boolean fields are a little different
        $this->assertStringContainsString('<input name="has-pet" type="checkbox" value="1">', $code);

        // Dropdown for the enum fields
        $this->assertCodeContains('
            <select name="house" required>
                <option value="gryffindor">Gryffindor</option>
                <option value="slytherin">Slytherin</option>
                <option value="ravenclaw">Ravenclaw</option>
                <option value="hufflepuff">Hufflepuff</option>
            </select>
        ', $code);
    }

    /** @test */
    public function it_adds_a_hidden_field_with_value_0_for_checkboxes()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'has_pet' => ['type' => Type::BOOLEAN],
            ])
        )->generate();

        $this->assertCodeContains('<input name="has-pet" type="checkbox" value="1">', $code);
        $this->assertCodeContains('<input name="has-pet" type="hidden" value="0">', $code);
    }

    /** @test */
    public function it_fills_fields_with_default_values_with_that_value()
    {
        $code = $this->generator(
            $this->mockTable('schools', [
                'name' => ['type' => Type::STRING, 'default' => 'Hogwarts'],
                'motto' => ['type' => Type::TEXT, 'default' => 'Draco dormiens nunquam titillandus'],
                'magical' => ['type' => Type::BOOLEAN, 'default' => true],
                'country' => ['enum' => ['uk', 'fr', 'de'], 'default' => 'uk'],
                'start_of_next_year' => ['type' => Type::DATE, 'default' => 'CURRENT_TIMESTAMP'],
            ])
        )->generate();

        $this->assertStringContainsString('<input name="name" required type="text" value="Hogwarts">', $code);
        $this->assertStringContainsString('<textarea name="motto" required>Draco dormiens nunquam titillandus</textarea>', $code);
        $this->assertStringContainsString('<input name="magical" type="checkbox" value="1" checked>', $code);
        $this->assertStringContainsString('<option value="uk" selected>', $code);
        $this->assertStringContainsString('<input name="start-of-next-year" required type="date" value="{{ now() }}">', $code);
    }

    /** @test */
    public function it_renders_submit_button()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('<button type="submit">Submit</button>', $code);
    }
}
