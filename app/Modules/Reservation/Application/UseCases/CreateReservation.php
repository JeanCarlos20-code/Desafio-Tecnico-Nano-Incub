<?php

namespace App\Modules\Reservation\Application\UseCases;

use App\Modules\Reservation\Application\Errors\CapacityExceeded;
use App\Modules\Reservation\Application\Errors\InactiveRoom;
use App\Modules\Reservation\Application\Errors\InvalidDuration;
use App\Modules\Reservation\Application\Errors\OccupancyRoomNotFound;
use App\Modules\Reservation\Application\Errors\ReservationOverlap;
use App\Modules\Reservation\Application\Errors\StartsInPast;
use App\Modules\Reservation\Application\Transaction;
use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use DateTimeImmutable;

final class CreateReservation
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly OccupancyRoomCatalog $rooms,
        private readonly Clock $clock,
        private readonly Transaction $transaction,
    ) {}

    public function execute(
        string $roomId,
        string $responsible,
        string $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        int $participants,
    ): Reservation {
        $responsible = trim($responsible);
        $title = trim($title);

        if ($startsAt < $this->clock->now()) {
            throw new StartsInPast;
        }

        $durationMinutes = ($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60;

        if ($durationMinutes < 30 || $durationMinutes > 240) {
            throw new InvalidDuration;
        }

        return $this->transaction->run(function () use ($roomId, $responsible, $title, $startsAt, $endsAt, $participants): Reservation {
            $room = $this->rooms->lockById($roomId);

            if ($room === null) {
                throw new OccupancyRoomNotFound;
            }

            if (! $room->isActive) {
                throw new InactiveRoom;
            }

            if ($participants > $room->capacity) {
                throw new CapacityExceeded;
            }

            if ($this->reservations->hasActiveOverlap($roomId, $startsAt, $endsAt)) {
                throw new ReservationOverlap;
            }

            return $this->reservations->create(
                $roomId,
                $responsible,
                $title,
                $startsAt,
                $endsAt,
                $participants,
            );
        });
    }
}
