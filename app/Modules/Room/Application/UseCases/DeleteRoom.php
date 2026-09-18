<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class DeleteRoom
{
    public function __construct(private readonly RoomRepository $rooms) {}

    public function execute(string $id): void
    {
        if ($this->rooms->findById($id) === null) {
            throw new RoomNotFound;
        }

        $this->rooms->delete($id);
    }
}
