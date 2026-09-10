<?php

namespace App\Livewire;

use App\Models\Saint;
use App\Services\SaintEditorPermissionService;
use App\Services\SaintEditorService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SaintEditor extends Component
{
    #[Locked]
    public Saint $saint;

    public array $form = [];

    public string $status = '';

    public function mount(Saint $saint): void
    {
        app(SaintEditorPermissionService::class)->authorizeEditor(auth()->user());
        $this->saint = $saint;
        $this->status = session('status', '');
        foreach (app(SaintEditorService::class)->rules($saint) as $field => $rules) {
            $this->form[$field] = $saint->getAttribute($field) ?? '';
        }
        $this->form['is_martyr'] = (bool) $saint->is_martyr;
        $this->form['is_doctor'] = (bool) $saint->is_doctor;
    }

    public function save(): void
    {
        app(SaintEditorPermissionService::class)->authorizeEditor(auth()->user());
        $this->status = '';
        $editor = app(SaintEditorService::class);
        $rules = collect($editor->rules($this->saint))->mapWithKeys(fn ($rules, $field) => ['form.'.$field => $rules])->all();
        $validated = $this->validate($rules);
        $oldSlug = $this->saint->slug;
        $this->saint = $editor->update($this->saint, $validated['form']);
        $this->status = 'Saint updated.';
        if ($oldSlug !== $this->saint->slug) {
            session()->flash('status', $this->status);
            $this->redirectRoute('saints.edit', $this->saint, navigate: true);
        }
    }

    public function render()
    {
        $permissions = app(SaintEditorPermissionService::class);
        $permissions->authorizeEditor(auth()->user());

        return view('livewire.saint-editor', [
            'canManageStaff' => $permissions->canManageStaff(auth()->user()),
            'statusOptions' => SaintEditorService::STATUS_OPTIONS,
            'yearQualifierOptions' => SaintEditorService::YEAR_QUALIFIER_OPTIONS,
            'imageVariantOptions' => app(SaintEditorService::class)->imageVariantOptions(),
        ]);
    }
}
