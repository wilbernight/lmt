<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Events\BoardChanged;
use App\Http\Resources\TaskResource;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Support\Realtime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public const COLORS = ['indigo', 'violet', 'rose', 'orange', 'amber', 'emerald', 'teal', 'sky', 'slate', 'pink'];

    public function index(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();
        $archived = $request->boolean('archived');

        $projects = Project::query()
            ->inWorkspace($workspace)
            ->visibleTo($user, $workspace)
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', $this->like($request->string('q'))))
            ->with('members:id,name,avatar_path,last_seen_at')
            ->addSelect(['tracked_seconds' => TimeEntry::selectRaw('COALESCE(SUM('.TimeEntry::secondsSql().'), 0)')->whereColumn('project_id', 'projects.id')])
            ->withCount([
                'tasks',
                'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'),
                'tasks as overdue_count' => fn ($q) => $q->whereNull('completed_at')->where('due_at', '<', now()),
            ])
            ->latest('updated_at')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'color' => $p->color,
                'visibility' => $p->visibility,
                'due_on' => $p->due_on?->toDateString(),
                'archived' => $p->archived_at !== null,
                'tasks_count' => $p->tasks_count,
                'done_count' => $p->done_count,
                'overdue_count' => $p->overdue_count,
                'members' => $p->members->take(6)->map->toCard(),
                'members_count' => $p->members->count(),
                'tracked_seconds' => (int) $p->tracked_seconds,
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'archived' => $archived,
            'filters' => ['q' => $request->string('q')->toString()],
            'colors' => self::COLORS,
            'canCreate' => $user->can('create', [Project::class, $workspace]),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Project::class, $workspace]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(self::COLORS)],
            'visibility' => ['required', Rule::in(['members', 'workspace'])],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'members' => ['array', 'max:100'],
            'members.*' => ['integer'],
        ]);

        $project = DB::transaction(function () use ($data, $workspace, $request) {
            $project = new Project(Arr::except($data, ['members']));
            $project->workspace_id = $workspace->id;
            $project->created_by = $request->user()->id;
            $project->save();

            $members = collect($workspace->filterMemberIds($data['members'] ?? []))
                ->reject(fn ($id) => $id === $request->user()->id)
                ->mapWithKeys(fn ($id) => [$id => ['role' => ProjectRole::Member->value]])
                ->put($request->user()->id, ['role' => ProjectRole::Manager->value]);

            $project->members()->sync($members);
            $project->createDefaultColumns();

            return $project;
        });

        AuditLog::record('project.created', $project);

        return redirect()->route('projects.show', [$workspace, $project])->with('success', 'پروژه ساخته شد.');
    }

    public function show(Request $request, Workspace $workspace, Project $project): Response
    {
        $this->authorize('view', $project);
        $user = $request->user();

        $project->load(['members:id,name,avatar_path,last_seen_at', 'columns']);

        $tasks = $project->tasks()
            ->with(TaskResource::withCard())
            ->withCount(TaskResource::cardCounts())
            ->when($request->filled('assignee'), fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($request->integer('assignee'))))
            ->when($request->filled('label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->string('label'))))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', $this->like($request->string('q'))))
            ->orderBy('position')
            ->get();

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'color' => $project->color,
                'visibility' => $project->visibility,
                'starts_on' => $project->starts_on?->toDateString(),
                'due_on' => $project->due_on?->toDateString(),
                'archived' => $project->archived_at !== null,
                'members' => $project->members->map(fn ($m) => [...$m->toCard(), 'role' => $m->pivot->role]),
                'columns' => $project->columns->map->only(['id', 'name', 'color', 'is_done', 'wip_limit', 'position']),
                'tracked_seconds' => (int) TimeEntry::where('project_id', $project->id)->sum(DB::raw(TimeEntry::secondsSql())),
            ],
            'tasks' => TaskResource::collection($tasks),
            'labels' => $workspace->labels()->where('scope', 'task')->get(['id', 'name', 'color']),
            'categories' => $workspace->categories()->get(['id', 'name', 'color']),
            'filters' => $request->only(['assignee', 'label', 'q']),
            'colors' => self::COLORS,
            'can' => [
                'manage' => $user->can('manage', $project),
                'contribute' => $user->can('contribute', $project),
                'delete' => $user->can('delete', $project),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', Rule::in(self::COLORS)],
            'visibility' => ['required', Rule::in(['members', 'workspace'])],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $project->update($data);
        AuditLog::record('project.updated', $project);
        Realtime::send(new BoardChanged($project->id, 'project'));

        return back()->with('success', 'پروژه به‌روزرسانی شد.');
    }

    public function members(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        $data = $request->validate([
            'members' => ['array', 'max:200'],
            'members.*.id' => ['required', 'integer'],
            'members.*.role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $allowed = $workspace->filterMemberIds(array_column($data['members'] ?? [], 'id'));

        $sync = collect($data['members'] ?? [])
            ->filter(fn ($m) => in_array((int) $m['id'], $allowed, true))
            ->mapWithKeys(fn ($m) => [(int) $m['id'] => ['role' => $m['role']]]);

        // The creator always keeps manager access.
        $sync->put($project->created_by, ['role' => ProjectRole::Manager->value]);

        $project->members()->sync($sync);
        AuditLog::record('project.members_updated', $project, ['count' => $sync->count()]);

        return back()->with('success', 'اعضای پروژه به‌روزرسانی شدند.');
    }

    public function archive(Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        $project->forceFill(['archived_at' => $project->archived_at ? null : now()])->save();

        return back()->with('success', $project->archived_at ? 'پروژه بایگانی شد.' : 'پروژه از بایگانی خارج شد.');
    }

    public function destroy(Request $request, Workspace $workspace, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $request->validate(['confirm_name' => ['required', Rule::in([$project->name])]]);

        AuditLog::record('project.deleted', $project, ['name' => $project->name]);
        Task::query()->where('project_id', $project->id)->forceDelete();
        $project->delete();

        return redirect()->route('projects.index', $workspace)->with('success', 'پروژه حذف شد.');
    }
}
