<?php

namespace App\Modules\User\Application\UseCases;

use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Repositories\UserRepository;

final class CreateUser
{
    public function __construct(private readonly UserRepository $users) {}

    public function execute(string $name, string $email, string $password): User
    {
        return $this->users->create($name, $email, $password);
    }
}
