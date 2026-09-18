<?php

namespace Tests\Unit\Room;

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;
use DateTimeImmutable;

final class FakeRoomRepository implements RoomRepository
{
    /** @var array<string, Room> */
    public array $rooms = [];

    /** @var list<array{name: string, capacity: int, is_active: bool}> */
    public array $created = [];

    /** @var list<array{id: string, name: string, capacity: int, is_active: bool}> */
    public array $updated = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<array{page: int, perPage: int}> */
    public array $listed = [];

    public function seed(Room $room): void
    {
        $this->rooms[$room->id] = $room;
    }

    public function listPage(int $page, int $perPage): array
    {
        $this->listed[] = compact('page', 'perPage');

        $items = array_values($this->rooms);
        usort($items, fn (Room $left, Room $right): int => strcmp($left->id, $right->id));

        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'total' => count($items),
        ];
    }

    public function findById(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function create(string $name, int $capacity, bool $isActive): Room
    {
        $this->created[] = [
            'name' => $name,
            'capacity' => $capacity,
            'is_active' => $isActive,
        ];

        $room = new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: $name,
            capacity: $capacity,
            isActive: $isActive,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );

        $this->rooms[$room->id] = $room;

        return $room;
    }

    public function update(string $id, string $name, int $capacity, bool $isActive): Room
    {
        $this->updated[] = [
            'id' => $id,
            'name' => $name,
            'capacity' => $capacity,
            'is_active' => $isActive,
        ];

        $existing = $this->rooms[$id];
        $updated = new Room(
            id: $id,
            name: $name,
            capacity: $capacity,
            isActive: $isActive,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );

        $this->rooms[$id] = $updated;

        return $updated;
    }

    public function delete(string $id): void
    {
        $this->deleted[] = $id;
        unset($this->rooms[$id]);
    }
}
