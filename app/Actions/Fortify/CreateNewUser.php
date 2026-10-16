<?php

namespace App\Actions\Fortify;

use App\Actions\CreateWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private CreateWorkspace $createWorkspace) {}

    public function create(array $input): User
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:190', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => trim($input['name']),
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            ($this->createWorkspace)($user, 'میزکار '.Str::limit(trim($input['name']), 40, ''));

            return $user;
        });
    }
}
