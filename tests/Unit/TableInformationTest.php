<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class TableInformationTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /** @test */
    public function it_knows_the_table_name()
    {
        $table = new TableInformation('users');

        $this->assertEquals('users', $table->name());
    }

    /** @test */
    public function it_throws_on_non_existing_tables()
    {
        $this->assertException(Exception::class, function () {
            new TableInformation('non_existing_table');
        });
    }

    /** @test */
    public function it_knows_the_columns_of_a_table()
    {
        $table = new TableInformation('users');

        $columns = $table->columns();

        $this->assertEquals([
            'id',
            'name',
            'email',
            'subscribed',
            'birthday',
            'wake_up',
            'created_at',
            'updated_at',
        ], $columns);
    }

    /** @test */
    public function it_knows_whether_a_colum_exists()
    {
        $table = new TableInformation('users');

        $this->assertTrue($table->has('name'));
        $this->assertTrue($table->has('wake_up'));

        $this->assertFalse($table->has('non_existing_column'));
    }

    /** @test */
    public function it_unescapes_escaped_column_names()
    {
        $table = new TableInformation('payment_methods');

        $this->assertContains('primary', $table->columns());
    }

    /** @test */
    public function it_knows_whether_a_column_is_required_or_optional()
    {
        $table = new TableInformation('users');
        $this->assertFalse($table->hasDefault('name'));
        $this->assertTrue($table->hasDefault('subscribed'));

        $table = new TableInformation('products');
        $this->assertTrue($table->hasDefault('start_at'));
    }

    /** @test */
    public function it_knows_the_default_value_of_a_column()
    {
        // Note: This probably works only in SQLite, and other drivers must be
        // tested as well.

        $table = new TableInformation('users');
        $this->assertEquals('0', $table->default('subscribed'));

        $table = new TableInformation('products');
        $this->assertEquals('CURRENT_TIMESTAMP', $table->default('start_at'));
    }

    /** @test */
    public function it_returns_column_types_as_strings()
    {
        $table = new TableInformation('users');
        $this->assertEquals(Type::STRING, $table->type('name'));
        $this->assertEquals(Type::DATETIME, $table->type('created_at'));

        $this->assertNull($table->type('non_existing_column'));
    }

    /** @test */
    public function it_constructs_virtual_enum_types_for_enum_columns()
    {
        $table = new TableInformation('products');

        $this->assertEquals(Type::ENUM, $table->type('type'));
        $this->assertSetsEqual(['food', 'stationery', 'other'], $table->getEnumValid('type'));
    }

    /** @test */
    public function it_knows_the_primary_key_of_a_table()
    {
        $table = new TableInformation('users');

        $this->assertEquals('id', $table->primaryKey());

        // TODO: Ensure that compound primary keys are handled here as well
    }

    /** @test */
    public function it_detects_soft_deletes()
    {
        $this->assertFalse(
            (new TableInformation('users'))->softDeletes()
        );

        $this->assertTrue(
            (new TableInformation('products'))->softDeletes()
        );
    }

    /** @test */
    public function it_retrieves_whether_a_column_references_one_in_another_table()
    {
        $this->assertEquals(
            (new TableInformation('products'))->reference('owner_id'),
            ['users', 'id']
        );

        $this->assertEquals(
            (new TableInformation('avatars'))->reference('user_id'),
            ['users', 'id']
        );

        $this->assertNull(
            (new TableInformation('users'))->reference('name')
        );
    }

    /** @test */
    public function it_retrieves_all_references()
    {
        $this->assertEquals(
            [
                'user_id' => ['users', 'id'],
            ],
            (new TableInformation('avatars'))->allReferences()
        );

        $this->assertEquals(
            [
                'role_id' => ['roles', 'id'],
                'user_id' => ['users', 'id'],
            ],
            (new TableInformation('role_user'))->allReferences()
        );
    }

    /** @test */
    public function it_retrieves_the_label_column_of_tables()
    {
        $data = [
            'users' => 'name',
            'avatars' => 'file',
            'products' => null,
            'roles' => 'name',
            'sales' => null,
            'payment_methods' => 'name',
        ];

        foreach ($data as $table => $expected) {
            $this->assertEquals(
                $expected,
                (new TableInformation($table))->labelColumn()
            );
        }
    }

    /** @test */
    public function it_knows_if_a_table_is_a_pivot()
    {
        $table = new TableInformation('users');
        $pivot = new TableInformation('role_user');

        $this->assertFalse($table->isPivot());
        $this->assertTrue($pivot->isPivot());
    }

    /** @test */
    public function it_handles_enum_columns()
    {
        $this->assertNull(
            (new TableInformation('users'))->getEnumValid('name')
        );

        $this->assertSetsEqual(
            ['food', 'stationery', 'other'],
            (new TableInformation('products'))->getEnumValid('type')
        );
    }

    /** @test */
    public function it_knows_whether_columns_have_unique_indices()
    {
        $this->assertFalse((new TableInformation('users'))->unique('name'));
        $this->assertTrue((new TableInformation('users'))->unique('email'));
    }

    // TODO: Test that boolean columns with default values are never nullable
}
