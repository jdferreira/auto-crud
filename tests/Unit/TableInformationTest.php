<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Mockery\MockInterface;
use Ferreira\AutoCrud\Type;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Connection;
use Doctrine\DBAL\Types\Type as DoctrineType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Types\Types as DoctrineTypes;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseException;

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
        $table = app(TableInformation::class, ['name' => 'users']);

        $this->assertEquals('users', $table->name());
    }

    /** @test */
    public function it_throws_on_non_existing_tables()
    {
        $this->assertException(Exception::class, function () {
            app(TableInformation::class, ['name' => 'non_existing_table']);
        });
    }

    /** @test */
    public function it_knows_the_columns_of_a_table()
    {
        $table = app(TableInformation::class, ['name' => 'users']);

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
        $table = app(TableInformation::class, ['name' => 'users']);

        $this->assertTrue($table->has('name'));
        $this->assertTrue($table->has('wake_up'));

        $this->assertFalse($table->has('non_existing_column'));
    }

    /** @test */
    public function it_unescapes_escaped_column_names()
    {
        $table = app(TableInformation::class, ['name' => 'payment_methods']);

        $this->assertContains('primary', $table->columns());
    }

    /** @test */
    public function it_knows_whether_a_column_is_required_or_optional()
    {
        $table = app(TableInformation::class, ['name' => 'users']);
        $this->assertFalse($table->hasDefault('name'));
        $this->assertTrue($table->hasDefault('subscribed'));

        $table = app(TableInformation::class, ['name' => 'products']);
        $this->assertTrue($table->hasDefault('start_at'));
    }

    /** @test */
    public function it_knows_the_default_value_of_a_column()
    {
        // Note: This probably works only in SQLite, and other drivers must be
        // tested as well.

        $table = app(TableInformation::class, ['name' => 'users']);
        $this->assertEquals('0', $table->default('subscribed'));

        $table = app(TableInformation::class, ['name' => 'products']);
        $this->assertEquals('CURRENT_TIMESTAMP', $table->default('start_at'));
    }

    /** @test */
    public function it_returns_column_types_as_strings()
    {
        $table = app(TableInformation::class, ['name' => 'users']);
        $this->assertEquals(Type::STRING, $table->type('name'));
        $this->assertEquals(Type::DATETIME, $table->type('created_at'));

        $this->assertNull($table->type('non_existing_column'));
    }

    /** @test */
    public function it_constructs_virtual_enum_types_for_enum_columns()
    {
        $table = app(TableInformation::class, ['name' => 'products']);

        $this->assertEquals(Type::ENUM, $table->type('type'));
        $this->assertSetsEqual(['food', 'stationery', 'other'], $table->getEnumValid('type'));
    }

    /** @test */
    public function it_knows_the_primary_key_of_a_table()
    {
        $table = app(TableInformation::class, ['name' => 'users']);

        $this->assertEquals('id', $table->primaryKey());
    }

    /** @test */
    public function it_detects_soft_deletes()
    {
        $this->assertFalse(
            (app(TableInformation::class, ['name' => 'users']))->softDeletes()
        );

        $this->assertTrue(
            (app(TableInformation::class, ['name' => 'products']))->softDeletes()
        );
    }

    /** @test */
    public function it_retrieves_whether_a_column_references_one_in_another_table()
    {
        $this->assertEquals(
            (app(TableInformation::class, ['name' => 'products']))->reference('owner_id'),
            ['users', 'id']
        );

        $this->assertEquals(
            (app(TableInformation::class, ['name' => 'avatars']))->reference('user_id'),
            ['users', 'id']
        );

        $this->assertNull(
            (app(TableInformation::class, ['name' => 'users']))->reference('name')
        );
    }

    /** @test */
    public function it_retrieves_all_references()
    {
        $this->assertEquals(
            [
                'user_id' => ['users', 'id'],
            ],
            (app(TableInformation::class, ['name' => 'avatars']))->allReferences()
        );

        $this->assertEquals(
            [
                'role_id' => ['roles', 'id'],
                'user_id' => ['users', 'id'],
            ],
            (app(TableInformation::class, ['name' => 'role_user']))->allReferences()
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
                app(TableInformation::class, ['name' => $table])->labelColumn()
            );
        }
    }

    /** @test */
    public function it_knows_if_a_table_is_a_pivot()
    {
        $table = app(TableInformation::class, ['name' => 'users']);
        $pivot = app(TableInformation::class, ['name' => 'role_user']);

        $this->assertFalse($table->isPivot());
        $this->assertTrue($pivot->isPivot());
    }

    /** @test */
    public function it_handles_enum_columns()
    {
        $this->assertNull(
            (app(TableInformation::class, ['name' => 'users']))->getEnumValid('name')
        );

        $this->assertSetsEqual(
            ['food', 'stationery', 'other'],
            (app(TableInformation::class, ['name' => 'products']))->getEnumValid('type')
        );
    }

    /** @test */
    public function it_knows_whether_columns_have_unique_indices()
    {
        $this->assertFalse((app(TableInformation::class, ['name' => 'users']))->unique('name'));
        $this->assertTrue((app(TableInformation::class, ['name' => 'users']))->unique('email'));
    }

    /** @test */
    public function it_computes_expected_foreign_key_column_name()
    {
        $this->assertEquals((app(TableInformation::class, ['name' => 'users']))->foreignKey(), 'user_id');
        $this->assertEquals((app(TableInformation::class, ['name' => 'products']))->foreignKey(), 'product_product_id');
        $this->assertEquals((app(TableInformation::class, ['name' => 'avatars']))->foreignKey(), 'avatar_id');
        $this->assertEquals((app(TableInformation::class, ['name' => 'sales']))->foreignKey(), 'sale_id');
    }

    /** @test */
    public function foreign_keys_cannot_have_default_values()
    {
        // Let's suppose our DatabaseInformation is built from an
        // AbstractSchemaManager that, in its inners, describes a table that has
        // a default value on a foreign key column. If we build a
        // TableInformation for that table, it should fail.

        /** @var AbstractSchemaManager&MockInterface $doctrine */
        $doctrine = $this->mock(AbstractSchemaManager::class, function ($mock) {
            /** @var AbstractSchemaManager&MockInterface $mock */
            $column = new Column('column', DoctrineType::getType(DoctrineTypes::INTEGER));
            $column->setDefault(0);

            $mock->shouldReceive('tablesExist')->withArgs(['tablename'])->andReturn(true);

            $mock
                ->shouldReceive('listTableColumns')
                ->withArgs(['tablename'])
                ->andReturn([
                    'column' => $column,
                ]);

            $mock
                ->shouldReceive('listTableForeignKeys')
                ->withArgs(['tablename'])
                ->andReturn([
                    new ForeignKeyConstraint(['column'], 'foreign', ['id']),
                ]);

            $mock
                ->shouldReceive('listTableIndexes')
                ->andReturn([]);
        });

        /** @var Connection&MockInterface $connection */
        $connection = $this->mock(Connection::class, function ($mock) use ($doctrine) {
            $mock->shouldReceive('getDoctrineSchemaManager')->andReturn($doctrine);
            $mock->shouldReceive('getDriverName')->andReturn(null);
        });

        $this->addToAssertionCount(-$doctrine->mockery_getExpectationCount());
        $this->addToAssertionCount(-$connection->mockery_getExpectationCount());

        $this->assertException(DatabaseException::class, function () {
            app(TableInformation::class, ['name' => 'tablename']);
        });
    }

    /** @test */
    public function primary_keys_must_span_exactly_one_column()
    {
        /** @var AbstractSchemaManager&MockInterface $doctrine */
        $doctrine = $this->mock(AbstractSchemaManager::class, function ($mock) {
            $mock->shouldReceive('tablesExist')->withArgs(['tablename'])->andReturn(true);

            $mock
                ->shouldReceive('listTableColumns')
                ->withArgs(['tablename'])
                ->andReturn([]);

            $mock
                ->shouldReceive('listTableIndexes')
                ->andReturn([
                    'primary' => new Index('', ['one', 'two']),
                ]);
        });

        /** @var Connection&MockInterface $connection */
        $connection = $this->mock(Connection::class, function ($mock) use ($doctrine) {
            $mock->shouldReceive('getDoctrineSchemaManager')->andReturn($doctrine);
        });

        $this->addToAssertionCount(-$doctrine->mockery_getExpectationCount());
        $this->addToAssertionCount(-$connection->mockery_getExpectationCount());

        $this->assertException(DatabaseException::class, function () {
            app(TableInformation::class, ['name' => 'tablename']);
        });
    }

    /** @test */
    public function it_disallows_non_nullable_unique_indexes_on_boolean_columns()
    {
        /** @var AbstractSchemaManager&MockInterface $doctrine */
        $doctrine = $this->mock(AbstractSchemaManager::class, function ($mock) {
            $mock->shouldReceive('tablesExist')->withArgs(['tablename'])->andReturn(true);

            $mock
                ->shouldReceive('listTableColumns')
                ->withArgs(['tablename'])
                ->andReturn([
                    'status' => new Column('status', DoctrineType::getType(DoctrineTypes::BOOLEAN)),
                ]);

            $mock
                ->shouldReceive('listTableIndexes')
                ->andReturn([
                    'unique' => new Index('', ['status'], true),
                ]);

            $mock
                ->shouldReceive('listTableForeignKeys')
                ->withArgs(['tablename'])
                ->andReturn([]);
        });

        /** @var Connection&MockInterface $connection */
        $connection = $this->mock(Connection::class, function ($mock) use ($doctrine) {
            $mock->shouldReceive('getDoctrineSchemaManager')->andReturn($doctrine);

            $mock->shouldReceive('getDriverName')->andReturn(null);
        });

        $this->addToAssertionCount(-$doctrine->mockery_getExpectationCount());
        $this->addToAssertionCount(-$connection->mockery_getExpectationCount());

        $this->assertException(DatabaseException::class, function () {
            app(TableInformation::class, ['name' => 'tablename']);
        });
    }
}
