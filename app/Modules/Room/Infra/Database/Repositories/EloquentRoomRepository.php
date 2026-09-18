<?php

namespace App\Modules\Room\Infra\Database\Repositories;

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;
use App\Modules\Room\Infra\Database\Models\Room as RoomModel;
use DateTimeImmutable;

final class EloquentRoomRepository implements RoomRepository
{
    public function listPage(int $page, int $perPage): array
    {
        $query = RoomModel::query()->orderBy('id');

        $total = (clone $query)->count();
        $models = $query->forPage($page, $perPage)->get();

        return [
            'items' => $models->map(fn (RoomModel $model): Room => $this->toDomain($model))->all(),
            'total' => $total,
        ];
    }

    public function findById(string $id): ?Room
    {
        $model = RoomModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function create(string $name, int $capacity, bool $isActive): Room
    {
        $model = RoomModel::query()->create([
            'name' => $name,
            'capacity' => $capacity,
            'is_active' => $isActive,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function update(string $id, string $name, int $capacity, bool $isActive): Room
    {
        $model = RoomModel::query()->find($id);

        if ($model === null) {
            throw new \RuntimeException('Room not found.');
        }

        $model->fill([
            'name' => $name,
            'capacity' => $capacity,
            'is_active' => $isActive,
        ]);
        $model->save();

        return $this->toDomain($model->refresh());
    }

    public function delete(string $id): void
    {
        $model = RoomModel::query()->find($id);

        if ($model === null) {
            return;
        }

        $model->delete();
    }

    private function toDomain(RoomModel $model): Room
    {
        return new Room(
            id: (string) $model->getKey(),
            name: $model->name,
            capacity: (int) $model->capacity,
            isActive: (bool) $model->is_active,
            createdAt: self::immutable($model->created_at),
            updatedAt: self::immutable($model->updated_at),
            deletedAt: self::immutable($model->deleted_at),
        );
    }

    private static function immutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
