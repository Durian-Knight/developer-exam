<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_all_users_and_user_only_sees_self(): void
    {
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create();
        User::factory()->create();

        Sanctum::actingAs($admin);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(3);

        Sanctum::actingAs($alice);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $alice->id);
    }

    public function test_only_admin_can_create_users(): void
    {
        $payload = ['name' => 'Carol', 'email' => 'carol@example.com', 'password' => 'secret123', 'role' => 'user'];

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/users', $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/users', $payload)
            ->assertCreated()
            ->assertJsonPath('email', 'carol@example.com')
            ->assertJsonMissingPath('password');
    }
}
