<?php

namespace Tests\Feature;

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
        $batch = Batch::factory()->create();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_id' => $batch->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new.student@example.com')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.batch_id', $batch->id)
            ->assertJsonStructure(['message', 'token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'new.student@example.com', 'role' => 'student']);
        // Password must be stored hashed, never plain.
        $this->assertNotEquals('password123', User::whereEmail('new.student@example.com')->first()->password);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/register', [
            'name' => 'Copycat',
            'email' => $user->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_id' => $user->batch_id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_cannot_escalate_to_representative(): void
    {
        $batch = Batch::factory()->create();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'batch_id' => $batch->id,
            'role' => 'representative',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'student');
        $this->assertSame(Role::Student, User::whereEmail('sneaky@example.com')->first()->role);
    }

    public function test_registration_validates_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'batch_id']);
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
