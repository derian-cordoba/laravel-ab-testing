<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an allowed_environments JSON column to both the experiments and feature-
 * flag-states tables. A null value means "all environments" (fully backwards-
 * compatible); an empty JSON array means "no environment" (effectively disabled
 * everywhere); a non-empty array restricts activation to the listed values.
 *
 * Values match ABTests\Enums\Environment: 'local', 'staging', 'production'.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->json('allowed_environments')->nullable()->after('status');
        });

        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->json('allowed_environments')->nullable()->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->dropColumn('allowed_environments');
        });

        Schema::table('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->dropColumn('allowed_environments');
        });
    }
};
