<?php

namespace App\Modules\Room\Domain\Entities;

use DateTimeImmutable;

final class Room
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $capacity,
        public readonly bool $isActive,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $deletedAt,
    ) {}
}
