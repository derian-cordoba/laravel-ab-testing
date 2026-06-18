<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_audit_log', static function (Blueprint $table): void {
            $table->id();
            $table->string('actor_identifier')->nullable();
            $table->string('actor_type')->nullable(); // 'user' | 'system'
            $table->string('action'); // start | pause | resume | stop | archive | kill | ramp_traffic
            $table->string('experiment_key')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['experiment_key', 'occurred_at'], 'ab_audit_exp_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_audit_log');
    }
};
