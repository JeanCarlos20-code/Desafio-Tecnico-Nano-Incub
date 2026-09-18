<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\UpdateRoom;
use App\Modules\Room\Infra\Http\Requests\UpdateRoomRequest;
use Illuminate\Http\RedirectResponse;

class UpdateRoomController extends Controller
{
    public function __invoke(string $room, UpdateRoomRequest $request, UpdateRoom $updateRoom): RedirectResponse
    {
        $data = $request->validated();

        try {
            $updateRoom->execute(
                $room,
                $data['name'],
                (int) $data['capacity'],
                array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            );
        } catch (RoomNotFound) {
            abort(404);
        }

        return redirect()->route('rooms.index')->with('success', 'Sala atualizada com sucesso.');
    }
}
