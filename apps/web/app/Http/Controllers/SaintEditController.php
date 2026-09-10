<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use App\Services\SaintEditorPermissionService;
use App\Services\SaintEditorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaintEditController extends Controller
{
    public function __construct(
        private readonly SaintEditorPermissionService $permissions,
        private readonly SaintEditorService $saintEditor,
    ) {}

    public function edit(Request $request, Saint $saint): View
    {
        $this->permissions->authorizeEditor($request->user());

        return view('saints.edit', [
            'saint' => $saint,
            'canManageStaff' => $this->permissions->canManageStaff($request->user()),
            'statusOptions' => SaintEditorService::STATUS_OPTIONS,
            'yearQualifierOptions' => SaintEditorService::YEAR_QUALIFIER_OPTIONS,
            'imageVariantOptions' => $this->saintEditor->imageVariantOptions(),
        ]);
    }

    public function update(Request $request, Saint $saint): RedirectResponse
    {
        $this->permissions->authorizeEditor($request->user());

        $validated = $request->validate($this->saintEditor->rules($saint));

        $saint = $this->saintEditor->update($saint, $validated);

        return redirect()
            ->route('saints.profile', $saint)
            ->with('status', 'Saint updated.');
    }
}
