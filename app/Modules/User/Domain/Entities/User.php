<?php

namespace App\Modules\User\Domain\Entities;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $rememberToken,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $deletedAt,
    ) {}
}
