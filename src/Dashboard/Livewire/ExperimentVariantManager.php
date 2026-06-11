<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Application\Commands\AddVariantCommand;
use ABTests\Application\Commands\RemoveVariantCommand;
use ABTests\Application\Commands\UpdateVariantCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Registry\ExperimentRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Manages the variant allocation for a single experiment. Renders a read-only
 * allocation panel for code-defined experiments and experiments that are no
 * longer in a mutable state (running, completed, archived). For runtime-defined
 * experiments in draft or scheduled status it renders a full inline CRUD
 * interface.
 */
final class ExperimentVariantManager extends Component
{
    public string $experimentKey = '';

    // ── Inline edit state ────────────────────────────────────────────────────
    public ?int $editingId = null;
    public string $editKey = '';
    public int $editWeight = 50;
    public bool $editIsControl = false;

    // ── Add-form state ───────────────────────────────────────────────────────
    public bool $showAddForm = false;
    public string $newKey = '';
    public int $newWeight = 0;
    public bool $newIsControl = false;

    // ── Feedback ─────────────────────────────────────────────────────────────
    public string $flashMessage = '';
    public string $flashType = 'success';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    // ── Listeners ────────────────────────────────────────────────────────────

    #[On('experiment-updated')]
    public function onExperimentUpdated(): void
    {
        $this->resetEditState();
    }

    // ── Edit actions ─────────────────────────────────────────────────────────

    public function startEdit(int $id): void
    {
        /** @var VariantModel|null $variant */
        $variant = VariantModel::query()->find($id);

        if ($variant === null) {
            return;
        }

        $this->showAddForm  = false;
        $this->editingId    = $id;
        $this->editKey      = $variant->key;
        $this->editWeight   = $variant->weight;
        $this->editIsControl = $variant->is_control;
        $this->flashMessage = '';
    }

    public function cancelEdit(): void
    {
        $this->resetEditState();
    }

    public function saveEdit(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->editKey = trim($this->editKey);

        if ($this->editKey === '') {
            $this->flashMessage = 'Variant key cannot be empty.';
            $this->flashType    = 'error';

            return;
        }

        try {
            app(CommandBus::class)->dispatch(new UpdateVariantCommand(
                experimentKey:   $this->experimentKey,
                variantId:       $this->editingId,
                variantKey:      $this->editKey,
                weight:          $this->editWeight,
                isControl:       $this->editIsControl,
                actorIdentifier: $this->actorIdentifier(),
            ));

            $this->flashMessage = 'Variant updated.';
            $this->flashType    = 'success';
            $this->resetEditState();
            $this->dispatch('experiment-updated');
        } catch (InvalidVariantOperation $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType    = 'error';
        } catch (Throwable $e) {
            $this->flashMessage = 'Unexpected error: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    // ── Add actions ───────────────────────────────────────────────────────────

    public function startAdd(): void
    {
        $this->resetEditState();
        $this->showAddForm  = true;
        $this->newKey       = '';
        $this->newWeight    = 0;
        $this->newIsControl = false;
        $this->flashMessage = '';
    }

    public function cancelAdd(): void
    {
        $this->showAddForm  = false;
        $this->flashMessage = '';
    }

    public function saveAdd(): void
    {
        $this->newKey = trim($this->newKey);

        if ($this->newKey === '') {
            $this->flashMessage = 'Variant key cannot be empty.';
            $this->flashType    = 'error';

            return;
        }

        try {
            app(CommandBus::class)->dispatch(new AddVariantCommand(
                experimentKey:   $this->experimentKey,
                variantKey:      $this->newKey,
                weight:          $this->newWeight,
                isControl:       $this->newIsControl,
                actorIdentifier: $this->actorIdentifier(),
            ));

            $this->flashMessage = 'Variant added.';
            $this->flashType    = 'success';
            $this->showAddForm  = false;
            $this->dispatch('experiment-updated');
        } catch (InvalidVariantOperation $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType    = 'error';
        } catch (Throwable $e) {
            $this->flashMessage = 'Unexpected error: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    // ── Remove ────────────────────────────────────────────────────────────────

    public function removeVariant(int $id): void
    {
        try {
            app(CommandBus::class)->dispatch(new RemoveVariantCommand(
                experimentKey:   $this->experimentKey,
                variantId:       $id,
                actorIdentifier: $this->actorIdentifier(),
            ));

            $this->flashMessage = 'Variant removed.';
            $this->flashType    = 'success';
            $this->resetEditState();
            $this->dispatch('experiment-updated');
        } catch (InvalidVariantOperation $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType    = 'error';
        } catch (Throwable $e) {
            $this->flashMessage = 'Unexpected error: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $model      = ExperimentModel::query()->firstWhere('key', $this->experimentKey);
        $variants   = collect();
        $totalWeight = 0;
        $isEditable  = false;
        $hasCodeDefinition = false;
        $status = null;

        if ($model !== null) {
            $status = ExperimentStatus::from($model->status);

            $definition = null;

            try {
                $definition        = app(ExperimentRegistry::class)->findByKey($this->experimentKey);
                $hasCodeDefinition = true;
            } catch (Throwable) {
                $hasCodeDefinition = false;
            }

            $variants = $model->variants()->orderByDesc('is_control')->orderBy('key')->get();

            // No DB snapshot yet — fall back to the code definition's allocation
            // so variants are always visible (e.g. before the first start).
            if ($variants->isEmpty() && $definition !== null) {
                $codeVariants = $definition->allocation->variants;
                usort($codeVariants, static fn ($a, $b): int => $b->isControl() <=> $a->isControl() ?: strcmp($a->key(), $b->key()));

                $variants = collect($codeVariants)->map(static fn ($v): object => (object) [
                    'id'         => null,
                    'key'        => $v->key(),
                    'weight'     => $v->weight(),
                    'is_control' => $v->isControl(),
                ]);
            }

            $totalWeight     = (int) $variants->sum('weight');
            $isLockedStatus = in_array($status, [ExperimentStatus::completed, ExperimentStatus::archived], true);
            $isEditable     = ! $isLockedStatus && ! $hasCodeDefinition;
        }

        return view('ab-testing::livewire.experiment-variant-manager', compact(
            'model',
            'variants',
            'totalWeight',
            'isEditable',
            'hasCodeDefinition',
            'status',
        ) + [
            'flashMessage' => $this->flashMessage,
            'flashType'    => $this->flashType,
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function resetEditState(): void
    {
        $this->editingId = null;
        $this->editKey   = '';
        $this->editWeight = 50;
        $this->editIsControl = false;
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
