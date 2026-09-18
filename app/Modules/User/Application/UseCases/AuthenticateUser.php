<?php

namespace App\Modules\User\Application\UseCases;

use App\Modules\User\Application\Errors\InvalidCredentials;
use App\Modules\User\Domain\UserAuthenticator;

final class AuthenticateUser
{
    public function __construct(private readonly UserAuthenticator $authenticator) {}

    public function execute(string $email, string $password): void
    {
        if (! $this->authenticator->attempt($email, $password)) {
            throw new InvalidCredentials;
        }
    }
}
