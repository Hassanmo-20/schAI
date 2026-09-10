<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('password');
            $table->foreignId('batch_id')
                ->nullable()
                ->after('role')
                ->constrained('batches')
                ->nullOnDelete();
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn('role');
        });
    }
};
