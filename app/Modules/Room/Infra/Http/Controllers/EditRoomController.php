<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\FindRoom;
use Inertia\Inertia;
use Inertia\Response;

class EditRoomController extends Controller
{
    public function __invoke(string $room, FindRoom $findRoom): Response
    {
        try {
            $found = $findRoom->execute($room);
        } catch (RoomNotFound) {
            abort(404);
        }

        return Inertia::render('Room/Edit', [
            'room' => [
                'id' => $found->id,
                'name' => $found->name,
                'capacity' => $found->capacity,
                'is_active' => $found->isActive,
            ],
            'has_registered_meetings' => false,
        ]);
    }
}
