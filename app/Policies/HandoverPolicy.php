<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Handover;
use Illuminate\Auth\Access\HandlesAuthorization;

class HandoverPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Handover');
    }

    public function view(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('View:Handover');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Handover');
    }

    public function update(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('Update:Handover');
    }

    public function delete(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('Delete:Handover');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Handover');
    }

    public function restore(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('Restore:Handover');
    }

    public function forceDelete(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('ForceDelete:Handover');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Handover');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Handover');
    }

    public function replicate(AuthUser $authUser, Handover $handover): bool
    {
        return $authUser->can('Replicate:Handover');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Handover');
    }

}