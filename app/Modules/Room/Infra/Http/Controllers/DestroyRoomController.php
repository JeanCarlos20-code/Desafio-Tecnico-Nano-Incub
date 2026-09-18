<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\DeleteRoom;
use Illuminate\Http\RedirectResponse;

class DestroyRoomController extends Controller
{
    public function __invoke(string $room, DeleteRoom $deleteRoom): RedirectResponse
    {
        try {
            $deleteRoom->execute($room);
        } catch (RoomNotFound) {
            abort(404);
        }

        return redirect()->route('rooms.index')->with('success', 'Sala excluída com sucesso.');
    }
}
