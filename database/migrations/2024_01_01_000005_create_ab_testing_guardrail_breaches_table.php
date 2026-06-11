<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_guardrail_breaches', static function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('metric_key');
            $table->string('variant_key');
            $table->double('observed_value');
            $table->double('threshold_value');
            $table->timestamp('breached_at');
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();

            $table->index(['experiment_key', 'metric_key'], 'ab_breaches_exp_metric_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_guardrail_breaches');
    }
};
