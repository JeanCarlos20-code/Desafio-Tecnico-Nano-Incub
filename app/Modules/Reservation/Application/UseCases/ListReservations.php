<?php

namespace App\Modules\Reservation\Application\UseCases;

use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use DateTimeImmutable;
use DateTimeZone;

final class ListReservations
{
    public function __construct(private readonly ReservationRepository $reservations) {}

    /**
     * @return array{items: list<Reservation>, total: int, hasAny: bool}
     */
    public function execute(int $page, int $perPage, ?string $roomId, string $date, string $timezone): array
    {
        if ($page < 1) {
            $page = 1;
        }

        $dayStart = new DateTimeImmutable($date.' 00:00:00', new DateTimeZone($timezone));
        $dayEndExclusive = $dayStart->modify('+1 day');

        $pageResult = $this->reservations->listPage($page, $perPage, $roomId, $dayStart, $dayEndExclusive);

        return [
            'items' => $pageResult['items'],
            'total' => $pageResult['total'],
            'hasAny' => $this->reservations->hasAny(),
        ];
    }
}
