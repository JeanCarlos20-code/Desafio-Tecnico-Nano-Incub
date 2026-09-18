<?php

namespace App\Modules\User\Domain;

interface UserAuthenticator
{
    public function attempt(string $email, string $password): bool;
}
