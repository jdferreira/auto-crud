<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ViewEditGenerator;

class ViewEditGeneratorTest extends TestCase
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
     * @return ViewEditGenerator
     */
    private function generator(string $table): ViewEditGenerator
    {
        return app(ViewEditGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator('users')->save();

        $this->assertFileExists(resource_path('views/users/edit.blade.php'));
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

        $this->assertStringContainsString('<h1>Edit user</h1>', $code);
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

        // TODO: These should have better tests that do not depend on the order
        // of attributes, and such. For the time being, let's not worry about
        // it, though.

        $code = $this->generator('users')->generate();
        $this->assertRegExp('/<input name="name" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="email" value=".*" type="email">/', $code);
        $this->assertRegExp('/<input name="birthday" required value=".*" type="date">/', $code);
        $this->assertRegExp('/<input name="wake-up" value=".*" type="time">/', $code);

        // Boolean fields are a little different
        $this->assertRegExp('/<input name="subscribed" .*? type="checkbox" value="1">/', $code);

        $code = $this->generator('avatars')->generate();
        $this->assertRegExp('/<input name="user-id" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="file" required value=".*" type="text">/', $code);
        $this->assertRegExp('/<input name="data" required value=".*" type="file">/', $code);

        $code = $this->generator('products')->generate();
        // We split the code contains assertion in two parts so that we are not
        // testing the value attribute here.
        $this->assertRegExp('/<select name="type" value=".*">/', $code);
        $this->assertCodeContains('
                <option value="food">Food</option>
                <option value="stationery">Stationery</option>
                <option value="other">Other</option>
            </select>
        ', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertRegExp('/<textarea name="primary" required>.*?<\/textarea>/', $code);
    }

    /** @test */
    public function it_adds_a_hidden_field_with_value_0_for_checkboxes()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('<input name="subscribed" {{ (old(\'subscribed\') ?? $user->subscribed ) ? \'checked\' : \'\' }} type="checkbox" value="1">', $code);
        $this->assertCodeContains('<input name="subscribed" type="hidden" value="0">', $code);
    }

    /** @test */
    public function it_renders_numeric_fields_as_inputs_of_type_text()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertRegExp('/<input name="user-id" required value=".*?" type="text">/', $code);
    }

    /** @test */
    public function it_renders_text_fields_named_email_as_inputs_of_type_email()
    {
        $code = $this->generator('users')->generate();

        $this->assertRegExp('/<input name="email" value=".*?" type="email">/', $code);
    }

    /** @test */
    public function it_binds_the_model_attributes_to_the_input_values()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('value="{{ old(\'name\') ?? $user->name }}"', $code);
        $this->assertStringContainsString('value="{{ old(\'email\') ?? $user->email }}"', $code);
        $this->assertStringContainsString('value="{{ old(\'birthday\') ?? $user->birthday }}"', $code);
        $this->assertStringContainsString('value="{{ old(\'wake-up\') ?? $user->wake_up }}"', $code);

        // Boolean fields are a little different
        $this->assertStringContainsString("{{ (old('subscribed') ?? \$user->subscribed ) ? 'checked' : '' }}", $code);

        $code = $this->generator('products')->generate();
        // Note that we split this next assertion in two so that we are not
        // testing the value attribute here.
        $this->assertCodeContains('<select name="type" value="{{ old(\'type\') ?? $product->type }}">', $code);

        $code = $this->generator('payment_methods')->generate();
        $this->assertStringContainsString('<textarea name="primary" required>{{ old(\'primary\') ?? $paymentMethod->primary }}</textarea>', $code);
    }

    /** @test */
    public function it_renders_a_submit_button()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<button type="submit">Submit</button>', $code);
    }
}
