<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReservationRoomLifecyclePortTest extends TestCase
{
    public function test_count_and_cancel_by_room_are_selective_and_do_not_overwrite_cancelled_at(): void
    {
        $reservations = new FakeReservationRepository;
        $now = new DateTimeImmutable('2026-09-21 12:00:00');
        $alreadyCanceledAt = new DateTimeImmutable('2026-09-20 09:00:00');

        $reservations->seed($this->reservation('future', 'room-1', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));
        $reservations->seed($this->reservation('in-progress', 'room-1', '2026-09-21 11:00:00', '2026-09-21 13:00:00'));
        $reservations->seed($this->reservation('past', 'room-1', '2026-09-20 10:00:00', '2026-09-20 10:30:00'));
        $reservations->seed($this->reservation('canceled-future', 'room-1', '2026-09-23 10:00:00', '2026-09-23 10:30:00', $alreadyCanceledAt));
        $reservations->seed($this->reservation('other-room', 'room-2', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));

        $this->assertSame(1, $reservations->countActiveFutureByRoom('room-1', $now));
        $this->assertSame(['room-1' => 4, 'room-2' => 1], $reservations->countByRoomIds(['room-1', 'room-2']));

        $cancelledAt = new DateTimeImmutable('2026-09-21 12:05:00');
        $reservations->cancelActiveFutureByRoom('room-1', $now, $cancelledAt);

        $this->assertEquals($cancelledAt, $reservations->reservations['future']->cancelledAt);
        $this->assertNull($reservations->reservations['in-progress']->cancelledAt);
        $this->assertNull($reservations->reservations['past']->cancelledAt);
        $this->assertEquals($alreadyCanceledAt, $reservations->reservations['canceled-future']->cancelledAt);
        $this->assertNull($reservations->reservations['other-room']->cancelledAt);

        $reservations->cancelAllActiveByRoom('room-1', $cancelledAt);

        $this->assertEquals($cancelledAt, $reservations->reservations['in-progress']->cancelledAt);
        $this->assertEquals($cancelledAt, $reservations->reservations['past']->cancelledAt);
        $this->assertEquals($alreadyCanceledAt, $reservations->reservations['canceled-future']->cancelledAt);
        $this->assertNull($reservations->reservations['other-room']->cancelledAt);
    }

    private function reservation(
        string $id,
        string $roomId,
        string $startsAt,
        string $endsAt,
        ?DateTimeImmutable $cancelledAt = null,
    ): Reservation {
        return new Reservation(
            id: $id,
            roomId: $roomId,
            responsible: 'Ada',
            title: $id,
            startsAt: new DateTimeImmutable($startsAt),
            endsAt: new DateTimeImmutable($endsAt),
            participants: 2,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
