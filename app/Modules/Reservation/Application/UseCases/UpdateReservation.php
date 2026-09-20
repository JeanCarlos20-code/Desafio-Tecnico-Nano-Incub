<?php

namespace App\Modules\Reservation\Application\UseCases;

use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;

final class UpdateReservation
{
    public function __construct(
        private readonly ReservationRepository $reservations,
    ) {}

    public function execute(string $id, string $title, string $responsible): Reservation
    {
        $title = trim($title);
        $responsible = trim($responsible);

        $existing = $this->reservations->findById($id);

        if ($existing === null || $existing->cancelledAt !== null) {
            throw new ReservationNotFound;
        }

        return $this->reservations->updateTitleAndResponsible($id, $title, $responsible);
    }
}
