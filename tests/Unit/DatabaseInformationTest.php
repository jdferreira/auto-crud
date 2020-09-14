<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Database\OneToOne;
use Ferreira\AutoCrud\Database\OneToMany;
use Ferreira\AutoCrud\Database\ManyToMany;
use Ferreira\AutoCrud\Database\TableInformation;

class DatabaseInformationTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /** @test */
    public function it_can_retrieve_tables()
    {
        $this->assertSetsEqual(
            [
                'users',
                'avatars',
                'products',
                'roles',
                'role_user',
                'sales',
                'payment_methods',
            ],
            $this->db->tablenames()
        );
    }

    /** @test */
    public function it_knows_to_ignore_the_migrations_table()
    {
        $this->assertNotContains('migrations', $this->db->tablenames());
    }

    /** @test */
    public function it_retrieves_pivot_tablenames()
    {
        $this->assertSetsEqual(['role_user'], $this->db->pivots());

        $this->assertNotContains('role_user', $this->db->tablenames(false));
    }

    /** @test */
    public function it_can_retrieve_tables_information()
    {
        foreach ($this->db->tablenames() as $table) {
            $this->assertEquals(
                new TableInformation($table),
                $this->db->table($table)
            );
        }

        $this->assertNull($this->db->table('non_existing_table'));
    }

    /** @test */
    public function it_retrieves_relationships()
    {
        $relationships = $this->db->relationships();

        $expected = [
            new OneToOne('avatars', 'user_id', 'users', 'id'),
            new OneToMany('products', 'owner_id', 'users', 'id'),
            new OneToMany('sales', 'product_id', 'products', 'product_id'),
            new ManyToMany('role_user', 'role_id', 'roles', 'id', 'user_id', 'users', 'id'),
        ];

        $this->assertSetsEqual($expected, $relationships);
    }

    /** @test */
    public function it_retrieves_relationships_for_a_table()
    {
        $relationships = $this->db->relationshipsFor('users');

        $expected = [
            new OneToOne('avatars', 'user_id', 'users', 'id'),
            new OneToMany('products', 'owner_id', 'users', 'id'),
            new ManyToMany('role_user', 'role_id', 'roles', 'id', 'user_id', 'users', 'id'),
        ];

        $this->assertSetsEqual($expected, $relationships);
    }
}
