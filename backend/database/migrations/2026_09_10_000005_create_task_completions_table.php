<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            // One completion record per student per task — duplicates are impossible.
            $table->unique(['task_id', 'student_id']);
            $table->index('task_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_completions');
    }
};
