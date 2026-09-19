<?php

namespace App\Modules\Reservation\Infra\Database\Repositories;

use App\Modules\Reservation\Domain\Entities\OccupancyRoom;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;
use App\Modules\Room\Infra\Database\Models\Room as RoomModel;

final class EloquentOccupancyRoomCatalog implements OccupancyRoomCatalog
{
    public function lockById(string $id): ?OccupancyRoom
    {
        $model = RoomModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toOccupancy($model) : null;
    }

    public function listActiveForCreate(): array
    {
        return RoomModel::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (RoomModel $model): OccupancyRoom => $this->toOccupancy($model))
            ->all();
    }

    public function listFilterOptions(): array
    {
        return RoomModel::query()
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereExists(function ($history): void {
                        $history->selectRaw('1')
                            ->from('reservations')
                            ->whereColumn('reservations.room_id', 'rooms.id');
                    });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (RoomModel $model): OccupancyRoom => $this->toOccupancy($model))
            ->all();
    }

    private function toOccupancy(RoomModel $model): OccupancyRoom
    {
        return new OccupancyRoom(
            id: (string) $model->getKey(),
            name: $model->name,
            capacity: (int) $model->capacity,
            isActive: (bool) $model->is_active,
        );
    }
}
