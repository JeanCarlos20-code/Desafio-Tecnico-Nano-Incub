<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Domain\Entities\OccupancyRoom;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;

final class FakeOccupancyRoomCatalog implements OccupancyRoomCatalog
{
    /** @var array<string, OccupancyRoom> */
    public array $rooms = [];

    /** @var list<string> */
    public array $locked = [];

    public function seed(OccupancyRoom $room): void
    {
        $this->rooms[$room->id] = $room;
    }

    public function lockById(string $id): ?OccupancyRoom
    {
        $this->locked[] = $id;

        return $this->rooms[$id] ?? null;
    }

    public function listActiveForCreate(): array
    {
        return array_values(array_filter(
            $this->rooms,
            fn (OccupancyRoom $room): bool => $room->isActive,
        ));
    }

    public function listFilterOptions(): array
    {
        return array_values($this->rooms);
    }
}
