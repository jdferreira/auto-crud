<?php

namespace Tests\Unit;

use Tests\TestCase;
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
            'table' => $table,
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

        $this->assertContains('<form method="POST">', $code);
        $this->assertContains('</form>', $code);
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator('users')->generate();
        $this->assertContains('<h1>New user</h1>', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertContains('<h1>New payment method</h1>', $code);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('<label for="name">Name</label>', $code);
        $this->assertContains('<label for="email">Email</label>', $code);
        $this->assertContains('<label for="subscribed">Subscribed</label>', $code);
        $this->assertContains('<label for="birthday">Birthday</label>', $code);
        $this->assertContains('<label for="wake-up">Wake up</label>', $code);
    }

    /** @test */
    public function it_converts_names_to_kebab_case()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('<label for="wake-up">Wake up</label>', $code);
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
        $this->assertContains('<input name="name" type="text">', $code);
        $this->assertContains('<input name="email" type="email">', $code);
        $this->assertContains('<input name="subscribed" type="checkbox">', $code);
        $this->assertContains('<input name="birthday" type="date">', $code);
        $this->assertContains('<input name="wake-up" type="time">', $code);

        $code = $this->generator('avatars')->generate();
        $this->assertContains('<input name="user-id" type="text">', $code);
        $this->assertContains('<input name="data" type="file">', $code);

        $code = $this->generator('products')->generate();
        $this->assertCodeContains('
            <select name="type">
                <option value="food">Food</option>
                <option value="stationery">Stationery</option>
                <option value="other">Other</option>
            </select>
        ', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertContains('<textarea name="comment"></textarea>', $code);
    }

    /** @test */
    public function it_renders_numeric_fields_as_inputs_of_type_text()
    {
        $code = $this->generator('avatars')->generate();
        $this->assertContains('<input name="user-id" type="text">', $code);
    }

    /** @test */
    public function it_renders_text_fields_named_email_as_inputs_of_type_email()
    {
        $code = $this->generator('users')->generate();
        $this->assertContains('<input name="email" type="email">', $code);
    }

    /** @test */
    public function it_renders_submit_button()
    {
        $code = $this->generator('users')->generate();
        $this->assertContains('<button type="submit">Submit</button>', $code);
    }
}
