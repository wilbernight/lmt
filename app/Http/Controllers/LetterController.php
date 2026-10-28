<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Letter;
use App\Models\LetterReferral;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ActivityNotification;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/** Internal correspondence (کارتابل نامه‌ها). */
class LetterController extends Controller
{
    public const BOXES = ['inbox', 'sent', 'referrals', 'archived'];

    public function index(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();
        $box = in_array($request->query('box'), self::BOXES, true) ? $request->query('box') : 'inbox';

        $query = Letter::query()->inWorkspace($workspace);

        match ($box) {
            'inbox' => $query->whereHas('recipients', fn (Builder $q) => $q->whereKey($user->id)
                ->whereNull('letter_recipients.archived_at')),
            'sent' => $query->where('sender_id', $user->id)->whereNull('sender_archived_at'),
            'referrals' => $query->whereHas('referrals', fn (Builder $q) => $q->where('to_user_id', $user->id)),
            'archived' => $query->where(fn (Builder $q) => $q
                ->where(fn ($s) => $s->where('sender_id', $user->id)->whereNotNull('sender_archived_at'))
                ->orWhereHas('recipients', fn ($r) => $r->whereKey($user->id)->whereNotNull('letter_recipients.archived_at'))),
        };

        $letters = $query
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('letters.id', $this->matchingIds($q->clone(), ['subject', 'body'], (string) $request->string('q')))
                ->orWhere('number', (int) $request->query('q'))))
            ->when($request->filled('label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->string('label'))))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->with(['sender:id,name,avatar_path,last_seen_at', 'labels:id,name,color', 'recipients:id,name', 'people:id,name'])
            ->withCount('attachments')
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Letter $l) => [
                'id' => $l->id,
                'number' => $l->number,
                'subject' => $l->subject,
                'excerpt' => mb_substr(strip_tags($l->body), 0, 140),
                'priority' => $l->priority,
                'confidential' => $l->confidential,
                'sender' => $l->sender->toCard(),
                'recipients' => $l->recipients->pluck('name')->merge($l->people->pluck('name'))->take(3),
                'labels' => $l->labels->map->only(['id', 'name', 'color']),
                'attachments_count' => $l->attachments_count,
                'unread' => $l->recipients->firstWhere('id', $user->id)?->pivot->read_at === null
                    && $l->sender_id !== $user->id,
                'created_at' => $l->created_at->toIso8601String(),
            ]);

        return Inertia::render('Letters/Index', [
            'box' => $box,
            'letters' => $letters,
            'filters' => $request->only(['q', 'label', 'priority']),
            'labels' => $workspace->labels()->where('scope', 'letter')->get(['id', 'name', 'color']),
            'priorities' => Letter::PRIORITIES,
        ]);
    }

    public function create(Request $request, Workspace $workspace): Response
    {
        return Inertia::render('Letters/Compose', [
            'labels' => $workspace->labels()->where('scope', 'letter')->get(['id', 'name', 'color']),
            'priorities' => Letter::PRIORITIES,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $letter = $this->send($workspace, $request->user(), $data);

        return redirect()->route('letters.show', [$workspace, $letter])->with('success', "نامه شماره {$letter->number} ارسال شد.");
    }

    public function show(Request $request, Workspace $workspace, Letter $letter): Response
    {
        $this->authorize('view', $letter);
        $user = $request->user();

        $letter->recipients()->updateExistingPivot($user->id, ['read_at' => DB::raw('COALESCE(read_at, CURRENT_TIMESTAMP)')]);

        $root = $letter->reply_to_id ? ($letter->replyTo ?? $letter) : $letter;

        $thread = Letter::query()
            ->where(fn ($q) => $q->whereKey($root->id)->orWhere('reply_to_id', $root->id))
            ->visibleTo($user)
            ->with(['sender:id,name,avatar_path,last_seen_at', 'recipients:id,name,avatar_path,last_seen_at', 'people', 'attachments', 'labels:id,name,color'])
            ->oldest()
            ->get()
            ->map(fn (Letter $l) => [
                'id' => $l->id,
                'number' => $l->number,
                'subject' => $l->subject,
                'body' => $l->body,
                'priority' => $l->priority,
                'confidential' => $l->confidential,
                'sender' => $l->sender->toCard(),
                'recipients' => $l->recipients->map(fn ($r) => [
                    ...$r->toCard(),
                    'type' => $r->pivot->type,
                    // Read receipts are visible to the sender only.
                    'read_at' => $l->sender_id === $user->id ? $r->pivot->read_at : null,
                ]),
                // Recipients by name, without an account.
                'people' => $l->people->map(fn ($p) => [...$p->toCard(), 'type' => $p->pivot->type]),
                'labels' => $l->labels->map->only(['id', 'name', 'color']),
                'attachments' => $l->attachments->map->toPayload(),
                'created_at' => $l->created_at->toIso8601String(),
            ]);

        $me = $letter->recipients()->whereKey($user->id)->first();

        return Inertia::render('Letters/Show', [
            'letter' => ['id' => $letter->id, 'subject' => $root->subject, 'number' => $root->number],
            'thread' => $thread,
            'referrals' => $letter->referrals()->with(['from:id,name', 'to:id,name'])->get()->map(fn ($r) => [
                'id' => $r->id, 'from' => $r->from->name, 'to' => $r->to->name, 'note' => $r->note,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
            'archived' => $letter->sender_id === $user->id ? $letter->sender_archived_at !== null : $me?->pivot->archived_at !== null,
            'can' => ['refer' => $user->can('refer', $letter), 'reply' => $user->can('reply', $letter), 'transfer' => $letter->sender_id === $user->id || $user->can('update', $workspace)],
        ]);
    }

    /** Printable A4 letter on the company letterhead. */
    public function print(Request $request, Workspace $workspace, Letter $letter): View
    {
        $this->authorize('view', $letter);

        $letter->load(['sender', 'recipients', 'people', 'attachments', 'referrals.from', 'referrals.to']);

        $people = $letter->recipients->pluck('id')->push($letter->sender_id)
            ->merge($letter->referrals->pluck('from_user_id'))
            ->merge($letter->referrals->pluck('to_user_id'));

        $titles = $workspace->members()->whereIn('users.id', $people->unique())->get()->pluck('pivot.job_title', 'id');
        $sent = $letter->created_at->setTimezone('Asia/Tehran');
        $fa = fn ($value) => CalendarUtils::convertNumbers((string) $value);
        $jalali = fn ($date) => Jalalian::fromDateTime($date->copy()->setTimezone('Asia/Tehran'));

        // Tracking code derived from the app key, so it cannot be guessed from the letter number.
        $code = strtoupper(substr(hash_hmac('sha256', $letter->id.'|'.$letter->number, config('app.key')), 0, 8));

        $qr = (new Writer(new ImageRenderer(new RendererStyle(132, 1), new SvgImageBackEnd)))
            ->writeString(route('letters.show', [$workspace, $letter]));

        AuditLog::record('letter.printed', $letter, ['number' => $letter->number]);

        return view('letters.print', [
            'letter' => $letter,
            'company' => collect(Setting::LETTERHEAD_KEYS)->mapWithKeys(fn ($k) => [str_replace('company_', '', $k) => trim((string) Setting::get($k))]),
            'number' => $fa($letter->number),
            'date' => $fa($jalali($letter->created_at)->format('%d %B %Y')),
            'dateNumeric' => $fa($jalali($letter->created_at)->format('Y/m/d')),
            'weekday' => $jalali($letter->created_at)->format('%A'),
            'time' => $fa($sent->format('H:i')),
            'to' => collect($letter->recipients->where('pivot.type', 'to')->values()
                ->map(fn ($r) => ['name' => $r->name, 'title' => $titles[$r->id] ?? null])->all())
                ->merge($letter->people->where('pivot.type', 'to')->values()
                    ->map(fn ($p) => ['name' => $p->name, 'title' => null])->all())
                ->values(),
            'cc' => collect($letter->recipients->where('pivot.type', 'cc')->values()
                ->map(fn ($r) => ['name' => $r->name, 'title' => $titles[$r->id] ?? null])->all())
                ->merge($letter->people->where('pivot.type', 'cc')->values()
                    ->map(fn ($p) => ['name' => $p->name, 'title' => null])->all())
                ->values(),
            'titles' => $titles,
            'referrals' => $letter->referrals->sortBy('created_at')->values()->map(fn ($r) => [
                'from' => $r->from->name, 'to' => $r->to->name, 'note' => $r->note,
                'date' => $fa($jalali($r->created_at)->format('Y/m/d - H:i')),
            ]),
            'priority' => ['urgent' => 'فوری', 'instant' => 'آنی'][$letter->priority] ?? null,
            'code' => $fa(substr($code, 0, 4)).'-'.$fa(substr($code, 4)),
            'qr' => preg_replace('/^<\?xml[^>]*>\s*/', '', $qr),
            'printedBy' => $request->user()->name,
            'printedAt' => $fa($jalali(now())->format('Y/m/d - H:i')),
            'fa' => $fa,
        ]);
    }

    public function reply(Request $request, Workspace $workspace, Letter $letter): RedirectResponse
    {
        $this->authorize('reply', $letter);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'all' => ['boolean'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['string', 'size:26'],
        ]);

        $user = $request->user();
        $root = $letter->reply_to_id ? $letter->replyTo : $letter;

        $to = collect([$letter->sender_id]);

        if ($request->boolean('all')) {
            $to = $to->merge($letter->recipients()->pluck('users.id'));
        }

        $to = $to->reject(fn ($id) => $id === $user->id)->unique()->values();

        if ($to->isEmpty()) {
            $to = $letter->recipients()->where('users.id', '!=', $user->id)->pluck('users.id');
        }

        $reply = $this->send($workspace, $user, [
            'subject' => 'پاسخ: '.preg_replace('/^(پاسخ: )+/u', '', $root->subject),
            'body' => $data['body'],
            'priority' => $letter->priority,
            'confidential' => $letter->confidential,
            'to' => $to->all(),
            'cc' => [],
            'attachments' => $data['attachments'] ?? [],
        ], $root->id);

        return redirect()->route('letters.show', [$workspace, $reply])->with('success', 'پاسخ ارسال شد.');
    }

    /** پاراف: forward to a colleague with an instruction note. */
    public function refer(Request $request, Workspace $workspace, Letter $letter): RedirectResponse
    {
        $this->authorize('refer', $letter);
        $user = $request->user();

        $data = $request->validate([
            'to' => ['required', 'array', 'min:1', 'max:20'],
            'to.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $targets = collect($workspace->filterMemberIds($data['to']))->reject(fn ($id) => $id === $user->id);
        abort_if($targets->isEmpty(), 422);

        DB::transaction(function () use ($letter, $targets, $user, $data) {
            foreach ($targets as $id) {
                $referral = new LetterReferral(['note' => $data['note'] ?? null]);
                $referral->letter_id = $letter->id;
                $referral->from_user_id = $user->id;
                $referral->to_user_id = $id;
                $referral->save();

                $letter->recipients()->syncWithoutDetaching([$id => ['type' => 'referral']]);
                $letter->recipients()->updateExistingPivot($id, ['archived_at' => null, 'read_at' => null]);
            }
        });

        ActivityNotification::dispatch(User::whereIn('id', $targets)->get(), new ActivityNotification(
            'letter.referred',
            "{$user->name} نامه «{$letter->subject}» را به شما ارجاع داد",
            $data['note'] ?? null,
            route('letters.show', [$workspace, $letter]),
            $workspace->id,
            $user->toCard(),
        ));

        AuditLog::record('letter.referred', $letter, ['to' => $targets->values()->all()]);

        return back()->with('success', 'نامه ارجاع داده شد.');
    }

    public function archive(Request $request, Workspace $workspace, Letter $letter): RedirectResponse
    {
        $this->authorize('view', $letter);
        $user = $request->user();

        if ($letter->sender_id === $user->id && ! $letter->recipients()->whereKey($user->id)->exists()) {
            $letter->forceFill(['sender_archived_at' => $letter->sender_archived_at ? null : now()])->save();
        } else {
            $current = $letter->recipients()->whereKey($user->id)->value('letter_recipients.archived_at');
            $letter->recipients()->updateExistingPivot($user->id, ['archived_at' => $current ? null : now()]);
        }

        return back()->with('success', 'وضعیت بایگانی تغییر کرد.');
    }

    public function labels(Request $request, Workspace $workspace, Letter $letter): RedirectResponse
    {
        $this->authorize('view', $letter);

        $data = $request->validate(['labels' => ['array', 'max:10'], 'labels.*' => ['string', 'size:26']]);
        $letter->syncLabels($data['labels'] ?? [], 'letter');

        return back();
    }

    // ------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(array_keys(Letter::PRIORITIES))],
            'confidential' => ['boolean'],
            // Recipients: members by id, anyone else by free-typed name.
            'to' => ['array', 'max:100'],
            'to.*' => ['integer'],
            'to_names' => ['array', 'max:100'],
            'to_names.*' => ['string', 'max:120'],
            'cc' => ['array', 'max:100'],
            'cc.*' => ['integer'],
            'cc_names' => ['array', 'max:100'],
            'cc_names.*' => ['string', 'max:120'],
            'labels' => ['array', 'max:10'],
            'labels.*' => ['string', 'size:26'],
            'attachments' => ['array', 'max:20'],
            'attachments.*' => ['string', 'size:26'],
        ];
    }

    private function send(Workspace $workspace, User $sender, array $data, ?string $replyTo = null): Letter
    {
        $to = collect($workspace->filterMemberIds($data['to'] ?? []))->reject(fn ($id) => $id === $sender->id);
        $cc = collect($workspace->filterMemberIds($data['cc'] ?? []))->diff($to)->reject(fn ($id) => $id === $sender->id);

        // Recipients without an account: free-typed names become People of this workspace.
        $toPeople = Person::resolveMany($workspace, $data['to_names'] ?? [], $sender->id);
        $ccPeople = collect(Person::resolveMany($workspace, $data['cc_names'] ?? [], $sender->id))->diff($toPeople)->values();

        abort_if($to->isEmpty() && $toPeople === [], 422, 'گیرنده معتبر انتخاب نشده است.');

        $letter = DB::transaction(function () use ($workspace, $sender, $data, $replyTo, $to, $cc, $toPeople, $ccPeople) {
            $number = (int) Letter::where('workspace_id', $workspace->id)->lockForUpdate()->max('number') + 1;

            $letter = new Letter([
                'subject' => trim($data['subject']),
                'body' => $data['body'],
                'priority' => $data['priority'],
                'confidential' => (bool) ($data['confidential'] ?? false),
            ]);
            $letter->reply_to_id = $replyTo;
            $letter->workspace_id = $workspace->id;
            $letter->sender_id = $sender->id;
            $letter->number = $number;
            $letter->save();

            $letter->recipients()->attach(
                $to->mapWithKeys(fn ($id) => [$id => ['type' => 'to']])
                    ->union($cc->mapWithKeys(fn ($id) => [$id => ['type' => 'cc']]))
                    ->all()
            );

            $letter->people()->attach(
                collect($toPeople)->mapWithKeys(fn ($id) => [$id => ['type' => 'to']])
                    ->union($ccPeople->mapWithKeys(fn ($id) => [$id => ['type' => 'cc']]))
                    ->all()
            );

            $letter->syncLabels($data['labels'] ?? [], 'letter');
            $letter->claimAttachments($data['attachments'] ?? [], $sender->id, $workspace->id);

            return $letter;
        });

        ActivityNotification::dispatch(User::whereIn('id', $to->merge($cc))->get(), new ActivityNotification(
            'letter.received',
            "نامه جدید از {$sender->name}: {$letter->subject}",
            $letter->confidential ? null : $letter->body,
            route('letters.show', [$workspace, $letter]),
            $workspace->id,
            $sender->toCard(),
        ));

        return $letter;
    }
}
