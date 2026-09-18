<?php

namespace App\Modules\User\Infra\Http;

use App\Modules\User\Domain\UserAuthenticator;
use Illuminate\Support\Facades\Auth;

final class LaravelUserAuthenticator implements UserAuthenticator
{
    public function attempt(string $email, string $password): bool
    {
        return Auth::attempt([
            'email' => $email,
            'password' => $password,
        ], false);
    }
}
