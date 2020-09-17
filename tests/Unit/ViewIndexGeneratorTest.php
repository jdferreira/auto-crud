<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ViewIndexGenerator;

class ViewIndexGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ViewIndexGenerator
     */
    private function generator(TableInformation $table): ViewIndexGenerator
    {
        return app(ViewIndexGenerator::class, [
            'table' => $table,
        ]);
    }

    /** @test */
    public function it_can_generate_a_view()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(resource_path('views/students/index.blade.php'));
    }

    /** @test */
    public function it_shows_all_models()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('@foreach ($students as $student)', $code);
    }

    /** @test */
    public function it_is_titled_based_on_the_model_name()
    {
        $code = $this->generator(
            $this->mockTable('Students')
        )->generate();

        $this->assertStringContainsString('<h1>Students</h1>', $code);
    }

    /** @test */
    public function it_contains_a_column_for_the_id()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'id' => ['primaryKey' => true],
            ])
        )->generate();

        $this->assertStringContainsString('<th>ID</th>', $code);
    }

    /** @test */
    public function it_contains_an_html_table_column_for_each_database_table_column()
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
    public function it_accesses_model_fields_for_each_column()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'id' => ['primaryKey' => true],
                'name' => [],
                'house' => [],
            ])
        )->generate();

        $this->assertStringContainsString('$student->id', $code);
        $this->assertStringContainsString('$student->name', $code);
        $this->assertStringContainsString('$student->house', $code);
    }

    /** @test */
    public function it_does_not_show_soft_deletes()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'deleted_at' => [],
            ])
        )->generate();

        $this->assertStringNotContainsString('$students->deleted_at', $code);
    }

    /** @test */
    public function it_does_not_show_timestamps()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'created_at' => [],
                'deleted_at' => [],
            ])
        )->generate();

        $this->assertStringNotContainsString('$student->created_at', $code);
        $this->assertStringNotContainsString('$student->updated_at', $code);
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
        $this->assertStringContainsString('$student->lunch', $code);
        $this->assertStringContainsString('$student->letter_sent_at->format(\'Y-m-d H:i:s\')', $code);
        $this->assertStringContainsString('$student->has_pet ? \'&#10004;\' : \'&#10008;\'', $code);
    }

    /** @test */
    public function it_detects_nullable_datetime_columns()
    {
        $code = $this->generator(
            $this->mockTable('students', [
                'birthday' => ['type' => Type::DATE, 'required' => false],
                'lunch' => ['type' => Type::TIME, 'required' => false],
                'letter_sent_at' => ['type' => Type::DATETIME, 'required' => false],
            ])
        )->generate();

        $this->assertStringContainsString('{{ $student->birthday !== null ? $student->birthday->format(\'Y-m-d\') : null }}', $code);
        $this->assertStringContainsString('{{ $student->lunch }}', $code);
        $this->assertStringContainsString('{{ $student->letter_sent_at !== null ? $student->letter_sent_at->format(\'Y-m-d H:i:s\') : null }}', $code);
    }

    /** @test */
    public function it_shows_navigation_links()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString('{{ $students->links() }}', $code);
    }

    /** @not-test TODO: Needs a way to mock relationships */
    public function it_shows_relationships()
    {
        $code = $this->generator(
            $this->mockTable('avatars')
        )->generate();

        $this->assertStringContainsString('$avatar->user->name', $code);

        $code = $this->generator(
            $this->mockTable('sales')
        )->generate();

        $this->assertStringContainsString('Product #{{ $sale->product_id }}', $code);
    }

    /** @not-test TODO: Needs a way to mock relationships */
    public function it_renders_links_on_relationships()
    {
        $code = $this->generator(
            $this->mockTable('sales')
        )->generate();

        $this->assertStringContainsString(
            '<a href="{{ route(\'products.show\', [\'product\' => $sale->product_id]) }}">',
            $code
        );
    }
}
