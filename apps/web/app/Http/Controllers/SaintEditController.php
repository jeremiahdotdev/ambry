<?php

namespace App\Http\Controllers;

use App\Models\Saint;
use App\Services\SaintEditorPermissionService;
use App\Services\SaintEditorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $validated = $request->validate([
            'primary_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('saints', 'slug')->ignore($saint->id),
            ],
            'canonical_status' => ['required', Rule::in(array_keys(SaintEditorService::STATUS_OPTIONS))],
            'gender' => ['nullable', 'string', 'max:80'],
            'birth_year' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'birth_year_qualifier' => ['nullable', Rule::in(array_keys(SaintEditorService::YEAR_QUALIFIER_OPTIONS))],
            'death_year' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'death_year_qualifier' => ['nullable', Rule::in(array_keys(SaintEditorService::YEAR_QUALIFIER_OPTIONS))],
            'life_dates' => ['nullable', 'string', 'max:255'],
            'is_martyr' => ['nullable', 'boolean'],
            'is_doctor' => ['nullable', 'boolean'],
            'profile_subtitle' => ['nullable', 'string', 'max:255'],
            'profile_summary' => ['nullable', 'string', 'max:6000'],
            'biography' => ['nullable', 'string', 'max:20000'],
            'image_page_variant' => ['nullable', Rule::in(array_keys($this->saintEditor->imageVariantOptions()))],
        ]);

        $saint = $this->saintEditor->update($saint, $validated);

        return redirect()
            ->route('saints.profile', $saint)
            ->with('status', 'Saint updated.');
    }
}
