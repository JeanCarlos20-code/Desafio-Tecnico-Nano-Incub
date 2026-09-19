<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Application\UseCases\CancelReservation;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class CancelReservationController extends Controller
{
    public function __invoke(string $reservation, CancelReservation $cancelReservation): RedirectResponse
    {
        try {
            $cancelReservation->execute($reservation);
        } catch (ReservationNotFound) {
            abort(404);
        } catch (RuntimeException) {
            return back()->withErrors(['general' => 'Não foi possível cancelar a reserva. Tente novamente.']);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reserva cancelada com sucesso. O horário está disponível novamente.');
    }
}
