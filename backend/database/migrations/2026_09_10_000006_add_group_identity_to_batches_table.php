<?php

use App\Enums\BatchYear;
use App\Enums\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a batch row an addressable academic *group*: (batch_year, department).
 *
 * The existing `batches` table already carried a free-text `department` and
 * `academic_year`, but nothing tied a user to a specific year+department pair
 * as a unit. Adding `batch_year` alongside the (now constrained) department,
 * plus a composite unique index, makes the pair the group's real identity —
 * which is what every isolation rule in the app keys off.
 *
 * Deliberately additive: `batch_id` stays the foreign key everywhere, so all
 * existing task/user scoping keeps working untouched.
 *
 * No group rows are inserted here — registration resolves (and creates) the
 * group it needs through `Batch::resolveGroup()`, and the demo seeder creates
 * the full set. A migration that pre-populated all ten groups would make every
 * `Batch::factory()` in the test suite collide with the unique index below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Nullable so existing rows survive the migration; the unique index
            // below permits multiple NULLs, so legacy rows never collide.
            $table->string('batch_year', 10)->nullable()->after('name');
        });

        // Backfill: pre-existing rows stored the year in `academic_year`, but
        // only values that are actually one of the offered batches become a
        // group identity. Anything else (an old "2026" demo batch) stays NULL
        // so the enum casts on the model can never meet an unknown value.
        DB::table('batches')
            ->whereNull('batch_year')
            ->whereIn('academic_year', BatchYear::values())
            ->update(['batch_year' => DB::raw('academic_year')]);

        // `department` was free text before this migration and is now a
        // constrained enum. Anything outside the enum is cleared rather than
        // guessed at — those rows keep their `name`, which Batch::groupLabel()
        // falls back to, so nothing in the UI goes blank.
        DB::table('batches')
            ->whereNotNull('department')
            ->whereNotIn('department', Department::values())
            ->update(['department' => null]);

        Schema::table('batches', function (Blueprint $table) {
            // The group identity. One row per (year, department) pair — this is
            // what stops two "2027 CCE" groups from ever existing.
            $table->unique(['batch_year', 'department'], 'batches_group_unique');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropUnique('batches_group_unique');
            $table->dropColumn('batch_year');
        });
    }
};
