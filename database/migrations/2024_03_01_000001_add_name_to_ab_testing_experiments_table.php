<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional human-readable display name to experiments. Useful for
 * runtime-defined experiments created from the dashboard, where there is no
 * code-level #[AsExperiment(name: '...')] attribute to read from.
 * Code-defined names always take precedence in the UI.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->string('name')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_experiments', static function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
