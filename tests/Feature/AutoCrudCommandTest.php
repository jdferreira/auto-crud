<?php

namespace Tests\Features;

use Tests\TestCase;

class AutoCrudCommandTest extends TestCase
{
    /** @test */
    public function the_command_exists()
    {
        $this->artisan('autocrud:make')->assertExitCode(0);
    }
}
