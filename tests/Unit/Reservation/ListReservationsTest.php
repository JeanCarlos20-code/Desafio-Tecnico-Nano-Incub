<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\UseCases\ListReservations;
use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ListReservationsTest extends TestCase
{
    public function test_period_all_without_range_forwards_null_list_page_bounds(): void
    {
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation('r1'));
        $clock = new FakeClock(new DateTimeImmutable('2026-09-21 12:00:00'));

        $result = $this->list($reservations, $clock, page: 1, roomId: null, period: 'all');

        $this->assertCount(1, $reservations->listed);
        $this->assertSame(1, $reservations->listed[0]['page']);
        $this->assertSame(15, $reservations->listed[0]['perPage']);
        $this->assertNull($reservations->listed[0]['roomId']);
        $this->assertNull($reservations->listed[0]['rangeStart']);
        $this->assertNull($reservations->listed[0]['rangeEndExclusive']);
        $this->assertTrue($result['hasAny']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('r1', $result['items'][0]->id);
    }

    public function test_it_resolves_today_tomorrow_and_week_half_open_windows_from_clock_timezone(): void
    {
        $reservations = new FakeReservationRepository;
        $clock = new FakeClock(new DateTimeImmutable('2026-09-21 15:00:00', new DateTimeZone('UTC')));

        (new ListReservations($reservations, $clock))->execute(1, 15, 'room-1', 'today', null, null, 'America/Sao_Paulo');
        (new ListReservations($reservations, $clock))->execute(1, 15, null, 'tomorrow', null, null, 'UTC');
        (new ListReservations($reservations, $clock))->execute(1, 15, null, 'week', null, null, 'UTC');

        $this->assertSame('2026-09-21 00:00:00', $reservations->listed[0]['rangeStart']->format('Y-m-d H:i:s'));
        $this->assertSame('America/Sao_Paulo', $reservations->listed[0]['rangeStart']->getTimezone()->getName());
        $this->assertSame('2026-09-22 00:00:00', $reservations->listed[0]['rangeEndExclusive']->format('Y-m-d H:i:s'));

        $this->assertSame('2026-09-22 00:00:00', $reservations->listed[1]['rangeStart']->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $reservations->listed[1]['rangeStart']->getTimezone()->getName());
        $this->assertSame('2026-09-23 00:00:00', $reservations->listed[1]['rangeEndExclusive']->format('Y-m-d H:i:s'));

        $this->assertSame('2026-09-21 00:00:00', $reservations->listed[2]['rangeStart']->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $reservations->listed[2]['rangeStart']->getTimezone()->getName());
        $this->assertSame('2026-09-28 00:00:00', $reservations->listed[2]['rangeEndExclusive']->format('Y-m-d H:i:s'));
        $this->assertSame('room-1', $reservations->listed[0]['roomId']);
    }

    public function test_complete_range_uses_inclusive_dates_and_ignores_period(): void
    {
        $reservations = new FakeReservationRepository;
        $clock = new FakeClock(new DateTimeImmutable('2026-09-21 12:00:00'));

        $this->list(
            $reservations,
            $clock,
            page: 1,
            roomId: null,
            period: 'today',
            startsOn: '2026-09-22',
            endsOn: '2026-09-23',
            timezone: 'UTC',
        );

        $this->assertSame('2026-09-22 00:00:00', $reservations->listed[0]['rangeStart']->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $reservations->listed[0]['rangeStart']->getTimezone()->getName());
        $this->assertSame('2026-09-24 00:00:00', $reservations->listed[0]['rangeEndExclusive']->format('Y-m-d H:i:s'));
    }

    public function test_it_clamps_page_to_one_and_excludes_cancelled_at_rows(): void
    {
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation('active-1'));
        $reservations->seed($this->reservation('canceled-1', new DateTimeImmutable('2026-09-21 08:00:00')));
        $clock = new FakeClock(new DateTimeImmutable('2026-09-21 12:00:00'));

        $result = $this->list($reservations, $clock, page: 0, roomId: 'room-1', period: 'all');

        $this->assertSame(1, $reservations->listed[0]['page']);
        $this->assertSame('room-1', $reservations->listed[0]['roomId']);
        $this->assertTrue($result['hasAny']);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('active-1', $result['items'][0]->id);

        $onlyCanceled = new FakeReservationRepository;
        $onlyCanceled->seed($this->reservation('canceled-only', new DateTimeImmutable('2026-09-21 08:00:00')));

        $empty = $this->list($onlyCanceled, $clock, page: 1, roomId: null, period: 'all');

        $this->assertFalse($empty['hasAny']);
        $this->assertSame(0, $empty['total']);
        $this->assertSame([], $empty['items']);
    }

    private function list(
        FakeReservationRepository $reservations,
        FakeClock $clock,
        int $page,
        ?string $roomId,
        string $period,
        ?string $startsOn = null,
        ?string $endsOn = null,
        string $timezone = 'UTC',
    ): array {
        return (new ListReservations($reservations, $clock))->execute(
            $page,
            15,
            $roomId,
            $period,
            $startsOn,
            $endsOn,
            $timezone,
        );
    }

    private function reservation(string $id, ?DateTimeImmutable $cancelledAt = null): Reservation
    {
        return new Reservation(
            id: $id,
            roomId: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            startsAt: new DateTimeImmutable('2026-09-21 10:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 10:30:00'),
            participants: 2,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
