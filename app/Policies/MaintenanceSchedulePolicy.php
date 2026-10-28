<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MaintenanceSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceSchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaintenanceSchedule');
    }

    public function view(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('View:MaintenanceSchedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaintenanceSchedule');
    }

    public function update(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('Update:MaintenanceSchedule');
    }

    public function delete(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('Delete:MaintenanceSchedule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaintenanceSchedule');
    }

    public function restore(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('Restore:MaintenanceSchedule');
    }

    public function forceDelete(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('ForceDelete:MaintenanceSchedule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaintenanceSchedule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaintenanceSchedule');
    }

    public function replicate(AuthUser $authUser, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $authUser->can('Replicate:MaintenanceSchedule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaintenanceSchedule');
    }

}