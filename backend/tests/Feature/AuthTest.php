<?php

namespace Tests\Feature;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => '2027',
            'department' => 'CCE',
            'role' => 'student',
        ]);

        $group = Batch::where('batch_year', '2027')->where('department', 'CCE')->firstOrFail();

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new.student@example.com')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.batch_id', $group->id)
            ->assertJsonPath('user.batch_year', '2027')
            ->assertJsonPath('user.department', 'CCE')
            ->assertJsonPath('user.batch', '2027 CCE')
            ->assertJsonStructure(['message', 'token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'new.student@example.com', 'role' => 'student']);
        // Password must be stored hashed, never plain.
        $this->assertNotEquals('password123', User::whereEmail('new.student@example.com')->first()->password);
    }

    public function test_registration_reuses_the_existing_group_row(): void
    {
        $group = Batch::factory()->group(BatchYear::Y2029, Department::CSE)->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Joiner',
            'email' => 'joiner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => '2029',
            'department' => 'CSE',
            'role' => 'student',
        ])->assertCreated()->assertJsonPath('user.batch_id', $group->id);

        // Two people picking the same pair must land in ONE group, not two.
        $this->assertSame(1, Batch::where('batch_year', '2029')->where('department', 'CSE')->count());
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Copycat',
            'email' => $user->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => '2027',
            'department' => 'CCE',
            'role' => 'student',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_honours_the_selected_representative_role(): void
    {
        // Product decision: the role is self-selected at sign-up (see
        // RegisterRequest). A representative's powers are still confined to
        // their own group by TaskPolicy, so this never grants cross-group access.
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Group Rep',
            'email' => 'rep@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => '2028++',
            'department' => 'CSE',
            'role' => 'representative',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'representative')
            ->assertJsonPath('user.batch', '2028++ CSE');

        $this->assertSame(Role::Representative, User::whereEmail('rep@example.com')->first()->role);
    }

    public function test_registration_rejects_values_outside_the_offered_choices(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_year' => '1999',
            'department' => 'LAW',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['batch_year', 'department', 'role']);

        $this->assertDatabaseMissing('users', ['email' => 'outsider@example.com']);
    }

    public function test_registration_validates_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'batch_year', 'department', 'role']);
    }

    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.role', 'student')
            ->assertJsonStructure(['message', 'token', 'user']);

        $me = $this->withToken($login->json('token'))->getJson('/api/auth/me');

        $me->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_user_can_logout_and_token_stops_working(): void
    {
        $user = User::factory()->create();

        // Exercise the real token flow: actingAs bypasses token lookup,
        // so it cannot prove revocation.
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Guard instances are shared across requests inside one test, so drop
        // them to force token re-resolution (production boots fresh per request).
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_cannot_reach_protected_endpoints(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token-xyz')->getJson('/api/auth/me')->assertUnauthorized();
    }
}
