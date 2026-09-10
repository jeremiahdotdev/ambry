<?php

namespace App\Livewire;

use App\Models\SaintEditorPermission;
use App\Services\SaintEditorPermissionService;
use Livewire\Component;

class EditorStaff extends Component
{
    public string $email = '';

    public string $status = '';

    public function add(): void
    {
        $permissions = app(SaintEditorPermissionService::class);
        $permissions->authorizeStaffManager(auth()->user());
        $this->validate(['email' => 'required|email|max:255']);
        $permissions->addEditorEmail(auth()->user(), $this->email);
        $this->reset('email');
        $this->status = 'Editor added.';
    }

    public function remove(int $id): void
    {
        $permissions = app(SaintEditorPermissionService::class);
        $permissions->authorizeStaffManager(auth()->user());
        $permissions->removeEditorEmail(auth()->user(), SaintEditorPermission::findOrFail($id));
        $this->status = 'Editor removed.';
    }

    public function render()
    {
        $permissions = app(SaintEditorPermissionService::class);
        $permissions->authorizeStaffManager(auth()->user());

        return view('livewire.editor-staff', ['staff' => $permissions->staff()]);
    }
}
