<?php

namespace App\Modules\Reservation\Domain\Entities;

use DateTimeImmutable;

final class Reservation
{
    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        public readonly string $responsible,
        public readonly string $title,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly int $participants,
        public readonly ?DateTimeImmutable $cancelledAt,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        public readonly string $roomName = '',
    ) {}
}
