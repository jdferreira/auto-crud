<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Validation\RuleGenerator;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\RequestGenerator;

class RequestGeneratorTest extends TestCase
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
     * @param string $dir
     *
     * @return RequestGenerator
     */
    private function generator(string $table): RequestGenerator
    {
        return app(RequestGenerator::class, [
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_request()
    {
        $this->generator('users')->save();

        $this->assertFileExists(app_path('Http/Requests/UserRequest.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        // This test only works because the users table contains a unique
        // column, and so the request generator needs to know how to get to that
        // model.

        $code = $this->generator('users')->setModelDirectory('Models')->generate();

        $this->assertStringContainsString(
            'use App\Models\User;',
            $code
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_request()
    {
        $code = $this->generator('products')->generate();

        $this->assertStringContainsString(
            'class ProductRequest extends FormRequest',
            $code
        );
    }

    /** @test */
    public function it_uses_column_definitions_to_decide_how_to_validate()
    {
        $this->app->bind(RuleGenerator::class, function () {
            return $this->mock(RuleGenerator::class, function ($mock) {
                $mock->shouldReceive('generate')->once();
            });
        });

        $this->generator('users')->generate();
    }

    /** @test */
    public function it_generates_a_correct_validation_for_users()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains("
            \$model = \$this->route('user');

            return [
                'name' => 'required',
                'email' => [
                    'nullable',
                    'email:rfc',
                    Rule::unique('users')->ignore(\$model),
                ],
                'subscribed' => 'required|boolean',
                'birthday' => 'required|date_format:Y-m-d',
                'wake_up' => 'nullable|date_format:H:i:s',
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_validation_for_avatars()
    {
        $code = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            \$model = \$this->route('avatar');

            return [
                'user_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                    Rule::unique('avatars')->ignore(\$model),
                ],
                'file' => 'required',
                'data' => 'required',
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_validation_for_products()
    {
        $code = $this->generator('products')->generate();

        $this->assertCodeContains("
            return [
                'owner_id' => 'required|integer|exists:users,id',
                'type' => 'nullable|in:food,stationery,other',
                'value' => [
                    'required',
                    'regex:/^[+-]?(?:\d+\.?|\d*\.\d+)$/',
                ],
                'start_at' => 'required|date',
            ];
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_validation_for_roles()
    {
        $code = $this->generator('roles')->generate();

        $this->assertCodeContains("
            return [
                'name' => 'required',
                'description' => 'required',
            ];
        ", $code);
    }

    // TODO: What about many-to-many relationhsips??

    /** @test */
    public function it_generates_a_correct_validation_for_sales()
    {
        $code = $this->generator('sales')->generate();

        $this->assertCodeContains("
            return [
                'product_id' => 'required|integer|exists:products',
                'amount' => 'required|integer',
                'date' => 'required|date_format:Y-m-d',
            ];
        ", $code);
    }
}
