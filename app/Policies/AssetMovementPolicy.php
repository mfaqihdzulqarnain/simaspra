<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AssetMovement;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetMovementPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetMovement');
    }

    public function view(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('View:AssetMovement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetMovement');
    }

    public function update(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('Update:AssetMovement');
    }

    public function delete(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('Delete:AssetMovement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetMovement');
    }

    public function restore(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('Restore:AssetMovement');
    }

    public function forceDelete(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('ForceDelete:AssetMovement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetMovement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetMovement');
    }

    public function replicate(AuthUser $authUser, AssetMovement $assetMovement): bool
    {
        return $authUser->can('Replicate:AssetMovement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetMovement');
    }

}