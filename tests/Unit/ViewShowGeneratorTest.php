<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ViewShowGenerator;

class ViewShowGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ViewShowGenerator
     */
    private function generator(TableInformation $table): ViewShowGenerator
    {
        return app(ViewShowGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(resource_path('views/students/show.blade.php'));
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('<h1>Student</h1>', $code);
    }

    /** @test */
    public function it_contains_a_label_for_each_field()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'name' => [],
                'house' => [],
            ])
        )->generate();

        $this->assertStringContainsString('<th>Name</th>', $code);
        $this->assertStringContainsString('<th>House</th>', $code);
    }

    /** @test */
    public function it_formats_column_type()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'birthday' => ['type' => Type::DATE],
                'lunch' => ['type' => Type::TIME],
                'letter_sent_at' => ['type' => Type::DATETIME],
                'has_pet' => ['type' => Type::BOOLEAN],
            ])
        )->generate();

        $this->assertStringContainsString('$student->birthday->format(\'Y-m-d\')', $code);
        $this->assertStringContainsString('$student->lunch->format(\'H:i:s\')', $code);
        $this->assertStringContainsString('$student->letter_sent_at->format(\'Y-m-d H:i:s\')', $code);
        $this->assertStringContainsString('$student->has_pet ? \'&#10004;\' : \'&#10008;\'', $code);
    }

    /** @test */
    public function it_does_not_render_laravel_timestamps()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'created_at' => [],
            ])
        )->generate();

        $this->assertStringNotContainsString('$student->created_at', $code);
    }

    /** @test */
    public function it_renders_delete_and_edit_buttons()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('
            <a href="{{ route(\'students.edit\', [\'student\' => $student]) }}">Edit</a>
            <form action="{{ route(\'students.destroy\', [\'student\' => $student]) }}" method="POST">
                @method(\'DELETE\')
                @csrf
                <button type="submit">Delete</button>
            </form>
        ', $code);
    }

    /** @test */
    public function it_uses_correct_case_on_the_edit_and_delete_buttons()
    {
        $code = $this->generator(
            $this->mockTable('staff_facilities')
        )->generate();

        $this->assertCodeContains('
            <a href="{{ route(\'staff_facilities.edit\', [\'staff_facility\' => $staffFacility]) }}">Edit</a>
            <form action="{{ route(\'staff_facilities.destroy\', [\'staff_facility\' => $staffFacility]) }}" method="POST">
                @method(\'DELETE\')
                @csrf
                <button type="submit">Delete</button>
            </form>
        ', $code);
    }
}
