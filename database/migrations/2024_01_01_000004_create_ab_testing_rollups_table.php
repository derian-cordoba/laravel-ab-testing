<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derived cache for the dashboard results console. Written only by the
 * RefreshRollupsJob; never touched in the request path. The watermark column
 * (updated_through_at) tracks the last event timestamp processed so the job
 * can run incrementally.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_rollups', static function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('variant_key');
            $table->string('metric_key');
            $table->unsignedBigInteger('count_of_units')->default(0);
            $table->unsignedBigInteger('exposures')->default(0);
            $table->double('sum_of_values')->default(0.0);
            $table->double('sum_of_squared_values')->default(0.0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamp('updated_through_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(
                ['experiment_key', 'variant_key', 'metric_key'],
                'ab_rollups_unique_index',
            );
            $table->index('experiment_key', 'ab_rollups_exp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_rollups');
    }
};
