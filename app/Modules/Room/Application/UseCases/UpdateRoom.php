<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class UpdateRoom
{
    public function __construct(private readonly RoomRepository $rooms) {}

    public function execute(string $id, string $name, int $capacity, ?bool $isActive = null): Room
    {
        $existing = $this->rooms->findById($id);

        if ($existing === null) {
            throw new RoomNotFound;
        }

        return $this->rooms->update($id, $name, $capacity, $isActive ?? $existing->isActive);
    }
}
