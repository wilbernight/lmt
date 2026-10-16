<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Personal account settings: profile, avatar, password, 2FA, sessions, activity. */
class SettingsController extends Controller
{
    public function profile(Request $request): Response
    {
        return Inertia::render('Settings/Profile');
    }

    public function security(Request $request): Response
    {
        $user = $request->user();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                'ip' => $s->ip_address,
                'agent' => $this->describeAgent((string) $s->user_agent),
                'current' => hash_equals($s->id, $request->session()->getId()),
                'last_active' => date(DATE_ATOM, $s->last_activity),
            ]);

        return Inertia::render('Settings/Security', [
            'sessions' => $sessions,
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            ],
            'activity' => AuditLog::where('user_id', $user->id)
                ->where('action', 'like', 'auth.%')
                ->orWhere(fn ($q) => $q->where('user_id', $user->id)->where('action', 'like', 'account.%'))
                ->latest('created_at')->limit(20)->get()
                ->map(fn ($l) => [
                    'id' => $l->id, 'action' => $l->action, 'ip' => $l->ip_address,
                    'agent' => $this->describeAgent((string) $l->user_agent),
                    'created_at' => $l->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function avatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        $path = $request->file('avatar')->storeAs('avatars', $user->id.'-'.Str::random(24).'.img', 'local');
        $user->forceFill(['avatar_path' => $path])->save();

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'تصویر پروفایل به‌روزرسانی شد.');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back();
    }

    /** Avatars are visible only to people who share a workspace with their owner. */
    public function showAvatar(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();

        $shares = $viewer->id === $user->id || DB::table('workspace_user as a')
            ->join('workspace_user as b', 'a.workspace_id', '=', 'b.workspace_id')
            ->where('a.user_id', $viewer->id)
            ->where('b.user_id', $user->id)
            ->exists();

        abort_unless($shares && $user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        $mime = Storage::disk('local')->mimeType($user->avatar_path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk('local')->response($user->avatar_path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('password'));

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        AuditLog::record('account.sessions_revoked');

        return back()->with('success', 'از همه دستگاه‌های دیگر خارج شدید.');
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        if ($user->workspaces()->wherePivot('role', 'owner')->whereHas('members', fn ($q) => $q->where('users.id', '!=', $user->id))->exists()) {
            return back()->withErrors(['password' => 'ابتدا مالکیت میزکارهایی را که عضو دیگری دارند منتقل یا آن‌ها را حذف کنید.']);
        }

        DB::transaction(function () use ($user) {
            $user->workspaces()->wherePivot('role', 'owner')->get()->each->delete();
            AuditLog::record('account.deleted', userId: $user->id);
            Auth::guard('web')->logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'مرورگر ناشناس',
        };

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim("{$browser} {$os}");
    }
}
