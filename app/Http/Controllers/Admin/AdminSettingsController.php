<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    private const MB = 1024 * 1024;

    public function show(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'registration_open' => (bool) Setting::get('registration_open'),
                'default_storage_mb' => (int) (Setting::get('default_storage_limit') / self::MB),
                'default_max_workspaces' => (int) Setting::get('default_max_workspaces'),
                'max_upload_mb' => (int) Setting::get('max_upload_mb'),
                ...collect(Setting::LETTERHEAD_KEYS)->mapWithKeys(fn ($k) => [$k => (string) Setting::get($k)])->all(),
            ],
            'requires_2fa' => (bool) config('daftar.admin_requires_2fa'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_open' => ['required', 'boolean'],
            'default_storage_mb' => ['required', 'integer', 'min:10', 'max:10485760'],
            'default_max_workspaces' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_upload_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'company_name' => ['required', 'string', 'max:80'],
            'company_tagline' => ['nullable', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:200'],
            'company_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9۰-۹+\-\s()]*$/u'],
            'company_email' => ['nullable', 'email:rfc', 'max:120'],
            'company_website' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.\-\/:]+$/i'],
        ]);

        Setting::put([
            'registration_open' => $data['registration_open'],
            'default_storage_limit' => $data['default_storage_mb'] * self::MB,
            'default_max_workspaces' => $data['default_max_workspaces'],
            'max_upload_mb' => $data['max_upload_mb'],
            ...collect(Setting::LETTERHEAD_KEYS)->mapWithKeys(fn ($k) => [$k => trim((string) ($data[$k] ?? ''))])->all(),
        ]);

        AuditLog::record('admin.settings_updated', properties: $data);

        return back()->with('success', 'تنظیمات سایت ذخیره شد.');
    }
}
