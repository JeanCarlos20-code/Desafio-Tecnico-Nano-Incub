<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\UseCases\CreateRoom;
use App\Modules\Room\Infra\Http\Requests\StoreRoomRequest;
use Illuminate\Http\RedirectResponse;

class StoreRoomController extends Controller
{
    public function __invoke(StoreRoomRequest $request, CreateRoom $createRoom): RedirectResponse
    {
        $data = $request->validated();

        $createRoom->execute(
            $data['name'],
            (int) $data['capacity'],
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );

        return redirect()->route('rooms.index')->with('success', 'Sala criada com sucesso.');
    }
}
