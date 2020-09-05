<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ViewIndexGenerator;

class ViewIndexGeneratorTest extends TestCase
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
     * @return ViewIndexGenerator
     */
    private function generator(string $table): ViewIndexGenerator
    {
        return app(ViewIndexGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator('users')->save();

        $this->assertFileExists(resource_path('views/users/index.blade.php'));
    }

    /** @test */
    public function it_shows_all_users()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains(
            '@foreach ($users as $user)',
            $code
        );
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('<h1>Users</h1>', $code);
    }

    /** @test */
    public function it_contains_a_column_for_the_id()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('<th>ID</th>', $code);
    }

    /** @test */
    public function it_contains_an_html_table_column_for_each_database_table_column()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('<th>Name</th>', $code);
        $this->assertContains('<th>Email</th>', $code);
        $this->assertContains('<th>Subscribed</th>', $code);
        $this->assertContains('<th>Birthday</th>', $code);
        $this->assertContains('<th>Wake up</th>', $code);
    }

    /** @test */
    public function it_accesses_model_fields_for_each_column()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('$user->id', $code);
        $this->assertContains('$user->name', $code);
        $this->assertContains('$user->email', $code);
        $this->assertContains('$user->subscribed', $code);
        $this->assertContains('$user->birthday', $code);
        $this->assertContains('$user->wake_up', $code);
    }

    /** @test */
    public function it_does_not_show_soft_deletes()
    {
        $code = $this->generator('products')->generate();

        $this->assertNotContains('$product->deleted_at', $code);

        $code = $this->generator('payment_methods')->generate();

        $this->assertNotContains('$paymentMethod->deletion_time', $code);
    }

    /** @test */
    public function it_does_not_show_timestamps()
    {
        $code = $this->generator('users')->generate();

        $this->assertNotContains('$user->created_at', $code);
        $this->assertNotContains('$user->updated_at', $code);
    }

    /** @test */
    public function it_detects_column_type()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('$user->subscribed ? \'&#10004;\' : \'\'', $code);
        $this->assertContains('$user->birthday->format(\'Y-m-d\')', $code);
        $this->assertContains('$user->wake_up->format(\'H:i:s\')', $code);

        $code = $this->generator('products')->generate();

        $this->assertContains('$product->start_at->format(\'Y-m-d H:i:s\')', $code);
    }

    /** @test */
    public function it_detects_nullable_columns()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('$user->email ?: \'\'', $code);
        $this->assertContains('$user->wake_up ? $user->wake_up->format(\'H:i:s\') : \'\'', $code);

        $code = $this->generator('products')->generate();

        $this->assertContains('$product->type ?: \'\'', $code);
    }

    /** @test */
    public function it_does_not_show_binary_columns()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertNotContains('<th>Data</th>', $code);
        $this->assertNotContains('$avatar->data', $code);
    }

    /** @test */
    public function it_shows_navigation_links()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('{{ $users->links() }}', $code);
    }

    /** @test */
    public function it_shows_relationships()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertContains('$avatar->user->name', $code);

        $code = $this->generator('sales')->generate();

        $this->assertContains('(Product: {{ $sale->product_id }})', $code);
    }

    /** @test */
    public function it_renders_links_on_relationships()
    {
        $code = $this->generator('sales')->generate();

        $this->assertContains(
            '<a href="{{ route(\'products.show\', [\'products\' => $sale->product_id]) }}">',
            $code
        );
    }

    /** @test */
    public function it_generates_correct_index_view_for_users()
    {
        $code = $this->generator('users')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Users</h1>

            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subscribed</th>
                    <th>Birthday</th>
                    <th>Wake up</th>
                </tr>

                @foreach (\$users as \$user)
                    <tr>
                        <td>{{ \$user->id }}</td>
                        <td>{{ \$user->name }}</td>
                        <td>{{ \$user->email ?: '' }}</td>
                        <td>{{ \$user->subscribed ? '&#10004;' : '' }}</td>
                        <td>{{ \$user->birthday->format('Y-m-d') }}</td>
                        <td>{{ \$user->wake_up ? \$user->wake_up->format('H:i:s') : '' }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$users->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }

    /** @test */
    public function it_generates_correct_index_view_for_avatars()
    {
        $code = $this->generator('avatars')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Avatars</h1>

            <table>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>File</th>
                </tr>

                @foreach (\$avatars as \$avatar)
                    <tr>
                        <td>{{ \$avatar->id }}</td>
                        <td><a href=\"{{ route('users.show', ['users' => \$avatar->user_id]) }}\">{{ \$avatar->user->name }}</a></td>
                        <td>{{ \$avatar->file }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$avatars->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }

    /** @test */
    public function it_generates_correct_index_view_for_products()
    {
        $code = $this->generator('products')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Products</h1>

            <table>
                <tr>
                    <th>Product id</th>
                    <th>Owner</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Start at</th>
                </tr>

                @foreach (\$products as \$product)
                    <tr>
                        <td>{{ \$product->product_id }}</td>
                        <td><a href=\"{{ route('users.show', ['users' => \$product->owner_id]) }}\">{{ \$product->owner->name }}</a></td>
                        <td>{{ \$product->type ?: '' }}</td>
                        <td>{{ \$product->value }}</td>
                        <td>{{ \$product->start_at->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$products->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }

    /** @test */
    public function it_generates_correct_index_view_for_roles()
    {
        $code = $this->generator('roles')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Roles</h1>

            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                </tr>

                @foreach (\$roles as \$role)
                    <tr>
                        <td>{{ \$role->id }}</td>
                        <td>{{ \$role->name }}</td>
                        <td>{{ \$role->description }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$roles->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }

    /** @test */
    public function it_generates_correct_index_view_for_sales()
    {
        $code = $this->generator('sales')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Sales</h1>

            <table>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>

                @foreach (\$sales as \$sale)
                    <tr>
                        <td>{{ \$sale->id }}</td>
                        <td><a href=\"{{ route('products.show', ['products' => \$sale->product_id]) }}\">(Product: {{ \$sale->product_id }})</a></td>
                        <td>{{ \$sale->amount }}</td>
                        <td>{{ \$sale->date->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$sales->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }

    /** @test */
    public function it_generates_correct_index_for_payment_methods()
    {
        $code = $this->generator('payment_methods')->generate();

        $excerpt = "
            @extends('layouts.app')

            @section('content')
            <h1>Payment_methods</h1>

            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Primary</th>
                    <th>Metadata</th>
                </tr>

                @foreach (\$paymentMethods as \$paymentMethod)
                    <tr>
                        <td>{{ \$paymentMethod->id }}</td>
                        <td>{{ \$paymentMethod->name }}</td>
                        <td>{{ \Illuminate\Support\Str::limit(\$paymentMethod->primary, 30) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit(\$paymentMethod->metadata, 30) }}</td>
                    </tr>
                @endforeach
            </table>

            {{ \$paymentMethods->links() }}
            @endsection
        ";

        $this->assertCodeContains($excerpt, $code);
    }
}
