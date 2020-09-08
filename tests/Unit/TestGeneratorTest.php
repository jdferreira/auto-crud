<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Generators\TestGenerator;
use Ferreira\AutoCrud\Database\TableInformation;
use PHPUnit\Framework\ExpectationFailedException;

class TestGeneratorTest extends TestCase
{
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param string $table
     * @param string $dir
     *
     * @return TestGenerator
     */
    private function generator(string $table): TestGenerator
    {
        return app(TestGenerator::class, [
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_model()
    {
        $this->generator('users')->save();

        $this->assertFileExists(base_path('tests/Feature/UsersCrudTest.php'));
    }

    /** @test */
    public function it_detects_referenced_models_qualified_name()
    {
        $code = $this->generator('users')->generate();
        $this->assertStringContainsString('use App\User;', $code);

        $code = $this->generator('users')->setModelDirectory('Models')->generate();
        $this->assertStringContainsString('use App\Models\User;', $code);
    }

    /** @test */
    public function it_defines_a_testcase()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString('use Tests\TestCase;', $code);
        $this->assertStringContainsString('class UsersCrudTest extends TestCase', $code);
    }

    /** @test */
    public function it_uses_the_necessary_traits()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            use RefreshDatabase,
                AssertsHTML,
                AssertsField;
        ', $code);
    }

    /** @test */
    public function it_generates_twelve_tests()
    {
        $code = $this->generator('users')->generate();

        preg_match_all('/\/\*\* @test \*\/\n *.*function \w+/', $code, $matches);

        // Note that with preg_match_all, the $matches variable contains matches
        // and groups:
        // - $matches[0] is an array of all strings that matched full pattern
        // - $matches[1] is an array of strings matched by the first
        //   parenthesized subpattern
        // - etc.
        //
        // In this case, we want only the full matches, so $matches[0]

        $this->assertCount(12, $matches[0]);
    }

    /** @test */
    public function it_uses_the_table_name_on_all_test_methods()
    {
        $code = $this->generator('users')->generate();

        preg_match_all('/\/\*\* @test \*\/\n *.*function \w+/', $code, $matches);

        foreach ($matches[0] as $match) {
            $this->assertRegExp('/(?:_|\b)users?(?:_|\b)/', $match);
        }
    }

    /** @test */
    public function it_generates_valid_PHP_code()
    {
        $code = $this->generator('users')->generate();

        $cmd = 'php -l';
        $specs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $specs, $pipes);

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        // Apparently we need to read the STDOUT pipe, or the process fails with a 255 code
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        $this->assertEquals(0, $exitCode, 'File contains syntax errors:' . PHP_EOL . $errors);
    }

