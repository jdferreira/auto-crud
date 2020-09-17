<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;

class TypeTest extends TestCase
{
    /** @test */
    public function it_knows_the_format_of_date_related_column()
    {
        $this->assertEquals("'Y-m-d H:i:s'", Type::dateTimeFormat(Type::DATETIME));
        $this->assertEquals("'Y-m-d'", Type::dateTimeFormat(Type::DATE));

        $this->assertEquals(null, Type::dateTimeFormat(Type::TIME));
        $this->assertEquals(null, Type::dateTimeFormat(Type::INTEGER));
        $this->assertEquals(null, Type::dateTimeFormat('not-a-type'));
    }
}
