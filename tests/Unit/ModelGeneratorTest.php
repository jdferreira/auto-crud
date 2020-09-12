<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ModelGenerator;

class ModelGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ModelGenerator
     */
    private function generator($table): ModelGenerator
    {
        return app(ModelGenerator::class, ['table' => $table]);
    }

    /** @test */
    public function it_can_generate_a_model()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(app_path('Student.php'));
    }

    /** @test */
    public function it_uses_the_app_namespace()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('namespace App;', $code);
    }

    /** @test */
    public function it_saves_models_with_custom_namespaces_in_the_correct_directory()
    {
        $this->generator(
            $this->mockTable('students')
        )->setModelDirectory('Models')->save();

        $this->assertFileExists(app_path('Models/Student.php'));
    }

    /** @test */
    public function it_uses_custom_namespaces()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->setModelDirectory('Models')->generate();

        $this->assertStringContainsString('namespace App\Models;', $code);
    }

    /** @test */
    public function it_handles_nested_namespace()
    {
        $this->generator(
            $this->mockTable('users')
        )->setModelDirectory('Models/Authentication')->save();

        $this->assertFileExists(app_path('Models/Authentication/User.php'));

        $code = $this->generator(
            $this->mockTable('users')
        )->setModelDirectory('Models/Authentication')->generate();

        $this->assertStringContainsString(
            'namespace App\Models\Authentication;',
            $code
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_model()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString(
            'class Student extends Model',
            $code
        );
    }

    /** @test */
    public function it_handles_soft_deletes()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'deleted_at' => [
                    'type' => Type::DATETIME,
                    'required' => false,
                ],
            ])
        )->generate();

        $this->assertStringContainsString('use Illuminate\Database\Eloquent\SoftDeletes;', $code);
        $this->assertStringContainsString('use SoftDeletes;', $code);
    }

    /** @test */
    public function it_handles_custom_primary_keys()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'student_id' => [
                    'primaryKey' => true,
                ],
            ])
        )->generate();

        $this->assertStringContainsString(
            'protected $primaryKey = \'student_id\';',
            $code
        );
    }

    /** @test */
    public function it_handles_attribute_casting()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'name' => ['type' => Type::STRING],
                'birthday' => ['type' => Type::DATE],
                'height' => ['type' => Type::DECIMAL],
                'has_pet' => ['type' => Type::BOOLEAN],
                'current_year' => ['type' => Type::INTEGER],
                'letter_sent_at' => ['type' => Type::DATETIME],
                'preferred_lunch_time' => ['type' => Type::TIME],
            ])
        )->generate();

        $this->assertCodeContains("
            protected \$casts = [
                'birthday' => 'date',
                'height' => 'decimal:2',
                'has_pet' => 'boolean',
                'current_year' => 'integer',
                'letter_sent_at' => 'datetime',
                'preferred_lunch_time' => 'datetime',
            ];
        ", $code);

        $code = $this->generator(
            $this->mockTable('students', [
                'name' => ['type' => Type::STRING],
            ])
        )->generate();

        $this->assertStringNotContainsString('protected $casts', $code);
    }

    /** @not-test TODO: Re-add this as a actual test */
    public function it_handles_one_to_one_relationships()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains('
            public function avatar()
            {
                return $this->hasOne(Avatar::class);
            }
        ', $users);

        $this->assertCodeContains('
            public function user()
            {
                return $this->belongsTo(User::class);
            }
        ', $avatars);
    }

    /** @not-test TODO: Re-add this as a actual test */
    public function it_handles_one_to_many_relationships()
    {
        $users = $this->generator('users')->generate();
        $products = $this->generator('products')->generate();
        $sales = $this->generator('sales')->generate();

        $this->assertCodeContains("
            public function products()
            {
                return \$this->hasMany(Product::class, 'owner_id');
            }
        ", $users);

        $this->assertCodeContains("
            public function owner()
            {
                return \$this->belongsTo(User::class, 'owner_id');
            }
        ", $products);

        $this->assertCodeContains("
            public function sales()
            {
                return \$this->hasMany(Sale::class, 'product_id');
            }
        ", $products);

        $this->assertCodeContains("
            public function product()
            {
                return \$this->belongsTo(Product::class, 'product_id');
            }
        ", $sales);
    }

    /** @not-test TODO: Re-add this as a actual test */
    public function it_handles_many_to_many_relationships()
    {
        $users = $this->generator('users')->generate();
        $roles = $this->generator('roles')->generate();

        $this->assertCodeContains('
            public function roles()
            {
                return $this->belongsToMany(Role::class);
            }
        ', $users);

        $this->assertCodeContains('
            public function users()
            {
                return $this->belongsToMany(User::class);
            }
        ', $roles);
    }

    /** @test */
    public function it_generates_a_path_method()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'student_id' => ['primaryKey' => true],
            ])
        )->generate();

        $this->assertCodeContains('
            public function path()
            {
                return \'/students/\' . $this->student_id;
            }
        ', $code);
    }

    /** @test */
    public function it_has_a_fillable_attribute_to_guard_against_mass_assignments()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'name' => [],
                'house' => [],
            ])
        )->generate();

        $this->assertCodeContains("
            protected \$fillable = [
                'name',
                'house',
            ];
        ", $code);
    }

    /** @test */
    public function it_marks_models_without_eloquent_timestamps()
    {
        $table = $this->mockTable('students');

        $generator = app(ModelGenerator::class, [
            'table' => $table,
        ]);

        $code = $generator->generate();

        $this->assertStringContainsString('public $timestamps = false;', $code);
    }
}
