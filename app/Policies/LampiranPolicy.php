<?php

namespace App\Policies;

use App\Models\Lampiran;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LampiranPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    public function view(User $user, Lampiran $lampiran): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($lampiran->diunggah_oleh === $user->id) {
            return true;
        }

        $parent = $lampiran->lampiranable;
        if ($parent && $parent->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lampiran $lampiran): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lampiran $lampiran): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lampiran $lampiran): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lampiran $lampiran): bool
    {
        //
    }
}
