<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', TaskStatus::values())->default(TaskStatus::Todo->value);

            // RESTRICT: a user with tasks can't be hard-deleted by accident;
            // tasks must be reassigned first.
            $table->foreignId('assigned_to')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Covers the hot paths of GET /api/tasks:
            //  - "my tasks" (+ optional status filter), newest first
            //  - admin filtering by status
            $table->index(['assigned_to', 'status', 'id']);
            $table->index(['status', 'id']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
