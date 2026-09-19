<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\UseCases\ListReservations;
use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ListReservationsTest extends TestCase
{
    public function test_it_clamps_page_and_forwards_room_id_plus_timezone_day_bounds(): void
    {
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation('r1'));

        $result = (new ListReservations($reservations))->execute(0, 15, 'room-1', '2026-09-21', 'UTC');

        $this->assertCount(1, $reservations->listed);
        $this->assertSame(1, $reservations->listed[0]['page']);
        $this->assertSame(15, $reservations->listed[0]['perPage']);
        $this->assertSame('room-1', $reservations->listed[0]['roomId']);
        $this->assertSame('2026-09-21 00:00:00', $reservations->listed[0]['dayStart']->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $reservations->listed[0]['dayStart']->getTimezone()->getName());
        $this->assertSame('2026-09-22 00:00:00', $reservations->listed[0]['dayEndExclusive']->format('Y-m-d H:i:s'));
        $this->assertTrue($result['hasAny']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('r1', $result['items'][0]->id);
    }

    public function test_it_forwards_null_room_id_and_reports_has_any_false_when_empty(): void
    {
        $reservations = new FakeReservationRepository;

        $result = (new ListReservations($reservations))->execute(2, 15, null, '2026-09-21', 'UTC');

        $this->assertNull($reservations->listed[0]['roomId']);
        $this->assertSame(2, $reservations->listed[0]['page']);
        $this->assertFalse($result['hasAny']);
        $this->assertSame(0, $result['total']);
    }

    private function reservation(string $id): Reservation
    {
        return new Reservation(
            id: $id,
            roomId: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            startsAt: new DateTimeImmutable('2026-09-21 10:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 10:30:00'),
            participants: 2,
            cancelledAt: null,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
