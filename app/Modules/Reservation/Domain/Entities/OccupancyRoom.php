<?php

namespace App\Modules\Reservation\Domain\Entities;

final class OccupancyRoom
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $capacity,
        public readonly bool $isActive,
    ) {}
}
