<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\Errors\CapacityExceeded;
use App\Modules\Reservation\Application\Errors\InactiveRoom;
use App\Modules\Reservation\Application\Errors\InvalidDuration;
use App\Modules\Reservation\Application\Errors\OccupancyRoomNotFound;
use App\Modules\Reservation\Application\Errors\ReservationOverlap;
use App\Modules\Reservation\Application\Errors\StartsInPast;
use App\Modules\Reservation\Application\UseCases\CreateReservation;
use App\Modules\Reservation\Domain\Entities\OccupancyRoom;
use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CreateReservationTest extends TestCase
{
    public function test_it_persists_a_valid_future_30_minute_booking_with_cancelled_at_null(): void
    {
        [$useCase, $reservations] = $this->makeUseCase();

        $startsAt = new DateTimeImmutable('2026-09-21 10:00:00');
        $endsAt = new DateTimeImmutable('2026-09-21 10:30:00');

        $created = $useCase->execute(
            'room-1',
            '  Ada Lovelace  ',
            '  Daily  ',
            $startsAt,
            $endsAt,
            4,
        );

        $this->assertSame('018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c21', $created->id);
        $this->assertSame('room-1', $created->roomId);
        $this->assertSame('Ada Lovelace', $created->responsible);
        $this->assertSame('Daily', $created->title);
        $this->assertEquals($startsAt, $created->startsAt);
        $this->assertEquals($endsAt, $created->endsAt);
        $this->assertSame(4, $created->participants);
        $this->assertNull($created->cancelledAt);
        $this->assertCount(1, $reservations->created);
        $this->assertSame('Ada Lovelace', $reservations->created[0]['responsible']);
        $this->assertSame('Daily', $reservations->created[0]['title']);
    }

    public function test_it_accepts_starts_at_equal_to_clock_now_and_exact_duration_bounds(): void
    {
        $now = new DateTimeImmutable('2026-09-21 09:00:00');

        [$minUseCase] = $this->makeUseCase(now: $now);
        $min = $minUseCase->execute(
            'room-1',
            'Ada',
            'Min',
            $now,
            $now->modify('+30 minutes'),
            1,
        );
        $this->assertNull($min->cancelledAt);

        [$maxUseCase] = $this->makeUseCase(now: $now);
        $max = $maxUseCase->execute(
            'room-1',
            'Ada',
            'Max',
            $now,
            $now->modify('+4 hours'),
            1,
        );
        $this->assertNull($max->cancelledAt);
    }

    public function test_it_rejects_starts_at_before_clock_now(): void
    {
        [$useCase, $reservations] = $this->makeUseCase();

        try {
            $useCase->execute(
                'room-1',
                'Ada',
                'Past',
                new DateTimeImmutable('2026-09-21 08:59:59'),
                new DateTimeImmutable('2026-09-21 09:30:00'),
                2,
            );
            $this->fail('Expected StartsInPast');
        } catch (StartsInPast $exception) {
            $this->assertSame('O horário inicial não pode estar no passado.', $exception->getMessage());
        }

        $this->assertSame([], $reservations->created);
    }

    public function test_it_rejects_duration_below_30_minutes_or_above_4_hours(): void
    {
        [$useCase, $reservations] = $this->makeUseCase();

        try {
            $useCase->execute(
                'room-1',
                'Ada',
                'Short',
                new DateTimeImmutable('2026-09-21 10:00:00'),
                new DateTimeImmutable('2026-09-21 10:29:00'),
                2,
            );
            $this->fail('Expected InvalidDuration for short slot');
        } catch (InvalidDuration $exception) {
            $this->assertSame('A reserva deve durar entre 30 minutos e 4 horas.', $exception->getMessage());
        }

        try {
            $useCase->execute(
                'room-1',
                'Ada',
                'Long',
                new DateTimeImmutable('2026-09-21 10:00:00'),
                new DateTimeImmutable('2026-09-21 14:01:00'),
                2,
            );
            $this->fail('Expected InvalidDuration for long slot');
        } catch (InvalidDuration $exception) {
            $this->assertSame('A reserva deve durar entre 30 minutos e 4 horas.', $exception->getMessage());
        }

        $this->assertSame([], $reservations->created);
    }

    public function test_it_rejects_missing_inactive_or_over_capacity_rooms(): void
    {
        $catalog = new FakeOccupancyRoomCatalog;
        $catalog->seed(new OccupancyRoom('room-inactive', 'Sala Cinza', 8, false));
        $catalog->seed(new OccupancyRoom('room-small', 'Sala Pequena', 2, true));
        $reservations = new FakeReservationRepository;
        $useCase = new CreateReservation(
            $reservations,
            $catalog,
            new FakeClock(new DateTimeImmutable('2026-09-21 09:00:00')),
            new FakeTransaction,
        );

        $startsAt = new DateTimeImmutable('2026-09-21 10:00:00');
        $endsAt = new DateTimeImmutable('2026-09-21 10:30:00');

        try {
            $useCase->execute('missing', 'Ada', 'Daily', $startsAt, $endsAt, 2);
            $this->fail('Expected OccupancyRoomNotFound');
        } catch (OccupancyRoomNotFound $exception) {
            $this->assertSame('Sala não encontrada.', $exception->getMessage());
        }

        try {
            $useCase->execute('room-inactive', 'Ada', 'Daily', $startsAt, $endsAt, 2);
            $this->fail('Expected InactiveRoom');
        } catch (InactiveRoom $exception) {
            $this->assertSame('Não é possível reservar uma sala inativa.', $exception->getMessage());
        }

        try {
            $useCase->execute('room-small', 'Ada', 'Daily', $startsAt, $endsAt, 3);
            $this->fail('Expected CapacityExceeded');
        } catch (CapacityExceeded $exception) {
            $this->assertSame('O número de participantes excede a capacidade da sala.', $exception->getMessage());
        }

        $this->assertSame(['missing', 'room-inactive', 'room-small'], $catalog->locked);
        $this->assertSame([], $reservations->created);
    }

    public function test_it_rejects_active_overlap_and_accepts_consecutive_ends_at_equals_starts_at(): void
    {
        [$useCase, $reservations] = $this->makeUseCase();
        $reservations->seed($this->reservation(
            'existing',
            'room-1',
            new DateTimeImmutable('2026-09-21 10:00:00'),
            new DateTimeImmutable('2026-09-21 10:30:00'),
        ));

        try {
            $useCase->execute(
                'room-1',
                'Ada',
                'Overlap',
                new DateTimeImmutable('2026-09-21 10:15:00'),
                new DateTimeImmutable('2026-09-21 10:45:00'),
                2,
            );
            $this->fail('Expected ReservationOverlap');
        } catch (ReservationOverlap $exception) {
            $this->assertSame('Já existe uma reserva ativa neste horário para a sala.', $exception->getMessage());
        }

        $consecutive = $useCase->execute(
            'room-1',
            'Ada',
            'Next',
            new DateTimeImmutable('2026-09-21 10:30:00'),
            new DateTimeImmutable('2026-09-21 11:00:00'),
            2,
        );

        $this->assertSame('Next', $consecutive->title);
        $this->assertCount(1, $reservations->created);
    }

    public function test_it_ignores_canceled_rows_when_checking_overlap(): void
    {
        [$useCase, $reservations] = $this->makeUseCase();
        $reservations->seed($this->reservation(
            'canceled',
            'room-1',
            new DateTimeImmutable('2026-09-21 10:00:00'),
            new DateTimeImmutable('2026-09-21 10:30:00'),
            new DateTimeImmutable('2026-09-21 09:05:00'),
        ));

        $created = $useCase->execute(
            'room-1',
            'Ada',
            'Reuse',
            new DateTimeImmutable('2026-09-21 10:00:00'),
            new DateTimeImmutable('2026-09-21 10:30:00'),
            2,
        );

        $this->assertSame('Reuse', $created->title);
        $this->assertCount(1, $reservations->created);
    }

    /**
     * @return array{0: CreateReservation, 1: FakeReservationRepository}
     */
    private function makeUseCase(?DateTimeImmutable $now = null): array
    {
        $catalog = new FakeOccupancyRoomCatalog;
        $catalog->seed(new OccupancyRoom('room-1', 'Sala Azul', 8, true));
        $reservations = new FakeReservationRepository;
        $useCase = new CreateReservation(
            $reservations,
            $catalog,
            new FakeClock($now ?? new DateTimeImmutable('2026-09-21 09:00:00')),
            new FakeTransaction,
        );

        return [$useCase, $reservations];
    }

    private function reservation(
        string $id,
        string $roomId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        ?DateTimeImmutable $cancelledAt = null,
    ): Reservation {
        return new Reservation(
            id: $id,
            roomId: $roomId,
            responsible: 'Ada',
            title: 'Existing',
            startsAt: $startsAt,
            endsAt: $endsAt,
            participants: 2,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-20 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-20 08:00:00'),
        );
    }
}
