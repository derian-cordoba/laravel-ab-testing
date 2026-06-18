<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the last time a feature flag was evaluated in the resolution path.
 * Used by stale-flag detection: a flag that is enabled but not evaluated for
 * longer than the configured threshold is surfaced as stale in the dashboard.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->timestamp('last_evaluated_at')->nullable()->after('killed_at');
            $table->index('last_evaluated_at', 'ab_flag_states_last_evaluated_index');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->dropIndex('ab_flag_states_last_evaluated_index');
            $table->dropColumn('last_evaluated_at');
        });
    }
};
