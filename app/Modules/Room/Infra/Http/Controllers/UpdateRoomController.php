<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\Errors\DeactivationDecisionRequired;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\UpdateRoom;
use App\Modules\Room\Infra\Http\Requests\UpdateRoomRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class UpdateRoomController extends Controller
{
    public function __invoke(string $room, UpdateRoomRequest $request, UpdateRoom $updateRoom): RedirectResponse
    {
        $data = $request->validated();

        try {
            $result = $updateRoom->execute(
                $room,
                $data['name'],
                (int) $data['capacity'],
                $request->boolean('is_active'),
                $data['scheduled_meetings_action'] ?? null,
            );
        } catch (RoomNotFound) {
            abort(404);
        } catch (DeactivationDecisionRequired $exception) {
            throw ValidationException::withMessages([
                'scheduled_meetings_action' => 'Informe o que deseja fazer com as reuniões programadas.',
                'future_active_count' => [(string) $exception->futureActiveCount],
            ]);
        }

        return redirect()->route('rooms.index')->with('success', $result['flash']);
    }
}
