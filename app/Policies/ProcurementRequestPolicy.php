<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProcurementRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcurementRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProcurementRequest');
    }

    public function view(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('View:ProcurementRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProcurementRequest');
    }

    public function update(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('Update:ProcurementRequest');
    }

    public function delete(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('Delete:ProcurementRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProcurementRequest');
    }

    public function restore(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('Restore:ProcurementRequest');
    }

    public function forceDelete(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('ForceDelete:ProcurementRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProcurementRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProcurementRequest');
    }

    public function replicate(AuthUser $authUser, ProcurementRequest $procurementRequest): bool
    {
        return $authUser->can('Replicate:ProcurementRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProcurementRequest');
    }

}