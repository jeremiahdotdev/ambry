<?php

namespace App\Services;

use App\Models\SaintEditorPermission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaintEditorPermissionService
{
    public function canEditSaints(?User $user): bool
    {
        return $user !== null
            && SaintEditorPermission::query()
                ->where('email', $this->normalizeEmail($user->email))
                ->exists();
    }

    public function canManageStaff(?User $user): bool
    {
        return $user !== null
            && SaintEditorPermission::query()
                ->where('email', $this->normalizeEmail($user->email))
                ->where('role', SaintEditorPermission::ROLE_OWNER)
                ->exists();
    }

    /**
     * @return Collection<int, SaintEditorPermission>
     */
    public function staff(): Collection
    {
        return SaintEditorPermission::query()
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->orderBy('email')
            ->get();
    }

    public function addEditorEmail(User $actor, string $email): SaintEditorPermission
    {
        $this->authorizeStaffManager($actor);

        $normalizedEmail = $this->normalizeEmail($email);
        $permission = SaintEditorPermission::query()
            ->where('email', $normalizedEmail)
            ->first();

        if ($permission) {
            return $permission;
        }

        return SaintEditorPermission::query()->create([
            'email' => $normalizedEmail,
            'role' => SaintEditorPermission::ROLE_EDITOR,
        ]);
    }

    public function removeEditorEmail(User $actor, SaintEditorPermission $permission): void
    {
        $this->authorizeStaffManager($actor);

        if ($permission->isOwner()) {
            throw ValidationException::withMessages([
                'email' => 'The owner cannot be removed from edit staff.',
            ]);
        }

        $permission->delete();
    }

    public function authorizeEditor(?User $user): void
    {
        abort_unless($this->canEditSaints($user), 403);
    }

    public function authorizeStaffManager(?User $user): void
    {
        abort_unless($this->canManageStaff($user), 403);
    }

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
