<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function execute(array $attributes): User
    {
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
        ])->validate();

        return User::query()->create($validated);
    }
}
