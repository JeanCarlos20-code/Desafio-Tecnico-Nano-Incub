<?php

namespace Tests\Unit\Room;

use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\DeleteRoom;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Reservation\FakeClock;
use Tests\Unit\Reservation\FakeReservationRepository;
use Tests\Unit\Reservation\FakeTransaction;

class DeleteRoomTest extends TestCase
{
    public function test_it_cancels_every_active_reservation_then_deletes_the_room(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $now = new DateTimeImmutable('2026-09-21 12:00:00');
        $alreadyCanceledAt = new DateTimeImmutable('2026-09-20 09:00:00');
        $rooms->seed(new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: 'Sala Azul',
            capacity: 10,
            isActive: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        ));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));
        $reservations->seed($this->reservation('in-progress', '2026-09-21 11:00:00', '2026-09-21 13:00:00'));
        $reservations->seed($this->reservation('past', '2026-09-20 10:00:00', '2026-09-20 10:30:00'));
        $reservations->seed($this->reservation('already', '2026-09-23 10:00:00', '2026-09-23 10:30:00', $alreadyCanceledAt));

        (new DeleteRoom(
            $rooms,
            $reservations,
            new FakeClock($now),
            new FakeTransaction,
        ))->execute('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11');

        $this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->locked);
        $this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->deleted);
        $this->assertEquals($now, $reservations->reservations['future']->cancelledAt);
        $this->assertEquals($now, $reservations->reservations['in-progress']->cancelledAt);
        $this->assertEquals($now, $reservations->reservations['past']->cancelledAt);
        $this->assertEquals($alreadyCanceledAt, $reservations->reservations['already']->cancelledAt);
    }

    public function test_it_throws_room_not_found_and_writes_nothing_when_the_room_is_missing(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));

        try {
            (new DeleteRoom(
                $rooms,
                $reservations,
                new FakeClock(new DateTimeImmutable('2026-09-21 12:00:00')),
                new FakeTransaction,
            ))->execute('missing-id');
            $this->fail('Expected RoomNotFound');
        } catch (RoomNotFound) {
            $this->assertSame([], $rooms->deleted);
            $this->assertSame([], $reservations->canceled);
            $this->assertNull($reservations->reservations['future']->cancelledAt);
        }
    }

    private function reservation(
        string $id,
        string $startsAt,
        string $endsAt,
        ?DateTimeImmutable $cancelledAt = null,
    ): Reservation {
        return new Reservation(
            id: $id,
            roomId: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
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
