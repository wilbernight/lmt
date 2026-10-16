<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff attendance: a presence opens at login and closes at logout. If someone
 * just closes the browser, the presence is closed at their last request once
 * they have been idle longer than the session lifetime.
 */
class Presence
{
    public static function clockIn(User $user, ?Request $request = null): Attendance
    {
        $request ??= request();

        return DB::transaction(function () use ($user, $request) {
            Attendance::where('user_id', $user->id)->open()->lockForUpdate()->get()
                ->each(fn (Attendance $a) => $a->close('relogin', $a->last_activity_at));

            $attendance = new Attendance([
                'started_at' => now(),
                'last_activity_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            ]);
            $attendance->user_id = $user->id;
            $attendance->save();

            return $attendance;
        });
    }

    /** Called on activity (at most once a minute per user). */
    public static function touch(User $user, ?Request $request = null): void
    {
        $open = Attendance::where('user_id', $user->id)->open();

        // e.g. the presence was closed for inactivity but the session survived ("remember me").
        // (Not based on update()'s row count: MySQL reports 0 when the value did not change.)
        if (! $open->clone()->exists()) {
            static::clockIn($user, $request);

            return;
        }

        $open->update(['last_activity_at' => now()]);
    }

    public static function clockOut(User $user, string $reason = 'logout'): void
    {
        Attendance::where('user_id', $user->id)->open()->get()->each->close($reason);
        TimeEntry::where('user_id', $user->id)->running()->get()->each->stop();
    }

    /** Close presences (and running task timers) of people who silently left. */
    public static function closeStale(): int
    {
        $cutoff = now()->subMinutes(Attendance::idleMinutes());
        $closed = 0;

        Attendance::open()->where('last_activity_at', '<', $cutoff)->get()->each(function (Attendance $a) use (&$closed) {
            $a->close('timeout', $a->last_activity_at);
            TimeEntry::where('user_id', $a->user_id)->running()->get()->each(fn (TimeEntry $t) => $t->stop($a->last_activity_at));
            $closed++;
        });

        return $closed;
    }
}
