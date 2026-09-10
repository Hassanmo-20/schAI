<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('type', 20)->index();
            $table->dateTime('deadline')->index();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('batch_id');
            $table->index(['batch_id', 'is_active', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
