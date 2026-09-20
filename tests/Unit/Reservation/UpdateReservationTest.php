<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\Errors\ReservationNotFound;
use App\Modules\Reservation\Application\UseCases\UpdateReservation;
use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UpdateReservationTest extends TestCase
{
    public function test_it_persists_trimmed_title_and_responsible_and_leaves_occupancy_unchanged(): void
    {
        $reservations = new FakeReservationRepository;
        $seeded = $this->reservation(null, new DateTimeImmutable('2026-01-01 10:00:00'));
        $reservations->seed($seeded);
        $useCase = new UpdateReservation($reservations);

        $updated = $useCase->execute('res-1', '  Daily revisada  ', '  Ada Lovelace  ');

        $this->assertCount(1, $reservations->updated);
        $this->assertSame('res-1', $reservations->updated[0]['id']);
        $this->assertSame('Daily revisada', $reservations->updated[0]['title']);
        $this->assertSame('Ada Lovelace', $reservations->updated[0]['responsible']);
        $this->assertSame('Daily revisada', $updated->title);
        $this->assertSame('Ada Lovelace', $updated->responsible);
        $this->assertSame($seeded->roomId, $updated->roomId);
        $this->assertEquals($seeded->startsAt, $updated->startsAt);
        $this->assertEquals($seeded->endsAt, $updated->endsAt);
        $this->assertSame($seeded->participants, $updated->participants);
        $this->assertNull($updated->cancelledAt);
    }

    public function test_it_throws_not_found_for_a_missing_id_and_writes_nothing(): void
    {
        $reservations = new FakeReservationRepository;
        $useCase = new UpdateReservation($reservations);

        try {
            $useCase->execute('missing', 'Daily', 'Ada');
            $this->fail('Expected ReservationNotFound');
        } catch (ReservationNotFound) {
            $this->assertSame([], $reservations->updated);
            $this->assertSame([], $reservations->reservations);
        }
    }

    public function test_it_throws_not_found_for_a_cancelled_reservation_and_writes_nothing(): void
    {
        $reservations = new FakeReservationRepository;
        $cancelledAt = new DateTimeImmutable('2026-09-21 09:00:00');
        $seeded = $this->reservation($cancelledAt);
        $reservations->seed($seeded);
        $useCase = new UpdateReservation($reservations);

        try {
            $useCase->execute('res-1', 'Novo título', 'Novo responsável');
            $this->fail('Expected ReservationNotFound');
        } catch (ReservationNotFound) {
            $this->assertSame([], $reservations->updated);
            $stored = $reservations->reservations['res-1'];
            $this->assertSame($seeded->title, $stored->title);
            $this->assertSame($seeded->responsible, $stored->responsible);
            $this->assertEquals($seeded->startsAt, $stored->startsAt);
            $this->assertEquals($seeded->endsAt, $stored->endsAt);
            $this->assertSame($seeded->roomId, $stored->roomId);
            $this->assertSame($seeded->participants, $stored->participants);
            $this->assertEquals($cancelledAt, $stored->cancelledAt);
        }
    }

    public function test_it_does_not_call_overlap_duration_capacity_or_lock_collaborators(): void
    {
        $reservations = new FakeReservationRepository;
        $reservations->seed($this->reservation(null));
        $useCase = new UpdateReservation($reservations);

        $useCase->execute('res-1', 'Daily', 'Ada');

        $this->assertSame([], $reservations->occupancyMethodCalls);
        $this->assertSame([], $reservations->canceled);
        $stored = $reservations->reservations['res-1'];
        $this->assertEquals(new DateTimeImmutable('2026-09-21 10:00:00'), $stored->startsAt);
        $this->assertEquals(new DateTimeImmutable('2026-09-21 10:30:00'), $stored->endsAt);
        $this->assertSame('room-1', $stored->roomId);
        $this->assertSame(2, $stored->participants);
        $this->assertNull($stored->cancelledAt);
    }

    private function reservation(?DateTimeImmutable $cancelledAt, ?DateTimeImmutable $startsAt = null): Reservation
    {
        $startsAt ??= new DateTimeImmutable('2026-09-21 10:00:00');

        return new Reservation(
            id: 'res-1',
            roomId: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            startsAt: $startsAt,
            endsAt: $startsAt->modify('+30 minutes'),
            participants: 2,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
