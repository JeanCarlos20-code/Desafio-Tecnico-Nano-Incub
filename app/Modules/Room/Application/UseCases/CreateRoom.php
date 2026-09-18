<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class CreateRoom
{
    public function __construct(private readonly RoomRepository $rooms) {}

    public function execute(string $name, int $capacity, ?bool $isActive = null): Room
    {
        return $this->rooms->create($name, $capacity, $isActive ?? true);
    }
}
