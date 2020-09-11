<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ControllerGenerator;

class ControllerGeneratorTest extends TestCase
{
    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param TableInformation $table
     *
     * @return ControllerGenerator
     */
    private function generator($table): ControllerGenerator
    {
        return app(ControllerGenerator::class, ['table' => $table]);
    }

    /** @test */
    public function it_can_generate_a_controller()
    {
        $this->generator(
            $this->mockTable('students')
        )->save();

        $this->assertFileExists(app_path('Http/Controllers/StudentController.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->setModelDirectory('Models')->generate();

        $this->assertStringContainsString(
            'use App\Models\Student;',
            $code
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_controller()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString(
            'class StudentController extends Controller',
            $code
        );
    }

    /** @test */
    public function it_uses_the_correct_form_request()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertStringContainsString(
            'use App\Http\Requests\StudentRequest;',
            $code
        );
    }

    /** @test */
    public function it_generates_the_six_crud_standard_methods()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $views = [
            'index',
            'show',
            'create',
            'store',
            'edit',
            'update',
            'destroy',
        ];

        foreach ($views as $method) {
            $this->assertStringContainsString("public function $method(", $code);
        }
    }

    /** @test */
    public function it_generates_a_correct_index()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains("
            public function index()
            {
                return view('students.index', ['students' => Student::paginate()]);
            }
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_show()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains("
            public function show(Student \$student)
            {
                return view('students.show', compact('student'));
            }
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_create()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains("
            public function create()
            {
                return view('students.create');
            }
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_store()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('
            public function store(StudentRequest $request)
            {
                $student = Student::create($request->validated());

                return redirect($student->path());
            }
        ', $code);
    }

    /** @test */
    public function it_generates_a_correct_edit()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains("
            public function edit(Student \$student)
            {
                return view('students.edit', compact('student'));
            }
        ", $code);
    }

    /** @test */
    public function it_generates_a_correct_update()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains('
            public function update(Student $student, StudentRequest $request)
            {
                $student->update($request->validated());

                return redirect($student->path());
            }
        ', $code);
    }

    /** @test */
    public function it_generates_a_correct_destroy()
    {
        $code = $this->generator(
            $this->mockTable('students')
        )->generate();

        $this->assertCodeContains("
            public function destroy(Student \$student)
            {
                \$student->delete();

                return redirect(route('students.index'));
            }
        ", $code);
    }

    /** @test */
    public function it_uses_correct_view_names()
    {
        $code = $this->generator(
            $this->mockTable('detention_methods')
        )->generate();

        $this->assertCodeContains('
            public function index()
            {
                return view(\'detention_methods.index\', [\'detentionMethods\' => DetentionMethod::paginate()]);
            }
        ', $code);
    }
}
