<?php

namespace App\Modules\Reservation\Application\UseCases;

use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;

final class CancelReservation
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly Clock $clock,
    ) {}

    public function execute(string $id): void
    {
        $reservation = $this->reservations->findById($id);

        if ($reservation === null) {
            throw new ReservationNotFound;
        }

        if ($reservation->cancelledAt !== null) {
            return;
        }

        $this->reservations->markCanceled($id, $this->clock->now());
    }
}
