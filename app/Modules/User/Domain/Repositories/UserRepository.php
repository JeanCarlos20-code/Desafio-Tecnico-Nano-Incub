<?php

namespace App\Modules\User\Domain\Repositories;

use App\Modules\User\Domain\Entities\User;

interface UserRepository
{
    public function existsByEmail(string $email): bool;

    public function create(string $name, string $email, string $password): User;
}
