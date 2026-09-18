<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class ListRooms
{
    public function __construct(private readonly RoomRepository $rooms) {}

    /**
     * @return array{items: list<Room>, total: int}
     */
    public function execute(int $page, int $perPage = 15): array
    {
        if ($page < 1) {
            $page = 1;
        }

        return $this->rooms->listPage($page, $perPage);
    }
}
