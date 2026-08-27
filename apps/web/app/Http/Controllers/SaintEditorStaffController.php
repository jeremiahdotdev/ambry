<?php

namespace App\Http\Controllers;

use App\Models\SaintEditorPermission;
use App\Services\SaintEditorPermissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaintEditorStaffController extends Controller
{
    public function __construct(
        private readonly SaintEditorPermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        $this->permissions->authorizeStaffManager($request->user());

        return view('saints.edit-staff', [
            'staff' => $this->permissions->staff(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->permissions->authorizeStaffManager($request->user());

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->permissions->addEditorEmail($request->user(), $validated['email']);

        return redirect()
            ->route('saints.edit-staff.index')
            ->with('status', 'Editor added.');
    }

    public function destroy(Request $request, SaintEditorPermission $permission): RedirectResponse
    {
        $this->permissions->removeEditorEmail($request->user(), $permission);

        return redirect()
            ->route('saints.edit-staff.index')
            ->with('status', 'Editor removed.');
    }
}
