<?php

namespace App\Actions\Fortify;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9۰-۹\s\-]{7,20}$/u'],
            'theme' => ['nullable', Rule::in(['system', 'light', 'dark'])],
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = $input['email'] !== $user->email;

        $user->forceFill([
            'name' => trim($input['name']),
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'theme' => $input['theme'] ?? $user->theme,
        ] + ($emailChanged ? ['email_verified_at' => null] : []))->save();

        if ($emailChanged) {
            AuditLog::record('account.email_changed', userId: $user->id);
            $user->sendEmailVerificationNotification();
        }
    }
}
