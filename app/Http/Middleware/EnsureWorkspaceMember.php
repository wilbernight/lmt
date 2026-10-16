<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every /w/{workspace} route: the user must be a member, otherwise the
 * workspace is reported as missing so its existence is not disclosed.
 * Optionally requires a minimum role: ->middleware('workspace:admin').
 */
class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        $workspace = $request->route('workspace');
        $user = $request->user();

        abort_unless($workspace instanceof Workspace && $user, 404);

        $role = $user->roleIn($workspace);
        abort_if($role === null, 404);

        if ($minimumRole !== null) {
            abort_unless($role->atLeast(WorkspaceRole::from($minimumRole)), 403);
        }

        if ($user->current_workspace_id !== $workspace->id) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->saveQuietly();
        }

        app()->instance(Workspace::class, $workspace);

        return $next($request);
    }
}
