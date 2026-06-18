<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Commands\ApproveExperimentCommand;
use ABTests\Application\Commands\RejectExperimentCommand;
use ABTests\Application\Commands\RequestApprovalCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Embedded Livewire component that renders the approval panel for a single
 * experiment. Shows the latest approval state and exposes request/approve/reject
 * actions. Only visible when governance.approval_required is true.
 */
final class ExperimentApprovalPanel extends Component
{
    public string $experimentKey = '';

    public string $notes = '';

    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        //
    }

    public function requestApproval(): void
    {
        $this->dispatchCommand(new RequestApprovalCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
            notes: $this->notes ?: null,
        ));
        $this->notes = '';
    }

    public function approve(): void
    {
        $this->dispatchCommand(new ApproveExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
            notes: $this->notes ?: null,
        ));
        $this->notes = '';
    }

    public function reject(): void
    {
        $this->dispatchCommand(new RejectExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
            notes: $this->notes ?: null,
        ));
        $this->notes = '';
    }

    public function render(): View
    {
        $approvalRequired = (bool) config('ab-testing.governance.approval_required', false);

        /** @var ExperimentApprovalModel|null $latestApproval */
        $latestApproval = ExperimentApprovalModel::query()
            ->where('experiment_key', $this->experimentKey)
            ->latest()
            ->first();

        $approvalHistory = ExperimentApprovalModel::query()
            ->where('experiment_key', $this->experimentKey)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('ab-testing::livewire.experiment-approval-panel', compact(
            'approvalRequired',
            'latestApproval',
            'approvalHistory',
        ));
    }

    private function dispatchCommand(object $command): void
    {
        try {
            app(CommandBus::class)->dispatch($command);

            $this->flashMessage = 'Action completed successfully.';
            $this->flashType = 'success';
            $this->dispatch('experiment-updated');
        } catch (Throwable $e) {
            $this->flashMessage = 'Error: '.$e->getMessage();
            $this->flashType = 'error';
        }
    }

    private function actorIdentifier(): string
    {
        $user = Auth::user();

        if ($user !== null && method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }

        return 'dashboard';
    }
}
