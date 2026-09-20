<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use DateTimeZone;
use Inertia\Inertia;
use Inertia\Response;

class EditReservationController extends Controller
{
    public function __invoke(string $reservation, ReservationRepository $reservations): Response
    {
        $found = $reservations->findById($reservation);

        if ($found === null || $found->cancelledAt !== null) {
            abort(404);
        }

        $timezone = new DateTimeZone((string) config('app.timezone'));
        $startsAt = $found->startsAt->setTimezone($timezone);
        $endsAt = $found->endsAt->setTimezone($timezone);

        return Inertia::render('Reservation/Edit', [
            'id' => $found->id,
            'title' => $found->title,
            'responsible' => $found->responsible,
            'room_id' => $found->roomId,
            'room_name' => $found->roomName,
            'date' => $startsAt->format('Y-m-d'),
            'start_time' => $startsAt->format('H:i'),
            'end_time' => $endsAt->format('H:i'),
            'participants' => $found->participants,
        ]);
    }
}
