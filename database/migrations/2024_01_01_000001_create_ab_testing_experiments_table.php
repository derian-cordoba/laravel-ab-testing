<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational mirror of a code-defined experiment. Structure (variants,
 * allocation, metrics) lives in code; this table holds the mutable state that
 * the dashboard controls: status, traffic percentage, and kill switch.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_experiments', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('layer')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('traffic_percentage')->default(0);
            $table->boolean('is_killed')->default(false);
            $table->timestamp('killed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_experiments');
    }
};
