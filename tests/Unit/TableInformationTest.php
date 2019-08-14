<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Ferreira\AutoCrud\Database\TableInformation;

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
    public function it_retrieves_the_details_of_a_specifc_column()
    {
        $table = new TableInformation('users');

        $this->assertInstanceOf(Column::class, $table->column('name'));

        $this->assertNull($table->column('non_existing_column'));
    }

    /** @test */
    public function it_knows_whether_a_column_is_required_or_optional()
    {
        $table = new TableInformation('users');

        $this->assertTrue($table->required('name'));
        $this->assertFalse($table->required('email'));
        $this->assertNull($table->required('non_existing_column'));
    }

    /** @test */
    public function it_represents_column_types_with_doctrine_strings()
    {
        $table = new TableInformation('users');

        $this->assertInstanceOf(StringType::class, $table->type('name'));
        $this->assertInstanceOf(DateTimeType::class, $table->type('created_at'));
        $this->assertNull($table->type('non_existing_column'));
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
    public function it_retrieved_the_foreign_keys_of_a_table()
    {
        $foreign = (new TableInformation('products'))->foreignKeys();

        $this->assertCount(1, $foreign);

        $foreignKey = $foreign[0];

        $this->assertInstanceOf(ForeignKeyConstraint::class, $foreignKey);
        $this->assertEquals(['owner_id'], $foreignKey->getColumns());
        $this->assertEquals('users', $foreignKey->getForeignTableName());
        $this->assertEquals(['id'], $foreignKey->getForeignColumns());
    }

    /** @test */
    public function it_knows_if_a_table_is_a_pivot()
    {
        $table = new TableInformation('users');
        $pivot = new TableInformation('role_user');

        // $this->assertFalse($table->isPivot());
        $this->assertTrue($pivot->isPivot());
    }
}
