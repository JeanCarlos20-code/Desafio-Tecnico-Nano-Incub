<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class ListRooms
{
    public function __construct(private readonly RoomRepository $rooms) {}

    /**
     * @return array{items: list<Room>, total: int, hasAny: bool}
     */
    public function execute(int $page, int $perPage = 15, string $status = 'all'): array
    {
        if ($page < 1) {
            $page = 1;
        }

        $pageResult = $this->rooms->listPage($page, $perPage, $status);

        return [
            'items' => $pageResult['items'],
            'total' => $pageResult['total'],
            'hasAny' => $this->rooms->hasAny(),
        ];
    }
}
