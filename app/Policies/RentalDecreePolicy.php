<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RentalDecree;
use Illuminate\Auth\Access\HandlesAuthorization;

class RentalDecreePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RentalDecree');
    }

    public function view(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('View:RentalDecree');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RentalDecree');
    }

    public function update(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('Update:RentalDecree');
    }

    public function delete(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('Delete:RentalDecree');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RentalDecree');
    }

    public function restore(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('Restore:RentalDecree');
    }

    public function forceDelete(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('ForceDelete:RentalDecree');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RentalDecree');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RentalDecree');
    }

    public function replicate(AuthUser $authUser, RentalDecree $rentalDecree): bool
    {
        return $authUser->can('Replicate:RentalDecree');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RentalDecree');
    }

}