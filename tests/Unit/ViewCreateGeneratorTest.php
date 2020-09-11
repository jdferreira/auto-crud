<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ViewCreateGenerator;

class ViewCreateGeneratorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param string $table
     *
     * @return ViewCreateGenerator
     */
    private function generator(string $table): ViewCreateGenerator
    {
        return app(ViewCreateGenerator::class, [
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator('users')->save();

        $this->assertFileExists(resource_path('views/users/create.blade.php'));
    }

    /** @test */
    public function it_shows_a_form()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<form method="POST">', $code);
        $this->assertStringContainsString('</form>', $code);
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator('users')->generate();
        $this->assertStringContainsString('<h1>New user</h1>', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertStringContainsString('<h1>New payment method</h1>', $code);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<label for="name">Name</label>', $code);
        $this->assertStringContainsString('<label for="email">Email</label>', $code);
        $this->assertStringContainsString('<label for="subscribed">Subscribed</label>', $code);
        $this->assertStringContainsString('<label for="birthday">Birthday</label>', $code);
        $this->assertStringContainsString('<label for="wake-up">Wake up</label>', $code);
    }

    /** @test */
    public function it_converts_names_to_kebab_case()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<label for="wake-up">Wake up</label>', $code);
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

        $code = $this->generator('users')->generate();
        $this->assertStringContainsString('<input name="name" required type="text">', $code);
        $this->assertStringContainsString('<input name="email" type="email">', $code);
        $this->assertStringContainsString('<input name="birthday" required type="date">', $code);
        $this->assertStringContainsString('<input name="wake-up" type="time">', $code);

        // Boolean fields are a little different
        $this->assertStringContainsString('<input name="subscribed" type="checkbox" value="1">', $code);

        $code = $this->generator('avatars')->generate();
        $this->assertStringContainsString('<input name="user-id" required type="text">', $code);
        $this->assertStringContainsString('<input name="file" required type="text">', $code);

        $code = $this->generator('products')->generate();
        $this->assertCodeContains('
            <select name="type">
                <option value="food">Food</option>
                <option value="stationery">Stationery</option>
                <option value="other">Other</option>
            </select>
        ', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertStringContainsString('<textarea name="primary" required></textarea>', $code);
    }

    /** @test */
    public function it_adds_a_hidden_field_with_value_0_for_checkboxes()
    {
        $code = $this->generator('users')->generate();
        $this->assertCodeContains('<input name="subscribed" type="checkbox" value="1">', $code);
        $this->assertCodeContains('<input name="subscribed" type="hidden" value="0">', $code);
    }

    /** @test */
    public function it_renders_numeric_fields_as_inputs_of_type_text()
    {
        $code = $this->generator('avatars')->generate();
        $this->assertStringContainsString('<input name="user-id" required type="text">', $code);
    }

    /** @test */
    public function it_renders_text_fields_named_email_as_inputs_of_type_email()
    {
        $code = $this->generator('users')->generate();
        $this->assertStringContainsString('<input name="email" type="email">', $code);
    }

    /** @test */
    public function it_fills_fields_with_default_values_with_that_value()
    {
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
        ]);

        $code = app(ViewCreateGenerator::class, [
            'table' => $table,
        ])->generate();

        $this->assertStringContainsString(
            '<input name="name" required type="text" value="Hogwarts">',
            $code
        );

        $this->assertStringContainsString(
            '<textarea name="motto" required>Draco dormiens nunquam titillandus</textarea>',
            $code
        );

        $this->assertStringContainsString(
            '<input name="magical" type="checkbox" value="1" checked>',
            $code
        );

        $this->assertStringContainsString(
            '<option value="uk" selected>',
            $code
        );
    }

    /** @test */
    public function it_renders_submit_button()
    {
        $code = $this->generator('users')->generate();
        $this->assertStringContainsString('<button type="submit">Submit</button>', $code);
    }
}
