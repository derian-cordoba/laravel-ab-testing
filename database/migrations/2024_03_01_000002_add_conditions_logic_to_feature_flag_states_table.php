<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a conditions_logic column to ab_testing_feature_flag_states so that
 * targeting conditions can be combined with AND ("all") or OR ("any") logic.
 * Defaults to "all" (AND) to preserve the behavior of existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->string('conditions_logic')->default('all')->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->dropColumn('conditions_logic');
        });
    }
};
