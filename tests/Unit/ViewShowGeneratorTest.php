<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ViewShowGenerator;

class ViewShowGeneratorTest extends TestCase
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
     * @return ViewShowGenerator
     */
    private function generator(string $table): ViewShowGenerator
    {
        return app(ViewShowGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator('users')->save();

        $this->assertFileExists(resource_path('views/users/show.blade.php'));
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<h1>User</h1>', $code);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<th>Name</th>', $code);
        $this->assertStringContainsString('<th>Email</th>', $code);
        $this->assertStringContainsString('<th>Subscribed</th>', $code);
        $this->assertStringContainsString('<th>Birthday</th>', $code);
        $this->assertStringContainsString('<th>Wake up</th>', $code);
    }

    /** @test */
    public function it_renders_field_values_according_to_field_type()
    {
        // TODO: These should have better tests that do not depend on the order
        // of attributes, and such. For the time being, let's not worry about
        // it, though.

        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('<td>{{ $user->name }}</td>', $code);
        $this->assertStringContainsString('<td>{{ $user->email }}</td>', $code);
        $this->assertStringContainsString("<td>{{ \$user->subscribed ? '&#10004;' : '&#10008;' }}</td>", $code);
        $this->assertStringContainsString("<td>{{ \$user->birthday->format('Y-m-d') }}</td>", $code);
        $this->assertStringContainsString("<td>{{ \$user->wake_up === null ? \$user->wake_up->format('H:i:s') : null }}</td>", $code);
    }

    /** @test */
    public function it_contains_labels_for_binary_columns()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertStringContainsString('<th>Data</th>', $code);
        $this->assertStringContainsString('<td>{{ $avatar->data }}</td>', $code);
    }

    /** @test */
    public function it_renders_delete_and_edit_buttons()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            <a href="{{ route(\'users.edit\', [\'user\' => $user]) }}">Edit</a>
            <form action="{{ route(\'users.destroy\', [\'user\' => $user]) }}" method="POST">
                @method(\'DELETE\')
                @csrf
                <button type="submit">Delete</button>
            </form>
        ', $code);
    }

    /** @test */
    public function it_renders_action_route_names_with_the_tablename()
    {
        $code = $this->generator('payment_methods')->generate();

        $this->assertCodeContains('
            <a href="{{ route(\'payment_methods.edit\', [\'payment_method\' => $paymentMethod]) }}">Edit</a>
            <form action="{{ route(\'payment_methods.destroy\', [\'payment_method\' => $paymentMethod]) }}" method="POST">
                @method(\'DELETE\')
                @csrf
                <button type="submit">Delete</button>
            </form>
        ', $code);
    }
}
