<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Application\UseCases\CancelReservation;
use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CancelReservationTest extends TestCase
{
    public function test_it_sets_cancelled_at_once_and_is_idempotent_on_repeat(): void
    {
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation(null));
        $now = new DateTimeImmutable('2026-09-21 11:00:00');
        $useCase = new CancelReservation($reservations, new FakeClock($now));

        $useCase->execute('res-1');

        $this->assertCount(1, $reservations->canceled);
        $this->assertSame('res-1', $reservations->canceled[0]['id']);
        $this->assertEquals($now, $reservations->canceled[0]['cancelledAt']);
        $this->assertEquals($now, $reservations->reservations['res-1']->cancelledAt);

        $useCase->execute('res-1');

        $this->assertCount(1, $reservations->canceled);
        $this->assertEquals($now, $reservations->reservations['res-1']->cancelledAt);
    }

    public function test_it_rejects_unknown_reservation(): void
    {
        $this->expectException(ReservationNotFound::class);

        (new CancelReservation(
            new FakeReservationRepository,
            new FakeClock(new DateTimeImmutable('2026-09-21 11:00:00')),
        ))->execute('missing');
    }

    private function reservation(?DateTimeImmutable $cancelledAt): Reservation
    {
        return new Reservation(
            id: 'res-1',
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
