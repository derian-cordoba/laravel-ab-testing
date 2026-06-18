<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional target sample size column used by the dashboard progress
 * badge. Null means the badge is hidden; a positive integer shows progress
 * toward the goal. Set via the dashboard controls panel.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->unsignedInteger('target_sample_size')->nullable()->after('traffic_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->dropColumn('target_sample_size');
        });
    }
};
