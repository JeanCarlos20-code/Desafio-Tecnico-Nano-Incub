<?php

namespace App\Modules\Reservation\Domain;

use App\Modules\Reservation\Domain\Entities\OccupancyRoom;

interface OccupancyRoomCatalog
{
    public function lockById(string $id): ?OccupancyRoom;

    /**
     * @return list<OccupancyRoom>
     */
    public function listActiveForCreate(): array;

    /**
     * @return list<OccupancyRoom>
     */
    public function listFilterOptions(): array;
}
