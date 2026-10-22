<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Demo accounts - every password is "password". */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $alice = User::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Tran', 'email' => 'bob@example.com']);

        Task::factory()->count(5)->for($admin, 'assignee')->create();
        Task::factory()->count(20)->for($alice, 'assignee')->create();
        Task::factory()->count(15)->for($bob, 'assignee')->create();
    }
}
