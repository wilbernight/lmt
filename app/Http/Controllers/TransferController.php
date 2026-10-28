<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Minute;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Moving tasks, letters and minutes to another workspace (or project). */
class TransferController extends Controller
{
    /** Destinations the signed-in member may pick: my workspaces with their visible projects. */
    public function options(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        // Guests cannot create anything anywhere, so they have no destination.
        $workspaces = $user->workspaces()
            ->whereNull('workspaces.suspended_at')
            ->wherePivotIn('workspace_user.role', ['owner', 'admin', 'member'])
            ->get(['workspaces.id', 'workspaces.name', 'workspaces.color'])
            ->sortBy('name')
            ->values()
            ->map(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'color' => $w->color,
                'projects' => Project::query()->inWorkspace($w)->visibleTo($user, $w)
                    ->whereNull('archived_at')->get(['id', 'name', 'color'])->sortBy('name')->values(),
            ]);

        return response()->json(['workspaces' => $workspaces]);
    }

    public function task(Request $request, Workspace $workspace, Task $task, Transfer $transfer): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', Rule::exists('workspaces', 'id')->whereNull('suspended_at')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->whereNull('archived_at')],
        ]);

        $to = Workspace::findOrFail($data['workspace_id']);
        $project = ($data['project_id'] ?? null) !== null ? Project::findOrFail($data['project_id']) : null;
        abort_if($project !== null && $project->workspace_id !== $to->id, 422, 'پروژه معتبر نیست.');

        $this->authorize('update', $task);
        $this->authorize('contribute', $project ?? $to);

        $task = $transfer->task($task, $workspace, $to, $project, $request->user());

        return response()->json([
            'ok' => true,
            'url' => $request->user()->can('view', $task)
                ? route('tasks.index', [$to, 'task' => $task->id])
                : route('tasks.index', $to),
            'message' => "وظیفه به «{$this->whither($to, $project)}» منتقل شد.",
        ]);
    }

    public function letter(Request $request, Workspace $workspace, Letter $letter, Transfer $transfer): JsonResponse
    {
        $to = $this->destination($request);

        // The sender, or an admin of the source workspace, may move a letter.
        abort_unless($request->user()->id === $letter->sender_id || $request->user()->can('update', $workspace), 403);
        $this->authorize('contribute', $to);

        $letter = $transfer->letter($letter, $workspace, $to, $request->user());

        return response()->json([
            'ok' => true,
            'url' => $request->user()->can('view', $letter)
                ? route('letters.show', [$to, $letter])
                : route('letters.index', $to),
            'message' => "نامه به میزکار «{$to->name}» منتقل شد.",
        ]);
    }

    public function minute(Request $request, Workspace $workspace, Minute $minute, Transfer $transfer): JsonResponse
    {
        $to = $this->destination($request);

        // The organizer (or admin) may move a minute, draft or final.
        $this->authorize('manageActions', $minute);
        $this->authorize('create', [Minute::class, $to]);

        $minute = $transfer->minute($minute, $workspace, $to, $request->user());

        return response()->json([
            'ok' => true,
            'url' => $request->user()->can('view', $minute)
                ? route('minutes.show', [$to, $minute])
                : route('minutes.index', $to),
            'message' => "صورت‌جلسه به میزکار «{$to->name}» منتقل شد.",
        ]);
    }

    // ------------------------------------------------------------------

    private function destination(Request $request): Workspace
    {
        $data = $request->validate([
            'workspace_id' => ['required', Rule::exists('workspaces', 'id')->whereNull('suspended_at')],
        ]);

        return Workspace::findOrFail($data['workspace_id']);
    }

    private function whither(Workspace $to, ?Project $project): string
    {
        return $project?->name ?? $to->name;
    }
}
