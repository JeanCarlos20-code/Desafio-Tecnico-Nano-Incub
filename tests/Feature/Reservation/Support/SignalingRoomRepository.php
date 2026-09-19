<?php

declare(strict_types=1);

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class SignalingRoomRepository implements RoomRepository
{
    public function __construct(
        private readonly RoomRepository $inner,
        private readonly string $barrier,
    ) {}

    public function listPage(int $page, int $perPage, string $status = 'all'): array
    {
        return $this->inner->listPage($page, $perPage, $status);
    }

    public function findById(string $id): ?Room
    {
        return $this->inner->findById($id);
    }

    public function lockById(string $id): ?Room
    {
        $room = $this->inner->lockById($id);
        file_put_contents($this->barrier.'.lifecycle-started', '1');

        $deadline = microtime(true) + 15;
        while (! is_file($this->barrier.'.create-started')) {
            if (microtime(true) > $deadline) {
                fwrite(STDERR, "timeout waiting for create to start\n");
                exit(2);
            }

            usleep(5000);
        }

        usleep(400000);

        return $room;
    }

    public function hasAny(): bool
    {
        return $this->inner->hasAny();
    }

    public function create(string $name, int $capacity, bool $isActive): Room
    {
        return $this->inner->create($name, $capacity, $isActive);
    }

    public function update(string $id, string $name, int $capacity, bool $isActive): Room
    {
        return $this->inner->update($id, $name, $capacity, $isActive);
    }

    public function delete(string $id): void
    {
        $this->inner->delete($id);
    }
}
