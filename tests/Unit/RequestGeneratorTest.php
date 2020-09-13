<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Validation\RuleGenerator;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\RequestGenerator;

class RequestGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return RequestGenerator
     */
    private function generator(TableInformation $table): RequestGenerator
    {
        return app(RequestGenerator::class, ['table' => $table]);
    }

    /** @test */
    public function it_can_generate_a_request()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(app_path('Http/Requests/StudentRequest.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        // This test depends on the table containing a unique column, which
        // makes the generated request class to need to know how to get to the
        // model class

        $code = $this->generator(
            $this->mockTable('schools', [
                'name' => ['unique' => true],
            ])
        )->generate();

        $this->assertStringContainsString(
            'use App\School;',
            $code
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_request()
    {
        $code = $this->generator(
            $this->mockTable('student')
        )->generate();

        $this->assertStringContainsString('use Illuminate\Foundation\Http\FormRequest;', $code);
        $this->assertStringContainsString('class StudentRequest extends FormRequest', $code);
    }

    /** @test */
    public function it_uses_column_definitions_to_decide_how_to_validate()
    {
        $this->app->bind(RuleGenerator::class, function () {
            return $this->mock(RuleGenerator::class, function ($mock) {
                $mock->shouldReceive('generate')->once();
            });
        });

        $this->generator(
            $this->mockTable('students', [
                'name' => [],
            ])
        )->generate();
    }
}
