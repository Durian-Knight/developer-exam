<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    // ---- Listing & filtering -------------------------------------------------

    public function test_admin_sees_all_tasks(): void
    {
        Task::factory()->count(3)->for($this->alice, 'assignee')->create();
        Task::factory()->count(2)->for($this->bob, 'assignee')->create();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'assignee' => ['id', 'name']]], 'meta', 'links']);
    }

    public function test_user_only_sees_their_own_tasks(): void
    {
        Task::factory()->count(3)->for($this->alice, 'assignee')->create();
        Task::factory()->count(2)->for($this->bob, 'assignee')->create();

        Sanctum::actingAs($this->alice);

        $response = $this->getJson('/api/tasks')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([$this->alice->id], array_values(array_unique(array_column($response->json('data'), 'assigned_to'))));

        // Trying to peek at someone else's tasks via the filter returns nothing.
        $this->getJson('/api/tasks?assigned_to='.$this->bob->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filters_by_status_assignee_and_search(): void
    {
        Task::factory()->for($this->alice, 'assignee')->create(['title' => 'Write report', 'status' => 'done']);
        Task::factory()->for($this->alice, 'assignee')->create(['title' => 'Fix bug', 'status' => 'todo']);
        Task::factory()->for($this->bob, 'assignee')->create(['title' => 'Write tests', 'status' => 'done']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/tasks?status=done')->assertJsonCount(2, 'data');
        $this->getJson('/api/tasks?status=done&assigned_to='.$this->alice->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?search=write')->assertJsonCount(2, 'data');
        $this->getJson('/api/tasks?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_list_is_paginated(): void
    {
        Task::factory()->count(30)->for($this->alice, 'assignee')->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/tasks?per_page=10&page=2')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 30);
    }

    // ---- Create -------------------------------------------------------------

    public function test_user_creates_task_assigned_to_themselves_by_default(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson('/api/tasks', ['title' => 'New task', 'due_date' => '2026-12-01'])
            ->assertCreated()
            ->assertJsonPath('title', 'New task')
            ->assertJsonPath('status', 'todo')
            ->assertJsonPath('assigned_to', $this->alice->id)
            ->assertJsonPath('due_date', '2026-12-01');
    }

    public function test_user_cannot_assign_task_to_someone_else(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson('/api/tasks', ['title' => 'Sneaky', 'assigned_to' => $this->bob->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_admin_can_assign_task_to_anyone(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/tasks', ['title' => 'For Bob', 'assigned_to' => $this->bob->id, 'status' => 'in_progress'])
            ->assertCreated()
            ->assertJsonPath('assignee.id', $this->bob->id)
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_create_validation(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/tasks', ['status' => 'nope', 'due_date' => '01/12/2026', 'assigned_to' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'status', 'due_date', 'assigned_to']);
    }

    // ---- Show / Update / Delete ---------------------------------------------

    public function test_user_can_view_update_and_delete_own_task(): void
    {
        $task = Task::factory()->for($this->alice, 'assignee')->create(['status' => 'todo']);
        Sanctum::actingAs($this->alice);

        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('id', $task->id);

        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done', 'title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('title', 'Renamed');

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertSoftDeleted($task);
        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
    }

    public function test_user_cannot_touch_someone_elses_task(): void
    {
        $task = Task::factory()->for($this->bob, 'assignee')->create();
        Sanctum::actingAs($this->alice);

        $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_admin_can_update_and_delete_any_task(): void
    {
        $task = Task::factory()->for($this->bob, 'assignee')->create();
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/tasks/{$task->id}", ['assigned_to' => $this->alice->id])
            ->assertOk()
            ->assertJsonPath('assigned_to', $this->alice->id);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
    }

    public function test_missing_task_returns_json_404(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/tasks/999999')->assertNotFound()->assertJson(['message' => 'Resource not found.']);
    }
}
