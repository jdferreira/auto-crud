<?php

namespace Tests\Unit;

use Tests\TestCase;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Generators\ControllerGenerator;

class ControllerGeneratorTest extends TestCase
{
    /**
     * The directory holding the migrations for these tests.
     *
     * @var string
     */
    protected $migrations = __DIR__ . '/../migrations';

    /**
     * Create a generator that can be used to generate or save the expected file.
     *
     * @param string $table
     * @param string $dir
     *
     * @return ControllerGenerator
     */
    private function generator(string $table): ControllerGenerator
    {
        return app(ControllerGenerator::class, [
            'table' => app(TableInformation::class, ['name' => $table]),
        ]);
    }

    /** @test */
    public function it_can_generate_a_controller()
    {
        $this->generator('users')->save();

        $this->assertFileExists(app_path('Http/Controllers/UserController.php'));
    }

    /** @test */
    public function it_detects_model_namespace()
    {
        $code = $this->generator('users')->setModelDirectory('Models')->save();

        $this->assertStringContainsString(
            'use App\Models\User;',
            $this->files->get(app_path('Http/Controllers/UserController.php'))
        );
    }

    /** @test */
    public function it_uses_the_tablename_to_name_the_controller()
    {
        $code = $this->generator('products')->generate();

        $this->assertStringContainsString(
            'class ProductController extends Controller',
            $code
        );
    }

    /** @test */
    public function it_uses_the_correct_form_request()
    {
        $code = $this->generator('users')->generate();

        $this->assertStringContainsString(
            'use App\Http\Requests\UserRequest;',
            $code
        );
    }

    /** @test */
    public function it_generates_the_six_crud_standard_methods()
    {
        $code = $this->generator('users')->generate();

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
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            public function index()
            {
                return view('users.index', ['users' => User::paginate()]);
            }
        ", $users);

        $this->assertCodeContains("
            public function index()
            {
                return view('avatars.index', ['avatars' => Avatar::paginate()]);
            }
        ", $avatars);
    }

    /** @test */
    public function it_generates_a_correct_show()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            public function show(User \$user)
            {
                return view('users.show', compact('user'));
            }
        ", $users);

        $this->assertCodeContains("
            public function show(Avatar \$avatar)
            {
                return view('avatars.show', compact('avatar'));
            }
        ", $avatars);
    }

    /** @test */
    public function it_generates_a_correct_create()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            public function create()
            {
                return view('users.create');
            }
        ", $users);

        $this->assertCodeContains("
            public function create()
            {
                return view('avatars.create');
            }
        ", $avatars);
    }

    /** @test */
    public function it_generates_a_correct_store()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains('
            public function store(UserRequest $request)
            {
                $user = User::create($request->validated());

                return redirect($user->path());
            }
        ', $users);

        $this->assertCodeContains('
            public function store(AvatarRequest $request)
            {
                $avatar = Avatar::create($request->validated());

                return redirect($avatar->path());
            }
        ', $avatars);
    }

    /** @test */
    public function it_generates_a_correct_edit()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            public function edit(User \$user)
            {
                return view('users.edit', compact('user'));
            }
        ", $users);

        $this->assertCodeContains("
            public function edit(Avatar \$avatar)
            {
                return view('avatars.edit', compact('avatar'));
            }
        ", $avatars);
    }

    /** @test */
    public function it_generates_a_correct_update()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains('
            public function update(User $user, UserRequest $request)
            {
                $user->update($request->validated());

                return redirect($user->path());
            }
        ', $users);

        $this->assertCodeContains('
            public function update(Avatar $avatar, AvatarRequest $request)
            {
                $avatar->update($request->validated());

                return redirect($avatar->path());
            }
        ', $avatars);
    }

    /** @test */
    public function it_generates_a_correct_destroy()
    {
        $users = $this->generator('users')->generate();
        $avatars = $this->generator('avatars')->generate();

        $this->assertCodeContains("
            public function destroy(User \$user)
            {
                \$user->delete();

                return redirect(route('users.index'));
            }
        ", $users);

        $this->assertCodeContains("
            public function destroy(Avatar \$avatar)
            {
                \$avatar->delete();

                return redirect(route('avatars.index'));
            }
        ", $avatars);
    }

    /** @test */
    public function it_uses_correct_view_names()
    {
        $code = $this->generator('payment_methods')->generate();

        $this->assertCodeContains('
            public function index()
            {
                return view(\'payment_methods.index\', [\'paymentMethods\' => PaymentMethod::paginate()]);
            }
        ', $code);
    }
}
