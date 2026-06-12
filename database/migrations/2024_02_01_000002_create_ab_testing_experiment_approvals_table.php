<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the approval lifecycle for experiments. When governance.approval_required
 * is true, an experiment must have a row with status = 'approved' before it can
 * transition to running. Older rows are kept as a history trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_experiment_approvals', static function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('status'); // ApprovalStatus enum value
            $table->string('requested_by');
            $table->string('requested_by_type')->default('user');
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_by_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('experiment_key', 'ab_approvals_experiment_index');
            $table->index(['experiment_key', 'status'], 'ab_approvals_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_experiment_approvals');
    }
};
