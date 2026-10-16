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

        if ($role === null) {
            return self::unavailable($request);
        }

        // A workspace locked by a site administrator is closed to its members.
        if ($workspace->suspended_at && ! $user->is_admin) {
            abort(423, 'این میزکار توسط مدیر سایت غیرفعال شده است.');
        }

        if ($minimumRole !== null) {
            abort_unless($role->atLeast(WorkspaceRole::from($minimumRole)), 403);
        }

        if ($user->current_workspace_id !== $workspace->id) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->saveQuietly();
        }

        app()->instance(Workspace::class, $workspace);

        return $next($request);
    }

    /**
     * A workspace that does not exist or is not yours: page visits go back to the
     * dashboard (same answer either way, so nothing is disclosed); API calls get 404.
     */
    public static function unavailable(Request $request): Response
    {
        abort_if(! $request->isMethod('GET') || $request->expectsJson(), 404);

        return redirect()->route('dashboard')->with('error', 'این میزکار در دسترس نیست یا به آن دسترسی ندارید.');
    }
}