    /** @test */
    public function it_tests_the_index_view()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_shows_existing_users_in_the_index()
            {
                $users = factory(User::class, 2)->states(\'full_model\')->create();

                foreach ($users as $user) {
                    $this->get(\'/users\')
                        ->assertSeeText($user->id)
                        ->assertSeeText($user->name)
                        ->assertSeeText($user->email)
                        ->assertSeeText($user->subscribed ? \'&#10004;\' : \'&#10008;\')
                        ->assertSeeText($user->birthday->format(\'Y-m-d\'))
                        ->assertSeeText($user->wake_up->format(\'H:i:s\'));
                }
            }
        ', $code);
    }

    /** @test */
    public function it_tests_the_create_form()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_asks_for_all_columns_on_the_user_create_form()
            {
                $document = $this->getDOMDocument(
                    $this->get(\'/users/create\')
                );

                $this->assertHTML("//input[@name=\'name\' and @type=\'text\']", $document);
                $this->assertHTML("//input[@name=\'email\' and @type=\'email\']", $document);
                $this->assertHTML("//input[@name=\'subscribed\' and @type=\'checkbox\']", $document);
                $this->assertHTML("//input[@name=\'birthday\' and @type=\'date\']", $document);
                $this->assertHTML("//input[@name=\'wake-up\' and @type=\'time\']", $document);
            }
        ', $code);
    }

    /** @test */
    public function it_tests_current_values_on_edit_form()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_starts_the_edit_form_with_the_user_current_values()
            {
                $user = factory(User::class)->states(\'full_model\')->create();

                $document = $this->getDOMDocument(
                    $this->get($user->path() . \'/edit\')
                );

                $this->assertHTML($this->xpath("//*[@name=\'name\' and @value=\'%s\']", $user->name), $document);
                $this->assertHTML($this->xpath("//*[@name=\'email\' and @value=\'%s\']", $user->email), $document);
                $this->assertHTML($this->xpath("//*[@name=\'birthday\' and @value=\'%s\']", $user->birthday->format(\'Y-m-d\')), $document);
                $this->assertHTML($this->xpath("//*[@name=\'wake-up\' and @value=\'%s\']", $user->wake_up->format(\'H:i:s\')), $document);

                $subscribedChecked = $user->subscribed ? \'@checked\' : \'not(@checked)\';
                $this->assertHTML("//*[@name=\'subscribed\' and $subscribedChecked]", $document);
            }
        ', $code);
    }

    /** @test */
    public function it_tests_old_values_from_a_previous_form_submission()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_keeps_old_values_on_unsuccessful_user_update()
            {
                $user = factory(User::class)->states(\'full_model\')->create();

                $updated = $user->toArray();
                $updated[\'name\'] = \'\';

                $this->withExceptionHandling();

                $response = $this->put($user->path(), $updated);

                $response->assertSessionHasInput(\'name\', \'\');
            }
        ', $code);
    }

    // /** @test */
    // public function it_marks_test_for_old_values_as_skipped_when_all_fields_are_optional()
    // {
    //     $this->markTestSkipped('I must refactor the code, perhaps, to make this code actually writable');
    // }

    /** @test */
    public function it_tests_required_fields()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_marks_required_labels_on_users_create_and_edit_forms()
            {
                $document = $this->getDOMDocument($this->get(\'/users/create\'));

                $this->assertHTML("//*[@name=\'name\' and @required]", $document);
                $this->assertHTML("//*[@name=\'email\' and not(@required)]", $document);
                $this->assertHTML("//*[@name=\'subscribed\' and not(@required)]", $document);
                $this->assertHTML("//*[@name=\'birthday\' and @required]", $document);
                $this->assertHTML("//*[@name=\'wake-up\' and not(@required)]", $document);

                $user = factory(User::class)->create();

                $document = $this->getDOMDocument($this->get($user->path() . \'/edit\'));

                $this->assertHTML("//*[@name=\'name\' and @required]", $document);
                $this->assertHTML("//*[@name=\'email\' and not(@required)]", $document);
                $this->assertHTML("//*[@name=\'subscribed\' and not(@required)]", $document);
                $this->assertHTML("//*[@name=\'birthday\' and @required]", $document);
                $this->assertHTML("//*[@name=\'wake-up\' and not(@required)]", $document);
            }
        ', $code);
    }

    /** @test */
    public function it_tests_the_creation_function()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_creates_users_when_asked_to()
            {
                $this->assertNull(User::find(1));

                $new = factory(User::class)->raw();

                $this->post(\'/users\', $new);

                $this->assertNotNull($user = User::find(1));

                $this->assertEquals($new[\'name\'], $user->name);
                $this->assertEquals($new[\'email\'], $user->email);
                $this->assertEquals($new[\'subscribed\'], $user->subscribed);
                $this->assertEquals($new[\'birthday\'], $user->birthday->format(\'Y-m-d\'));
                $this->assertEquals($new[\'wake_up\'], $user->wake_up->format(\'H:i:s\'));
            }
        ', $code);
    }

    /** @test */
    public function it_tests_the_update_function()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            /** @test */
            public function it_updates_users_when_asked_to()
            {
                $user = factory(User::class)->create();

                $new = factory(User::class)->raw();

                $this->put($user->path(), $new);

                $user = $user->fresh();

                $this->assertEquals($new[\'name\'], $user->name);
                $this->assertEquals($new[\'email\'], $user->email);
                $this->assertEquals($new[\'subscribed\'], $user->subscribed);
                $this->assertEquals($new[\'birthday\'], $user->birthday->format(\'Y-m-d\'));
                $this->assertEquals($new[\'wake_up\'], $user->wake_up->format(\'H:i:s\'));
            }
        ', $code);
    }

    /** @test */
    public function it_tests_specific_field_values()
    {
        $code = $this->generator('users')->generate();

        $this->assertCodeContains('
            public function assertFields()
            {
                $this->withExceptionHandling();

                // Create one user to test fields that should contain unique values
                factory(User::class)->create([
                    \'email\' => \'mail@example.com\',
                ]);

                $this->assertField(\'name\')
                    ->accepts(\'John Doe\')
                    ->accepts(\'Jane Doe\')
                    ->rejects(null);

                $this->assertField(\'email\')
                    ->accepts(\'johndoe@example.com\')
                    ->rejects(\'mail@example.com\') // Duplicate values must be rejected
                    ->accepts(null);

                $this->assertField(\'subscribed\')
                    ->accepts(true)
                    ->accepts(false)
                    ->rejects(\'yes\')
                    ->rejects(\'no\')
                    ->rejects(\'2\')
                    ->rejects(null);

                $this->assertField(\'birthday\')
                    ->accepts(\'2020-01-01\')
                    ->accepts(\'2021-12-31\')
                    ->rejects(\'2020-13-01\')
                    ->rejects(\'2020-01-32\')
                    ->rejects(\'not-a-date\')
                    ->rejects(null);

                $this->assertField(\'wake_up\')
                    ->accepts(\'00:01:02\')
                    ->accepts(\'23:59:59\')
                    ->rejects(\'25:00:00\')
                    ->rejects(\'00:61:00\')
                    ->rejects(\'00:00:61\')
                    ->rejects(\'not-a-time\')
                    ->accepts(null);
            }
        ', $code);
    }
}
