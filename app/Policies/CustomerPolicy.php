<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Customer;
use App\Models\User;
use App\Models\Workspace;

class CustomerPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return (bool) $user->roleIn($workspace)?->atLeast(WorkspaceRole::Member);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->viewAny($user, $workspace);
    }

    public function view(User $user, Customer $customer): bool
    {
        return (bool) $user->roleIn($customer->workspace_id)?->atLeast(WorkspaceRole::Member);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return (bool) $user->roleIn($customer->workspace_id)?->atLeast(WorkspaceRole::Admin)
            || $customer->created_by === $user->id;
    }
}
