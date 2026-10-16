<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public const DEFAULT_CATEGORIES = [
        ['name' => 'عمومی', 'color' => 'slate'],
        ['name' => 'توسعه', 'color' => 'indigo'],
        ['name' => 'طراحی', 'color' => 'violet'],
        ['name' => 'فروش', 'color' => 'emerald'],
    ];

    public function __invoke(User $owner, string $name, string $color = 'amber'): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $color) {
            $workspace = new Workspace(['name' => $name, 'color' => $color]);
            $workspace->owner_id = $owner->id;
            $workspace->save();

            $workspace->addMember($owner, WorkspaceRole::Owner);

            foreach (self::DEFAULT_CATEGORIES as $category) {
                $workspace->categories()->create($category);
            }

            $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            $owner->forgetRoles();

            AuditLog::record('workspace.created', $workspace, userId: $owner->id);

            return $workspace;
        });
    }
}
