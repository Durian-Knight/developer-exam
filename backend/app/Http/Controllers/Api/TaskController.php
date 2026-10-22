<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    /**
     * GET /api/tasks
     * Query params: status, assigned_to, search, sort_by, sort_dir, page, per_page.
     * Non-admin users only ever see tasks assigned to themselves.
     */
    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->validated();

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->with('assignee:id,name,email,role')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn ($q, $userId) => $q->where('assigned_to', $userId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $q->where('title', 'like', "%{$escaped}%");
            })
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_dir'] ?? 'desc')
            ->orderByDesc('id') // stable ordering for pagination
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /** POST /api/tasks */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validated();
        // Regular users can only create tasks for themselves; admins default to themselves too.
        $data['assigned_to'] ??= $request->user()->id;

        $task = Task::create($data)->load('assignee:id,name,email,role');

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    /** GET /api/tasks/{task} */
    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load('assignee:id,name,email,role'));
    }

    /** PUT/PATCH /api/tasks/{task} - partial updates are allowed. */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task->fresh()->load('assignee:id,name,email,role'));
    }

    /** DELETE /api/tasks/{task} - soft delete (row kept with deleted_at for audit/restore). */
    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
