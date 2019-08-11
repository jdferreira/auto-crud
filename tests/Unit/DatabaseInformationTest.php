<?php

namespace Tests\Unit;

use Tests\TestCase;

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
        $this->assertEqualsCanonicalizing(
            [
                'users',
                'avatars',
                'products',
                'roles',
                'role_user',
                'sales',
            ],
            $this->db->tablenames()
        );
    }
}
