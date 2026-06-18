<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable JSON column to store targeting conditions for each feature
 * flag. Each condition is a {attribute, operator, expected} tuple that maps
 * directly to the existing Criterion value object. All conditions are evaluated
 * with AND logic before the flag's resolve() method is called.
 *
 * Example stored value:
 *   [
 *     {"attribute": "plan", "operator": "in", "expected": ["pro", "enterprise"]},
 *     {"attribute": "country", "operator": "equals", "expected": "US"}
 *   ]
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->json('conditions')->nullable()->after('rollout_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->dropColumn('conditions');
        });
    }
};
