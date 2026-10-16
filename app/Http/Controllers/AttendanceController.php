<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Presence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff attendance (ساعت حضور). Workspace admins see every member; others only themselves.
 * Days are grouped in the local (Tehran) calendar.
 */
class AttendanceController extends Controller
{
    private const TZ = 'Asia/Tehran';

    public function index(Request $request, Workspace $workspace): Response
    {
        [$from, $to, $isAdmin, $memberIds, $selected] = $this->scope($request, $workspace);

        Presence::closeStale();

        $sessions = $this->sessions($memberIds, $from, $to);
        $members = $workspace->members()->whereIn('users.id', $memberIds)->orderBy('name')->get();

        $logged = TimeEntry::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('user_id', $memberIds)
            ->whereBetween('started_at', [$from->utc(), $to->utc()])
            ->selectRaw('user_id, SUM('.TimeEntry::secondsSql().') as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $days = collect();
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days->push($d->toDateString());
        }

        $rows = $members->map(function (User $m) use ($sessions, $logged, $days) {
            $mine = $sessions->where('user_id', $m->id);
            $perDay = $days->mapWithKeys(fn ($day) => [$day => (int) $mine->where('day', $day)->sum('seconds')]);
            $open = $mine->firstWhere('open', true);

            return [
                'user' => $m->toCard(),
                'job_title' => $m->pivot->job_title,
                'total' => (int) $mine->sum('seconds'),
                'days_present' => $perDay->filter()->count(),
                'per_day' => $perDay,
                'logged' => (int) ($logged[$m->id] ?? 0),
                'in_now' => $open !== null,
                'in_since' => $open['started_at'] ?? null,
            ];
        })->values();

        return Inertia::render('Attendance/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'days' => $days,
            'rows' => $rows,
            'sessions' => $sessions->when($selected, fn ($c) => $c->where('user_id', $selected))
                ->sortByDesc('started_at')->take(300)->values()
                ->map(fn ($s) => $isAdmin ? $s : array_diff_key($s, ['ip' => 1])),
            'isAdmin' => $isAdmin,
            'selected' => $selected,
            'me' => $this->today($request->user()),
            'idleMinutes' => Attendance::idleMinutes(),
        ]);
    }

    /** CSV for payroll, safe against spreadsheet formula injection. */
    public function export(Request $request, Workspace $workspace): StreamedResponse
    {
        [$from, $to, , $memberIds] = $this->scope($request, $workspace);

        $sessions = $this->sessions($memberIds, $from, $to)->sortBy('started_at');
        $names = User::whereIn('id', $memberIds)->pluck('name', 'id');

        AuditLog::record('attendance.exported', properties: ['from' => $from->toDateString(), 'to' => $to->toDateString()], workspaceId: $workspace->id);

        return response()->streamDownload(function () use ($sessions, $names) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['نام', 'تاریخ', 'ورود', 'خروج', 'مدت (دقیقه)', 'نحوه خروج']);
            $reasons = ['logout' => 'خروج', 'timeout' => 'عدم فعالیت', 'relogin' => 'ورود مجدد'];

            foreach ($sessions as $s) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v, [
                    $names[$s['user_id']] ?? '',
                    $s['day'],
                    CarbonImmutable::parse($s['started_at'])->setTimezone(self::TZ)->format('H:i'),
                    $s['ended_at'] ? CarbonImmutable::parse($s['ended_at'])->setTimezone(self::TZ)->format('H:i') : 'حاضر',
                    (int) round($s['seconds'] / 60),
                    $reasons[$s['end_reason']] ?? '',
                ]));
            }

            fclose($out);
        }, 'attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    private function scope(Request $request, Workspace $workspace): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer'],
        ]);

        $now = CarbonImmutable::now(self::TZ);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], self::TZ)->startOfDay() : $now->startOfWeek(CarbonInterface::SATURDAY);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], self::TZ)->endOfDay() : $now->endOfDay();
        abort_if($from->diffInDays($to) > 62, 422, 'بازه حداکثر ۶۲ روز است.');

        $user = $request->user();
        $isAdmin = (bool) $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin);
        $memberIds = $isAdmin ? $workspace->memberIds() : [$user->id];

        $selected = isset($data['user']) && in_array((int) $data['user'], $memberIds, true) ? (int) $data['user'] : null;

        return [$from, $to, $isAdmin, $memberIds, $selected];
    }

    private function sessions(array $userIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('started_at', [$from->utc(), $to->utc()])
            ->orderBy('started_at')
            ->limit(5000)
            ->get()
            ->map(fn (Attendance $a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'day' => $a->started_at->setTimezone(self::TZ)->toDateString(),
                'started_at' => $a->started_at->toIso8601String(),
                'ended_at' => $a->ended_at?->toIso8601String(),
                'seconds' => $a->seconds(),
                'open' => $a->ended_at === null,
                'end_reason' => $a->end_reason,
                'ip' => $a->ip_address,
            ]);
    }

    /** The viewer's own presence today, for the header widget. */
    public static function today(User $user): array
    {
        $start = CarbonImmutable::now(self::TZ)->startOfDay()->utc();

        $today = Attendance::where('user_id', $user->id)->where('started_at', '>=', $start)->get();
        $open = Attendance::where('user_id', $user->id)->open()->latest('started_at')->first();

        return [
            'in_since' => $open?->started_at->toIso8601String(),
            'first_in' => $today->min('started_at')?->toIso8601String(),
            'seconds' => (int) $today->sum(fn (Attendance $a) => $a->seconds()),
            'as_of' => now()->toIso8601String(),
        ];
    }
}
