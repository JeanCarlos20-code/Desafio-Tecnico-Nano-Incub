<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class FindRoom
{
    public function __construct(private readonly RoomRepository $rooms) {}

    public function execute(string $id): Room
    {
        $room = $this->rooms->findById($id);

        if ($room === null) {
            throw new RoomNotFound;
        }

        return $room;
    }
}
