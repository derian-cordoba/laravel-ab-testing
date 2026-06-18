<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the variant set for each experiment version. Populated when an
 * experiment is registered or started so the dashboard can display variant
 * names, weights, and the control arm even when the code definition is not
 * available (runtime-defined experiments, or after a class is deleted).
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_variants', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experiment_id')
                ->constrained('ab_testing_experiments')
                ->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('weight');
            $table->boolean('is_control')->default(false);
            $table->timestamps();

            $table->unique(['experiment_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_variants');
    }
};
