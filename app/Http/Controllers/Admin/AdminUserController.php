<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Site administration of user accounts: limits, access and suspension. */
class AdminUserController extends Controller
{
    private const MB = 1024 * 1024;

    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $users = User::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $this->like($request->string('q')))
                ->orWhere('email', 'like', $this->like($request->string('q')))))
            ->when($status === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($status === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when($status === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->withCount(['ownedWorkspaces', 'workspaces'])
            ->withSum('ownedWorkspaces as storage_used', 'storage_used')
            ->withSum('ownedWorkspaces as storage_allocated', 'storage_limit')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => $this->payload($u));

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $request->only(['q', 'status']),
            'defaults' => [
                'storage_mb' => (int) (Setting::get('default_storage_limit') / self::MB),
                'max_workspaces' => (int) Setting::get('default_max_workspaces'),
            ],
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount(['ownedWorkspaces', 'workspaces'])
            ->loadSum('ownedWorkspaces as storage_used', 'storage_used')
            ->loadSum('ownedWorkspaces as storage_allocated', 'storage_limit');

        return Inertia::render('Admin/UserShow', [
            'user' => $this->payload($user),
            'workspaces' => $user->workspaces()->withCount('members')->get()->map(fn (Workspace $w) => [
                'id' => $w->id, 'name' => $w->name, 'color' => $w->color, 'role' => $w->pivot->role, 'job_title' => $w->pivot->job_title,
                'members_count' => $w->members_count, 'storage_used' => $w->storage_used,
                'storage_limit' => $w->storage_limit, 'locked' => $w->suspended_at !== null,
            ]),
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->count(),
            'activity' => AuditLog::where('user_id', $user->id)->latest('created_at')->limit(25)->get()
                ->map(fn (AuditLog $l) => ['id' => $l->id, 'action' => $l->action, 'ip' => $l->ip_address, 'created_at' => $l->created_at->toIso8601String()]),
            'defaults' => [
                'storage_mb' => (int) (Setting::get('default_storage_limit') / self::MB),
                'max_workspaces' => (int) Setting::get('default_max_workspaces'),
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'is_admin' => ['required', 'boolean'],
            'can_create_workspaces' => ['required', 'boolean'],
            'max_workspaces' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:10', 'max:10485760'],
            'apply_storage_to_existing' => ['boolean'],
        ]);

        // Admins cannot lock themselves out, and the site always keeps one administrator.
        if ($user->is($actor) && ! $data['is_admin']) {
            return back()->withErrors(['is_admin' => 'نمی‌توانید دسترسی مدیریت خودتان را بردارید.']);
        }

        if ($user->is_admin && ! $data['is_admin'] && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['is_admin' => 'سایت باید حداقل یک مدیر داشته باشد.']);
        }

        $quota = isset($data['storage_quota_mb']) ? $data['storage_quota_mb'] * self::MB : null;

        DB::transaction(function () use ($user, $data, $quota) {
            $user->forceFill([
                'is_admin' => $data['is_admin'],
                'can_create_workspaces' => $data['can_create_workspaces'],
                'max_workspaces' => $data['max_workspaces'] ?? null,
                'storage_quota' => $quota,
            ])->save();

            if (! empty($data['apply_storage_to_existing'])) {
                $user->ownedWorkspaces()->update(['storage_limit' => $quota ?? Setting::get('default_storage_limit')]);
            }
        });

        AuditLog::record('admin.user_updated', $user, ['target' => $user->email] + $data);

        return back()->with('success', 'دسترسی‌ها و سهمیه کاربر ذخیره شد.');
    }

    /** Job title shown next to the person's name (letters, member lists), per workspace. */
    public function jobTitle(Request $request, User $user, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate(['job_title' => ['nullable', 'string', 'max:80']]);

        abort_unless($user->belongsToWorkspace($workspace), 404);

        $title = trim((string) ($data['job_title'] ?? '')) ?: null;
        $workspace->members()->updateExistingPivot($user->id, ['job_title' => $title]);

        AuditLog::record('admin.user_job_title', $user, ['target' => $user->email, 'workspace' => $workspace->name, 'job_title' => $title], $workspace->id);

        return back()->with('success', 'سمت کاربر ذخیره شد.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'نمی‌توانید حساب خودتان را مسدود کنید.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason'] ?? null])->save();
        $this->endSessions($user);

        AuditLog::record('admin.user_suspended', $user, ['target' => $user->email, 'reason' => $data['reason'] ?? null]);

        return back()->with('success', 'حساب کاربر مسدود شد و از همه دستگاه‌ها خارج شد.');
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AuditLog::record('admin.user_unsuspended', $user, ['target' => $user->email]);

        return back()->with('success', 'حساب کاربر فعال شد.');
    }

    public function logout(User $user): RedirectResponse
    {
        $this->endSessions($user);
        AuditLog::record('admin.user_logged_out', $user, ['target' => $user->email]);

        return back()->with('success', 'کاربر از همه دستگاه‌ها خارج شد.');
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'sms_two_factor_at' => null])->save();
        AuditLog::record('admin.user_2fa_reset', $user, ['target' => $user->email]);

        return back()->with('success', 'ورود دومرحله‌ای کاربر بازنشانی شد.');
    }

    public function verify(User $user): RedirectResponse
    {
        $user->markEmailAsVerified();
        AuditLog::record('admin.user_verified', $user, ['target' => $user->email]);

        return back()->with('success', 'ایمیل کاربر تأیید شد.');
    }

    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        // Invalidates "remember me" cookies as well.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    private function payload(User $u): array
    {
        return [
            ...$u->toCard(),
            'email' => $u->email,
            'is_admin' => $u->is_admin,
            'verified' => $u->hasVerifiedEmail(),
            'two_factor' => $u->hasSecondFactor(),
            'suspended' => $u->isSuspended(),
            'suspended_reason' => $u->suspended_reason,
            'can_create_workspaces' => $u->can_create_workspaces,
            'max_workspaces' => $u->max_workspaces,
            'storage_quota_mb' => $u->storage_quota ? (int) ($u->storage_quota / self::MB) : null,
            'owned_workspaces_count' => $u->owned_workspaces_count ?? 0,
            'workspaces_count' => $u->workspaces_count ?? 0,
            'storage_used' => (int) ($u->storage_used ?? 0),
            'storage_allocated' => (int) ($u->storage_allocated ?? 0),
            'last_seen_at' => $u->last_seen_at?->toIso8601String(),
            'created_at' => $u->created_at->toIso8601String(),
        ];
    }
}
