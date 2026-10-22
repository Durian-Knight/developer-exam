<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_a_token(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/login', ['email' => 'alice@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_wrong_password_returns_422(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $this->postJson('/api/login', ['email' => 'alice@example.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_token_works_and_logout_revokes_it(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);
        $token = $this->postJson('/api/login', ['email' => 'alice@example.com', 'password' => 'password'])
            ->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'alice@example.com');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
