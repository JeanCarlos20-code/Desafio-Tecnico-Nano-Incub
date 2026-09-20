<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Application\UseCases\UpdateReservation;
use App\Modules\Reservation\Infra\Http\Requests\UpdateReservationRequest;
use Illuminate\Http\RedirectResponse;

class UpdateReservationController extends Controller
{
    public function __invoke(
        string $reservation,
        UpdateReservationRequest $request,
        UpdateReservation $updateReservation,
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $updateReservation->execute($reservation, $data['title'], $data['responsible']);
        } catch (ReservationNotFound) {
            abort(404);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reserva atualizada com sucesso.');
    }
}
