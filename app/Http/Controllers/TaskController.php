<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Events\BoardChanged;
use App\Http\Resources\TaskResource;
use App\Models\AuditLog;
use App\Models\Bookmark;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ActivityNotification;
use App\Support\Realtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public const TABS = ['mine', 'following', 'created', 'done', 'all'];

    public function index(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'mine';

        if ($tab === 'all' && ! $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)) {
            $tab = 'mine';
        }

        $query = Task::query()->visibleTo($user, $workspace);

        match ($tab) {
            'mine' => $query->open()->assignedTo($user),
            'following' => $query->open()->where('creator_id', $user->id)
                ->whereDoesntHave('assignees', fn ($q) => $q->whereKey($user->id)),
            'created' => $query->where('creator_id', $user->id),
            'done' => $query->whereNotNull('completed_at')
                ->where(fn ($q) => $q->where('creator_id', $user->id)->orWhereHas('assignees', fn ($a) => $a->whereKey($user->id))),
            'all' => $query,
        };

        $this->applyFilters($query, $request);

        $tasks = $query
            ->with(TaskResource::withCard())
            ->withCount(TaskResource::cardCounts())
            ->when($tab === 'done', fn ($q) => $q->latest('completed_at'), fn ($q) => $q->orderByRaw('due_at is null, due_at')->orderByDesc('priority'))
            ->limit(300)
            ->get();

        return Inertia::render('Tasks/Index', [
            'tab' => $tab,
            'tasks' => TaskResource::collection($tasks),
            'filters' => $request->only(['q', 'category', 'label', 'priority', 'project', 'due', 'sort']),
            ...$this->formOptions($request, $workspace),
            'openTask' => $request->query('task'),
        ]);
    }

    public function calendar(Request $request, Workspace $workspace): Response|JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
        ]);

        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth()->subWeek());
        $to = CarbonImmutable::parse($data['to'] ?? now()->endOfMonth()->addWeek());
        abort_if($from->diffInDays($to) > 62, 422);

        $tasks = Task::query()
            ->visibleTo($request->user(), $workspace)
            ->where(fn (Builder $q) => $q
                ->whereBetween('due_at', [$from, $to])
                ->orWhereBetween('starts_at', [$from, $to]))
            ->with(TaskResource::withCard())
            ->withCount(TaskResource::cardCounts())
            ->limit(1000)
            ->get();

        if ($request->wantsJson()) {
            return response()->json(TaskResource::collection($tasks));
        }

        return Inertia::render('Tasks/Calendar', [
            'tasks' => TaskResource::collection($tasks),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ...$this->formOptions($request, $workspace),
        ]);
    }

    /** Picker options for the quick-create dialog. */
    public function options(Request $request, Workspace $workspace): JsonResponse
    {
        return response()->json($this->formOptions($request, $workspace));
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            ...$this->rules(),
            'title' => ['required', 'string', 'max:200'],
            'checklist' => ['array', 'max:100'],
            'checklist.*' => ['string', 'max:300'],
            'attachments' => ['array', 'max:20'],
            'attachments.*' => ['string', 'size:26'],
        ]);

        $project = $this->resolveProject($workspace, $data['project_id'] ?? null);

        if ($project) {
            $this->authorize('contribute', $project);
        } else {
            $this->authorize('contribute', $workspace);
        }

        $task = DB::transaction(function () use ($data, $workspace, $user, $project) {
            $task = new Task(Arr::only($data, (new Task)->getFillable()));
            $task->workspace_id = $workspace->id;
            $task->creator_id = $user->id;
            $task->project_id = $project?->id;
            $task->category_id = $this->resolveCategory($workspace, $data['category_id'] ?? null);

            if ($project) {
                $column = $this->resolveColumn($project, $data['column_id'] ?? null) ?? $project->columns()->first();
                $task->column_id = $column?->id;
                $task->position = (float) Task::where('column_id', $task->column_id)->max('position') + 1024;
            }

            $task->save();

            $assignees = $workspace->filterMemberIds($data['assignees'] ?? [$user->id]);
            $task->assignees()->sync($assignees);
            $task->watchers()->sync($workspace->filterMemberIds($data['watchers'] ?? []));
            $task->syncLabels($data['labels'] ?? [], 'task');

            foreach (array_values(array_filter(array_map('trim', $data['checklist'] ?? []))) as $i => $body) {
                $task->checklist()->create(['body' => $body, 'position' => $i]);
            }

            $task->claimAttachments($data['attachments'] ?? [], $user->id, $workspace->id);
            $task->log('created');

            return $task;
        });

        $this->notifyAssigned($task, $task->assignees()->get(), $user);

        if ($project) {
            Realtime::send(new BoardChanged($project->id));
        }

        return $request->wantsJson()
            ? response()->json(new TaskResource($task->load(TaskResource::withCard())->loadCount(TaskResource::cardCounts())), 201)
            : back()->with('success', 'وظیفه ساخته شد.');
    }

    public function show(Request $request, Workspace $workspace, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $task);
        $user = $request->user();

        if (! $request->wantsJson()) {
            return redirect()->route('tasks.index', [$workspace, 'task' => $task->id]);
        }

        $task->load([
            ...TaskResource::withCard(),
            'watchers:id,name,avatar_path,last_seen_at',
            'checklist',
            'attachments',
            'comments' => fn ($q) => $q->with(['user:id,name,avatar_path,last_seen_at', 'attachments'])->limit(100),
            'activities' => fn ($q) => $q->with('user:id,name')->limit(50),
            'project.columns',
        ])->loadCount(TaskResource::cardCounts());

        return response()->json([
            ...(new TaskResource($task))->resolve($request),
            'description' => $task->description,
            'remind_at' => $task->remind_at?->toIso8601String(),
            'estimate_minutes' => $task->estimate_minutes,
            'created_at' => $task->created_at->toIso8601String(),
            'progress' => $task->progress(),
            'columns' => $task->project?->columns->map->only(['id', 'name', 'color', 'is_done']) ?? [],
            'watchers' => $task->watchers->map->toCard(),
            'checklist' => $task->checklist->map(fn ($i) => $i->only(['id', 'body', 'is_done', 'position'])),
            'attachments' => $task->attachments->map->toPayload(),
            'comments' => $task->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'progress' => $c->progress,
                'spent_minutes' => $c->spent_minutes,
                'user' => $c->user->toCard(),
                'attachments' => $c->attachments->map->toPayload(),
                'edited' => $c->edited_at !== null,
                'created_at' => $c->created_at->toIso8601String(),
                'can_edit' => $user->can('update', $c),
                'can_delete' => $user->can('delete', $c),
            ]),
            'activities' => $task->activities->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'data' => $a->data,
                'user' => $a->user?->name, 'created_at' => $a->created_at->toIso8601String(),
            ]),
            'time' => [
                'total' => (int) $task->timeEntries()->sum(DB::raw(TimeEntry::secondsSql())),
                'mine' => (int) $task->timeEntries()->where('user_id', $user->id)->sum(DB::raw(TimeEntry::secondsSql())),
                'running' => TimeEntry::where('task_id', $task->id)->where('user_id', $user->id)->running()->first()?->started_at?->toIso8601String(),
                'entries' => $task->timeEntries()->with('user:id,name,avatar_path,last_seen_at')->limit(50)->get()
                    ->map(fn (TimeEntry $e) => [...$e->toPayload(), 'can_delete' => $e->user_id === $user->id || $user->can('delete', $task)]),
            ],
            'bookmarked' => Bookmark::where('user_id', $user->id)->where('bookmarkable_type', 'task')->where('bookmarkable_id', $task->id)->exists(),
            'watching' => $task->watchers->contains('id', $user->id),
            'can' => [
                'update' => $user->can('update', $task),
                'work' => $user->can('work', $task),
                'delete' => $user->can('delete', $task),
                'comment' => $user->can('comment', $task),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $rules = collect($this->rules())->map(fn ($r) => ['sometimes', ...$r])->all();
        $data = $request->validate([
            ...$rules,
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => ['string', 'size:26'],
        ]);

        $before = $task->only(['title', 'priority', 'due_at', 'project_id', 'category_id']);

        DB::transaction(function () use ($task, $data, $workspace, $request) {
            $task->fill(Arr::only($data, $task->getFillable()));

            if (array_key_exists('category_id', $data)) {
                $task->category_id = $this->resolveCategory($workspace, $data['category_id']);
            }

            if (array_key_exists('project_id', $data) && $data['project_id'] !== $task->project_id) {
                $project = $this->resolveProject($workspace, $data['project_id']);

                if ($project) {
                    $this->authorize('contribute', $project);
                }

                $task->project_id = $project?->id;
                $task->column_id = $project?->columns()->first()?->id;
            }

            $task->save();

            if (array_key_exists('labels', $data)) {
                $task->syncLabels($data['labels'] ?? [], 'task');
            }

            if (array_key_exists('watchers', $data)) {
                $task->watchers()->sync($workspace->filterMemberIds($data['watchers'] ?? []));
            }

            if (array_key_exists('assignees', $data)) {
                $this->syncAssignees($task, $workspace, $data['assignees'] ?? [], $request->user());
            }

            if (! empty($data['attachments'])) {
                $task->claimAttachments($data['attachments'], $request->user()->id, $workspace->id);
            }
        });

        $changes = collect($task->only(array_keys($before)))
            ->filter(fn ($v, $k) => (string) $v !== (string) $before[$k])
            ->keys();

        if ($changes->isNotEmpty()) {
            $task->log('updated', ['fields' => $changes->all()]);
        }

        $this->broadcastBoard($task);

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('work', $task);

        $completing = ! $task->isCompleted();

        $task->forceFill([
            'completed_at' => $completing ? now() : null,
            'completed_by' => $completing ? $request->user()->id : null,
        ]);

        if ($task->project_id) {
            $columns = $task->project->columns;
            $target = $completing ? $columns->firstWhere('is_done', true) : $columns->firstWhere('is_done', false);
            $task->column_id = $target?->id ?? $task->column_id;
        }

        $task->save();
        $task->log($completing ? 'completed' : 'reopened');

        if ($completing && $task->creator_id !== $request->user()->id) {
            ActivityNotification::dispatch([$task->creator], new ActivityNotification(
                'task.completed',
                "«{$task->title}» انجام شد",
                null,
                route('tasks.index', [$workspace, 'task' => $task->id]),
                $workspace->id,
                $request->user()->toCard(),
            ));
        }

        $this->broadcastBoard($task);

        return response()->json(['completed_at' => $task->completed_at?->toIso8601String()]);
    }

    /** Drag & drop on the board: change column and/or position. */
    public function move(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        abort_unless($task->project_id, 422);
        $this->authorize('contribute', $task->project);

        $data = $request->validate([
            'column_id' => ['required', 'string', 'size:26'],
            'position' => ['required', 'numeric', 'between:-1000000000,1000000000'],
        ]);

        $column = $this->resolveColumn($task->project, $data['column_id']);
        abort_if($column === null, 422);

        if ($column->wip_limit && $column->id !== $task->column_id && $column->tasks()->whereNull('completed_at')->count() >= $column->wip_limit) {
            throw ValidationException::withMessages(['column_id' => "ظرفیت ستون «{$column->name}» تکمیل است."]);
        }

        $from = $task->column_id;
        $task->column_id = $column->id;
        $task->position = (float) $data['position'];

        if ($column->is_done && ! $task->isCompleted()) {
            $task->forceFill(['completed_at' => now(), 'completed_by' => $request->user()->id]);
        } elseif (! $column->is_done && $task->isCompleted()) {
            $task->forceFill(['completed_at' => null, 'completed_by' => null]);
        }

        $task->save();

        if ($from !== $column->id) {
            $task->log('moved', ['to' => $column->name]);
        }

        $this->broadcastBoard($task);

        return response()->json(['ok' => true, 'completed_at' => $task->completed_at?->toIso8601String()]);
    }

    public function watch(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $result = $task->watchers()->toggle([$request->user()->id]);

        return response()->json(['watching' => $result['attached'] !== []]);
    }

    public function bookmark(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $bookmark = Bookmark::where('user_id', $request->user()->id)
            ->where('bookmarkable_type', 'task')->where('bookmarkable_id', $task->id)->first();

        if ($bookmark) {
            $bookmark->delete();

            return response()->json(['bookmarked' => false]);
        }

        $bookmark = new Bookmark(['bookmarkable_type' => 'task', 'bookmarkable_id' => $task->id]);
        $bookmark->user_id = $request->user()->id;
        $bookmark->save();

        return response()->json(['bookmarked' => true]);
    }

    public function destroy(Request $request, Workspace $workspace, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $task);

        AuditLog::record('task.deleted', $task, ['title' => $task->title]);
        $task->delete();
        $this->broadcastBoard($task);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'وظیفه حذف شد.');
    }

    // ------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['integer', 'between:0,3'],
            'project_id' => ['nullable', 'string', 'size:26'],
            'column_id' => ['nullable', 'string', 'size:26'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'remind_at' => ['nullable', 'date'],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'assignees' => ['array', 'max:50'],
            'assignees.*' => ['integer'],
            'watchers' => ['array', 'max:50'],
            'watchers.*' => ['integer'],
            'labels' => ['array', 'max:20'],
            'labels.*' => ['string', 'size:26'],
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('q'), fn ($q) => $q->whereIn('tasks.id', $this->matchingIds($q->clone(), ['title', 'description'], (string) $request->string('q'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->string('category')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->string('project')))
            ->when($request->filled('label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->string('label'))))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->integer('priority')))
            ->when($request->query('due') === 'today', fn ($q) => $q->whereDate('due_at', today()))
            ->when($request->query('due') === 'week', fn ($q) => $q->whereBetween('due_at', [now()->startOfDay(), now()->addWeek()]))
            ->when($request->query('due') === 'overdue', fn ($q) => $q->whereNull('completed_at')->where('due_at', '<', now()))
            ->when($request->query('due') === 'none', fn ($q) => $q->whereNull('due_at'));
    }

    private function formOptions(Request $request, Workspace $workspace): array
    {
        $user = $request->user();

        return [
            // Names are encrypted at rest, so they are sorted after decryption.
            'categories' => $workspace->categories()->get(['id', 'name', 'color'])->sortBy('name')->values(),
            'labels' => $workspace->labels()->where('scope', 'task')->get(['id', 'name', 'color'])->sortBy('name')->values(),
            'projects' => Project::query()->inWorkspace($workspace)->visibleTo($user, $workspace)
                ->whereNull('archived_at')->get(['id', 'name', 'color'])->sortBy('name')->values(),
            'canCreate' => $user->can('contribute', $workspace),
        ];
    }

    private function resolveProject(Workspace $workspace, ?string $id): ?Project
    {
        if (! $id) {
            return null;
        }

        $project = $workspace->projects()->whereKey($id)->first();
        abort_if($project === null, 422, 'پروژه معتبر نیست.');

        return $project;
    }

    private function resolveColumn(Project $project, ?string $id): ?BoardColumn
    {
        return $id ? $project->columns()->whereKey($id)->first() : null;
    }

    private function resolveCategory(Workspace $workspace, ?string $id): ?string
    {
        return $id ? $workspace->categories()->whereKey($id)->value('id') : null;
    }

    private function syncAssignees(Task $task, Workspace $workspace, array $ids, User $actor): void
    {
        $result = $task->assignees()->sync($workspace->filterMemberIds($ids));

        if ($result['attached'] !== []) {
            $task->log('assigned', ['users' => User::whereIn('id', $result['attached'])->pluck('name')->all()]);
            $this->notifyAssigned($task, User::whereIn('id', $result['attached'])->get(), $actor);
        }
    }

    private function notifyAssigned(Task $task, $users, User $actor): void
    {
        ActivityNotification::dispatch($users, new ActivityNotification(
            'task.assigned',
            "{$actor->name} وظیفه «{$task->title}» را به شما سپرد",
            $task->description,
            route('tasks.index', [$task->workspace_id, 'task' => $task->id]),
            $task->workspace_id,
            $actor->toCard(),
        ), $actor->id);
    }

    private function broadcastBoard(Task $task): void
    {
        if ($task->project_id) {
            Realtime::send(new BoardChanged($task->project_id));
        }
    }
}
