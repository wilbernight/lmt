<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\Minute;
use App\Models\MinuteAction;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ActivityNotification;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/** Meeting minutes (صورت‌جلسه). */
class MinuteController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Minute::class, $workspace]);
        $user = $request->user();

        $query = Minute::query()->inWorkspace($workspace);
        if (! $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)) {
            $query->visibleTo($user);
        }

        $minutes = $query
            ->when($request->filled('q'), fn ($q) => $q->whereIn('minutes.id', $this->matchingIds($q->clone(), ['subject'], (string) $request->string('q'))))
            ->when($request->query('status') === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($request->query('status') === 'final', fn ($q) => $q->where('status', 'final'))
            ->with(['organizer:id,name,avatar_path,last_seen_at', 'participants.user:id,name', 'participants.person:id,name'])
            ->withCount('actions')
            ->orderByRaw('held_at is null')->orderByDesc('held_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Minute $m) => $this->summary($m));

        return Inertia::render('Minutes/Index', [
            'minutes' => $minutes,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(Request $request, Workspace $workspace): Response
    {
        $this->authorize('create', [Minute::class, $workspace]);

        return Inertia::render('Minutes/Form', ['minute' => null]);
    }

    public function edit(Request $request, Workspace $workspace, Minute $minute): Response
    {
        $this->authorize('update', $minute);
        $minute->load($this->withDetail());

        return Inertia::render('Minutes/Form', ['minute' => $this->detail($minute, $request->user())]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Minute::class, $workspace]);
        $data = $request->validate($this->rules());

        $minute = $this->save($workspace, $request->user(), $data, new Minute, true);

        return redirect()->route('minutes.show', [$workspace, $minute])->with('success', "صورت‌جلسه شماره {$minute->number} ثبت شد.");
    }

    public function show(Request $request, Workspace $workspace, Minute $minute): Response
    {
        $this->authorize('view', $minute);
        $user = $request->user();

        $minute->load($this->withDetail())->loadCount('actions');

        return Inertia::render('Minutes/Show', [
            'minute' => $this->detail($minute, $user),
            'can' => [
                'update' => $user->can('update', $minute),
                'delete' => $user->can('delete', $minute),
                'approve' => $user->can('approve', $minute),
                'act' => $user->can('manageActions', $minute),
                'transfer' => $user->can('manageActions', $minute),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, Minute $minute): RedirectResponse
    {
        $this->authorize('update', $minute);
        $data = $request->validate($this->rules());

        $this->save($workspace, $request->user(), $data, $minute, false);

        return redirect()->route('minutes.show', [$workspace, $minute])->with('success', 'صورت‌جلسه به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Workspace $workspace, Minute $minute): RedirectResponse
    {
        $this->authorize('delete', $minute);

        AuditLog::record('minute.deleted', $minute, ['subject' => $minute->subject]);
        $minute->delete();

        return redirect()->route('minutes.index', $workspace)->with('success', 'صورت‌جلسه حذف شد.');
    }

    /** From draft to the final version, announced to the attending members. */
    public function finalize(Request $request, Workspace $workspace, Minute $minute): RedirectResponse
    {
        $this->authorize('update', $minute);

        $minute->forceFill(['status' => 'final'])->save();

        ActivityNotification::dispatch(User::whereIn('id', $minute->userParticipantIds())->get(), new ActivityNotification(
            'minute.finalized',
            "صورت‌جلسه «{$minute->subject}» نهایی شد",
            'برای تأیید و امضای الکترونیکی، صفحه صورت‌جلسه را باز کنید.',
            route('minutes.show', [$workspace, $minute]),
            $workspace->id,
            $request->user()->toCard(),
        ));

        return back()->with('success', 'صورت‌جلسه نهایی شد و برای تأیید حاضرین اعلام شد.');
    }

    /** Electronic sign-off by an attending member (تأیید صورت‌جلسه). */
    public function approve(Request $request, Workspace $workspace, Minute $minute): RedirectResponse
    {
        $this->authorize('approve', $minute);

        $minute->approvals()->toggle([$request->user()->id]);

        return back();
    }

    /** Turn an action item into a real task, handed to the same responsible person. */
    public function actionToTask(Request $request, Workspace $workspace, Minute $minute, MinuteAction $minuteAction): RedirectResponse
    {
        $this->authorize('manageActions', $minute);
        abort_if($minuteAction->minute_id !== $minute->id, 404);
        abort_if($minuteAction->task_id !== null, 422, 'این اقدام قبلاً به وظیفه تبدیل شده است.');

        $task = new Task(['title' => $minuteAction->title, 'due_at' => $minuteAction->due_at]);
        $task->workspace_id = $workspace->id;
        $task->creator_id = $request->user()->id;
        $task->save();

        if ($minuteAction->user_id) {
            $task->assignees()->sync([$minuteAction->user_id]);
        }
        if ($minuteAction->person_id) {
            $task->people()->sync([$minuteAction->person_id]);
        }
        $task->log('created', user: $request->user());

        $minuteAction->forceFill(['task_id' => $task->id])->save();

        if ($minuteAction->user_id && $minuteAction->user_id !== $request->user()->id) {
            ActivityNotification::dispatch(User::whereKey($minuteAction->user_id)->get(), new ActivityNotification(
                'task.assigned',
                "{$request->user()->name} مصوبه «{$task->title}» را به شما سپرد",
                null,
                route('tasks.index', [$workspace, 'task' => $task->id]),
                $workspace->id,
                $request->user()->toCard(),
            ), $request->user()->id);
        }

        return back()->with('success', 'از این اقدام وظیفه ساخته شد.');
    }

    /** Printable A4 minute on the company letterhead. */
    public function print(Request $request, Workspace $workspace, Minute $minute): View
    {
        $this->authorize('view', $minute);

        $minute->load($this->withDetail());

        $memberIds = $minute->participants->pluck('user_id')->filter()->push($minute->organizer_id)->unique();
        $titles = $workspace->members()->whereIn('users.id', $memberIds)->get()->pluck('pivot.job_title', 'id');
        $held = $minute->held_at?->copy()->setTimezone('Asia/Tehran');
        $fa = fn ($value) => CalendarUtils::convertNumbers((string) $value);
        $jalali = fn ($date) => Jalalian::fromDateTime($date->copy()->setTimezone('Asia/Tehran'));

        // Tracking code derived from the app key, so it cannot be guessed from the number.
        $code = strtoupper(substr(hash_hmac('sha256', $minute->id.'|'.$minute->number, config('app.key')), 0, 8));

        $qr = (new Writer(new ImageRenderer(new RendererStyle(132, 1), new SvgImageBackEnd)))
            ->writeString(route('minutes.show', [$workspace, $minute]));

        AuditLog::record('minute.printed', $minute, ['number' => $minute->number]);

        return view('minutes.print', [
            'minute' => $minute,
            'company' => collect(Setting::LETTERHEAD_KEYS)->mapWithKeys(fn ($k) => [str_replace('company_', '', $k) => trim((string) Setting::get($k))]),
            'number' => $fa($minute->number),
            'date' => $held ? $fa($jalali($held)->format('%d %B %Y')) : null,
            'dateNumeric' => $held ? $fa($jalali($held)->format('Y/m/d')) : null,
            'time' => $held ? $fa($held->format('H:i')) : null,
            'organizer' => $minute->organizer?->name,
            'organizerTitle' => $titles[$minute->organizer_id] ?? null,
            'titles' => $titles,
            'present' => $minute->participants->where('attended', true)->values(),
            'absent' => $minute->participants->where('attended', false)->values(),
            'actions' => $minute->actions->map(fn ($a) => [
                'title' => $a->title,
                'who' => $a->user?->name ?? $a->person?->name,
                'due' => $a->due_at ? $fa($jalali($a->due_at)->format('Y/m/d')) : null,
                'task' => $a->task_id !== null,
            ]),
            'approvals' => $minute->approvals->map(fn ($u) => [
                'name' => $u->name,
                'title' => $titles[$u->id] ?? null,
                'date' => $fa($jalali(\Illuminate\Support\Carbon::parse($u->pivot->created_at))->format('Y/m/d - H:i')),
            ]),
            'code' => $fa(substr($code, 0, 4)).'-'.$fa(substr($code, 4)),
            'qr' => preg_replace('/^<\?xml[^>]*>\s*/', '', $qr),
            'printedBy' => $request->user()->name,
            'printedAt' => $fa($jalali(now())->format('Y/m/d - H:i')),
            'fa' => $fa,
        ]);
    }

    // ------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'held_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:200'],
            'organizer_id' => ['nullable', 'integer'],
            'attachments' => ['array', 'max:20'],
            'attachments.*' => ['string', 'size:26'],
            // Invitees: members by id, anyone else by person id or free-typed name.
            'participants' => ['array', 'max:100'],
            'participants.*.user_id' => ['nullable', 'integer'],
            'participants.*.person_id' => ['nullable', 'string', 'size:26'],
            'participants.*.name' => ['nullable', 'string', 'max:120'],
            'participants.*.attended' => ['boolean'],
            // Action items (اقدامات): what, who (member / person / name), by when.
            'actions' => ['array', 'max:100'],
            'actions.*.title' => ['required', 'string', 'max:200'],
            'actions.*.user_id' => ['nullable', 'integer'],
            'actions.*.person_id' => ['nullable', 'string', 'size:26'],
            'actions.*.name' => ['nullable', 'string', 'max:120'],
            'actions.*.due_at' => ['nullable', 'date'],
        ];
    }

    private function withDetail(): array
    {
        return [
            'organizer:id,name,avatar_path,last_seen_at',
            'creator:id,name',
            'participants.user:id,name,avatar_path,last_seen_at',
            'participants.person:id,name',
            'actions.user:id,name,avatar_path,last_seen_at',
            'actions.person:id,name',
            'actions.task:id,title',
            'approvals:id,name',
            'attachments',
        ];
    }

    /** Shared by store() and update(): writes the minute, invitees, and actions. */
    private function save(Workspace $workspace, User $user, array $data, Minute $minute, bool $creating): Minute
    {
        $members = $workspace->memberIds();

        $minute = DB::transaction(function () use ($workspace, $user, $data, $minute, $creating, $members) {
            $organizer = (int) ($data['organizer_id'] ?? 0) ?: $user->id;
            $organizer = in_array($organizer, $members, true) ? $organizer : $user->id;

            $minute->fill(Arr::only($data, ['subject', 'body', 'held_at', 'location']));
            $minute->organizer_id = $organizer;

            if ($creating) {
                $minute->workspace_id = $workspace->id;
                $minute->created_by = $user->id;
                $minute->number = (int) Minute::where('workspace_id', $workspace->id)->lockForUpdate()->max('number') + 1;
            }

            $minute->save();

            // Free-typed names become People once, then every row can reference them.
            $names = [
                ...collect($data['participants'] ?? [])->filter(fn ($p) => empty($p['user_id']) && empty($p['person_id']))->pluck('name')->all(),
                ...collect($data['actions'] ?? [])->filter(fn ($a) => empty($a['user_id']) && empty($a['person_id']))->pluck('name')->all(),
            ];
            $peopleMap = Person::resolveMap($workspace, $names, $user->id);
            $peopleIds = $workspace->people()->pluck('id')->all();

            $minute->participants()->delete();

            foreach ($data['participants'] ?? [] as $p) {
                $row = ['attended' => (bool) ($p['attended'] ?? true)];

                if (! empty($p['user_id']) && in_array((int) $p['user_id'], $members, true)) {
                    $row['user_id'] = (int) $p['user_id'];
                } elseif (! empty($p['person_id']) && in_array($p['person_id'], $peopleIds, true)) {
                    $row['person_id'] = $p['person_id'];
                } else {
                    $norm = Person::normalize((string) ($p['name'] ?? ''));
                    if ($norm === '' || ! isset($peopleMap[$norm])) {
                        continue;
                    }
                    $row['person_id'] = $peopleMap[$norm];
                }

                $minute->participants()->create($row);
            }

            // Editing replaces the action list; a converted-to-task action keeps its link when the title survives.
            $oldTaskByTitle = $minute->actions()->pluck('task_id', 'title')->all();
            $minute->actions()->delete();

            foreach ($data['actions'] ?? [] as $i => $a) {
                $row = ['title' => trim($a['title']), 'position' => $i, 'due_at' => $a['due_at'] ?? null];

                if (! empty($a['user_id']) && in_array((int) $a['user_id'], $members, true)) {
                    $row['user_id'] = (int) $a['user_id'];
                } elseif (! empty($a['person_id']) && in_array($a['person_id'], $peopleIds, true)) {
                    $row['person_id'] = $a['person_id'];
                } else {
                    $norm = Person::normalize((string) ($a['name'] ?? ''));
                    if ($norm !== '' && isset($peopleMap[$norm])) {
                        $row['person_id'] = $peopleMap[$norm];
                    }
                }

                if (($oldTaskByTitle[$row['title']] ?? null) !== null) {
                    $row['task_id'] = $oldTaskByTitle[$row['title']];
                }

                $minute->actions()->create($row);
            }

            $minute->claimAttachments($data['attachments'] ?? [], $user->id, $workspace->id);

            return $minute;
        });

        // Notifications reach the attending members (external people have no account).
        if ($creating) {
            $userIds = array_values(array_filter(array_map(
                fn ($p) => ! empty($p['user_id']) && in_array((int) $p['user_id'], $members, true) ? (int) $p['user_id'] : null,
                $data['participants'] ?? [],
            )));

            ActivityNotification::dispatch(User::whereIn('id', $userIds)->get(), new ActivityNotification(
                'minute.created',
                "صورت‌جلسه «{$minute->subject}» ثبت شد",
                'شما در فهرست شرکت‌کنندگان این جلسه هستید.',
                route('minutes.show', [$workspace, $minute]),
                $workspace->id,
                $user->toCard(),
            ), $user->id);
        }

        return $minute;
    }

    private function summary(Minute $m): array
    {
        return [
            'id' => $m->id,
            'number' => $m->number,
            'subject' => $m->subject,
            'held_at' => $m->held_at?->toIso8601String(),
            'location' => $m->location,
            'status' => $m->status,
            'organizer' => $m->organizer?->toCard(),
            'participants' => $m->participants->map(fn ($p) => [
                'key' => $p->user_id ? 'u'.$p->user_id : 'p'.$p->person_id,
                'user_id' => $p->user_id,
                'person_id' => $p->person_id,
                'name' => $p->user?->name ?? $p->person?->name,
                'external' => $p->user_id === null,
                'attended' => $p->attended,
            ])->values(),
            'actions_count' => $m->actions_count,
        ];
    }

    private function detail(Minute $m, User $user): array
    {
        return [
            ...$this->summary($m),
            'body' => $m->body,
            'creator' => $m->creator?->name,
            'attachments' => $m->attachments->map->toPayload(),
            'actions' => $m->actions->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'user' => $a->user?->toCard(),
                'person' => $a->person?->toCard(),
                'due_at' => $a->due_at?->toIso8601String(),
                'task_id' => $a->task_id,
                'task_title' => $a->task?->title,
            ])->values(),
            'approvals' => $m->approvals->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'my_approval' => $m->approvals->contains('id', $user->id),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}
