<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ActiveAdminGuard
{
    public function assertAnotherActiveAdminExists(User $admin): void
    {
        $anotherAdminExists = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereKeyNot($admin->id)
            ->exists();

        if (! $anotherAdminExists) {
            throw ValidationException::withMessages([
                'is_active' => ['At least one active admin is required.'],
            ]);
        }
    }
}
