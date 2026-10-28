<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DamageReport;
use Illuminate\Auth\Access\HandlesAuthorization;

class DamageReportPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DamageReport');
    }

    public function view(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('View:DamageReport');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DamageReport');
    }

    public function update(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('Update:DamageReport');
    }

    public function delete(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('Delete:DamageReport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DamageReport');
    }

    public function restore(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('Restore:DamageReport');
    }

    public function forceDelete(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('ForceDelete:DamageReport');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DamageReport');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DamageReport');
    }

    public function replicate(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return $authUser->can('Replicate:DamageReport');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DamageReport');
    }

}