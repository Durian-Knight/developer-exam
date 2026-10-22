<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Admin: everything (see before()).
 * User: any action only on tasks assigned to them. Listing is allowed for everyone,
 * the controller scopes the query with Task::visibleTo().
 */
class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    private function owns(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id;
    }
}
