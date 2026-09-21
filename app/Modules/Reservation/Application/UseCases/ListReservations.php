<?php

namespace App\Modules\Reservation\Application\UseCases;

use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use DateTimeImmutable;
use DateTimeZone;

final class ListReservations
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{items: list<Reservation>, total: int, hasAny: bool}
     */
    public function execute(
        int $page,
        int $perPage,
        ?string $roomId,
        string $period,
        ?string $startsOn,
        ?string $endsOn,
        string $timezone,
        string $status = 'active',
    ): array {
        if ($page < 1) {
            $page = 1;
        }

        [$rangeStart, $rangeEndExclusive] = $this->resolveWindow($period, $startsOn, $endsOn, $timezone);

        $pageResult = $this->reservations->listPage($page, $perPage, $roomId, $rangeStart, $rangeEndExclusive, $status);

        return [
            'items' => $pageResult['items'],
            'total' => $pageResult['total'],
            'hasAny' => $this->reservations->hasAny(),
        ];
    }

    /**
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
     */
    private function resolveWindow(string $period, ?string $startsOn, ?string $endsOn, string $timezone): array
    {
        $tz = new DateTimeZone($timezone);

        if ($startsOn !== null && $endsOn !== null) {
            $rangeStart = new DateTimeImmutable($startsOn.' 00:00:00', $tz);
            $rangeEndExclusive = (new DateTimeImmutable($endsOn.' 00:00:00', $tz))->modify('+1 day');

            return [$rangeStart, $rangeEndExclusive];
        }

        if ($period === 'all') {
            return [null, null];
        }

        $today = $this->clock->now()->setTimezone($tz)->setTime(0, 0);

        return match ($period) {
            'today' => [$today, $today->modify('+1 day')],
            'tomorrow' => [$today->modify('+1 day'), $today->modify('+2 days')],
            'week' => [$today, $today->modify('+7 days')],
            default => [null, null],
        };
    }
}
