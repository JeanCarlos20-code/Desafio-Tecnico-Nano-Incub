<?php

namespace App\Modules\Room\Domain\Repositories;

use App\Modules\Room\Domain\Entities\Room;

interface RoomRepository
{
    /**
     * @return array{items: list<Room>, total: int}
     */
    public function listPage(int $page, int $perPage): array;

    public function findById(string $id): ?Room;

    public function create(string $name, int $capacity, bool $isActive): Room;

    public function update(string $id, string $name, int $capacity, bool $isActive): Room;

    public function delete(string $id): void;
}
