<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\ModelGenerator;

class ModelGeneratorTest extends TestCase
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
     * @return ModelGenerator
     */
    private function generator(string $table): ModelGenerator
    {
        return app(ModelGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_model()
    {
        $this->generator('users')->save();

        $this->assertFileExists(app_path('User.php'));
    }

    /** @test */
    public function it_uses_the_app_namespace()
    {
        $code = $this->generator('users')->generate();

        $this->assertContains('namespace App;', $code);
    }

    /** @test */
    public function it_detects_other_namespaces()
    {
        $this->generator('users')->setModelDirectory('Models')->save();

        $this->assertFileExists(app_path('Models/User.php'));

        $this->assertContains(
            'namespace App\Models;',
            $this->files->get(app_path('Models/User.php'))
        );
    }

    /** @test */
    public function it_handles_nested_namespace()
    {
        $this->generator('users')->setModelDirectory('Models/Authentication')->save();

        $this->assertFileExists(app_path('Models/Authentication/User.php'));

        $this->assertContains(
            'namespace App\Models\Authentication;',
            $this->files->get(app_path('Models/Authentication/User.php'))
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_model()
    {
        $code = $this->generator('products')->generate();

        $this->assertContains(
            'class Product extends Model',
            $code
        );
    }

    /** @test */
    public function it_handles_soft_deletes()
    {
        $this->assertTrue($this->db->table('products')->softDeletes());

        $code = $this->generator('products')->generate();

        $this->assertContains('use Illuminate\Database\Eloquent\SoftDeletes;', $code);
        $this->assertContains('use SoftDeletes;', $code);
    }

    /** @test */
    public function it_handles_custom_primary_keys()
    {
        $this->assertEquals(
            'product_id',
            $this->db->table('products')->primaryKey()
        );

        $code = $this->generator('products')->generate();

        $this->assertContains(
            'protected $primaryKey = \'product_id\';',
            $code
        );
    }

    /** @test */
    public function it_handles_attribute_casting()
    {
        $this->assertCodeContains("
            protected \$casts = [
                'subscribed' => 'boolean',
                'birthday' => 'date',
                'wake_up' => 'time',
            ];
        ", $this->generator('users')->generate());

        $this->assertNotContains('protected $casts', $this->generator('avatars')->generate());

        $this->assertCodeContains("
            protected \$casts = [
                'value' => 'decimal:2',
                'start_at' => 'datetime',
            ];
        ", $this->generator('products')->generate());

        $this->assertNotContains('protected $casts', $this->generator('roles')->generate());

        $this->assertCodeContains("
            protected \$casts = [
                'amount' => 'integer',
                'date' => 'date',
            ];
        ", $this->generator('sales')->generate());
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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
}
