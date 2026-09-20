<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Application\Errors\CapacityExceeded;
use App\Modules\Reservation\Application\Errors\InactiveRoom;
use App\Modules\Reservation\Application\Errors\InvalidDuration;
use App\Modules\Reservation\Application\Errors\OccupancyRoomNotFound;
use App\Modules\Reservation\Application\Errors\ReservationOverlap;
use App\Modules\Reservation\Application\Errors\StartsInPast;
use App\Modules\Reservation\Application\UseCases\CreateReservation;
use App\Modules\Reservation\Infra\Http\Requests\StoreReservationRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class StoreReservationController extends Controller
{
    public function __invoke(StoreReservationRequest $request, CreateReservation $createReservation): RedirectResponse
    {
        $data = $request->validated();
        $timezone = new DateTimeZone((string) config('app.timezone'));

        try {
            $createReservation->execute(
                (string) $data['room_id'],
                $data['responsible'],
                $data['title'],
                new DateTimeImmutable((string) $data['starts_at'], $timezone),
                new DateTimeImmutable((string) $data['ends_at'], $timezone),
                (int) $data['participants'],
            );
        } catch (StartsInPast $exception) {
            return back()->withErrors(['starts_at' => $exception->getMessage()]);
        } catch (InvalidDuration $exception) {
            return back()->withErrors(['ends_at' => $exception->getMessage()]);
        } catch (OccupancyRoomNotFound|InactiveRoom $exception) {
            return back()->withErrors(['room_id' => $exception->getMessage()]);
        } catch (CapacityExceeded $exception) {
            return back()->withErrors(['participants' => $exception->getMessage()]);
        } catch (ReservationOverlap $exception) {
            return back()->withErrors(['starts_at' => $exception->getMessage()]);
        } catch (RuntimeException) {
            return back()->withErrors(['general' => 'Não foi possível salvar a reserva. Tente novamente.']);
        }

        return redirect()->route('reservations.index')->with('success', 'Reserva criada com sucesso.');
    }
}
