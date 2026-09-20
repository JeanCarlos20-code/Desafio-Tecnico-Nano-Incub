<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Domain\Entities\OccupancyRoom;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;
use Inertia\Inertia;
use Inertia\Response;

class CreateReservationController extends Controller
{
    public function __invoke(OccupancyRoomCatalog $rooms): Response
    {
        return Inertia::render('Reservation/Create', [
            'rooms' => array_map(
                fn (OccupancyRoom $room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'capacity' => $room->capacity,
                ],
                $rooms->listActiveForCreate(),
            ),
            'timezone' => (string) config('app.timezone'),
        ]);
    }
}
