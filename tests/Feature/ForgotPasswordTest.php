<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_security_answers');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('system_settings');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id('role_id');
            $table->string('role_name', 15);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id('user_id');
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('email', 100)->unique();
            $table->string('contact_number', 25);
            $table->unsignedBigInteger('role_id');
            $table->unsignedTinyInteger('access_level');
            $table->timestamp('created_at')->nullable();
            $table->string('remember_token')->nullable();
        });

        Schema::create('user_security_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('question');
            $table->string('answer_hash');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->string('setting_value')->nullable();
        });
    }

    public function test_forgot_password_page_has_csrf_token_and_new_password_fields(): void
    {
        $this->withoutVite()
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee('New password')
            ->assertSee('Confirm new password');
    }

    public function test_user_can_load_security_questions_and_reset_password(): void
    {
        $user = $this->createUserWithSecurityAnswers();

        $this->postJson(route('password.questions'), ['username' => $user->username])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'questions' => ['What city were you born in?', 'What was your first school?', 'What is your favorite color?'],
            ]);

        $response = $this->postJson(route('password.reset'), [
            'username' => $user->username,
            'answer1' => 'Manila',
            'answer2' => 'Central School',
            'answer3' => 'Blue',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully. You can now log in with your new password.',
            ])
            ->assertDontSee('brand-new-password');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_incorrect_security_answers_do_not_change_password(): void
    {
        $user = $this->createUserWithSecurityAnswers();
        $oldPassword = $user->password;

        $this->postJson(route('password.reset'), [
            'username' => $user->username,
            'answer1' => 'Wrong answer',
            'answer2' => 'Central School',
            'answer3' => 'Blue',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk()->assertJson([
            'success' => false,
            'message' => 'One or more answers are incorrect.',
        ]);

        $this->assertSame($oldPassword, $user->fresh()->password);
    }

    public function test_new_password_must_be_confirmed_and_at_least_six_characters(): void
    {
        $user = $this->createUserWithSecurityAnswers();

        $this->postJson(route('password.reset'), [
            'username' => $user->username,
            'answer1' => 'Manila',
            'answer2' => 'Central School',
            'answer3' => 'Blue',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    private function createUserWithSecurityAnswers(): User
    {
        $role = Role::query()->create(['role_name' => 'User']);
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'password' => 'old-password',
            'email' => 'testuser@example.com',
            'contact_number' => '1234567890',
            'role_id' => $role->role_id,
            'access_level' => 3,
        ]);

        foreach ([
            ['What city were you born in?', 'Manila'],
            ['What was your first school?', 'Central School'],
            ['What is your favorite color?', 'Blue'],
        ] as [$question, $answer]) {
            $user->securityAnswers()->create([
                'question' => $question,
                'answer_hash' => Hash::make(strtolower($answer)),
            ]);
        }

        return $user;
    }
}
