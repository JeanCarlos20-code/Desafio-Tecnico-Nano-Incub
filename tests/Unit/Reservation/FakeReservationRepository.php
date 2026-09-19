<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use DateTimeImmutable;

final class FakeReservationRepository implements ReservationRepository
{
    /** @var array<string, Reservation> */
    public array $reservations = [];

    /** @var list<array{roomId: string, responsible: string, title: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, participants: int}> */
    public array $created = [];

    /** @var list<array{page: int, perPage: int, roomId: ?string, dayStart: DateTimeImmutable, dayEndExclusive: DateTimeImmutable}> */
    public array $listed = [];

    /** @var list<array{id: string, cancelledAt: DateTimeImmutable}> */
    public array $canceled = [];

    public function seed(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function create(
        string $roomId,
        string $responsible,
        string $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        int $participants,
    ): Reservation {
        $this->created[] = compact('roomId', 'responsible', 'title', 'startsAt', 'endsAt', 'participants');

        $reservation = new Reservation(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c21',
            roomId: $roomId,
            responsible: $responsible,
            title: $title,
            startsAt: $startsAt,
            endsAt: $endsAt,
            participants: $participants,
            cancelledAt: null,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );

        $this->reservations[$reservation->id] = $reservation;

        return $reservation;
    }

    public function hasActiveOverlap(string $roomId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): bool
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->roomId !== $roomId || $reservation->cancelledAt !== null) {
                continue;
            }

            if ($reservation->startsAt < $endsAt && $reservation->endsAt > $startsAt) {
                return true;
            }
        }

        return false;
    }

    public function listPage(
        int $page,
        int $perPage,
        ?string $roomId,
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEndExclusive,
    ): array {
        $this->listed[] = compact('page', 'perPage', 'roomId', 'dayStart', 'dayEndExclusive');

        $items = array_values(array_filter(
            $this->reservations,
            fn (Reservation $reservation): bool => $reservation->cancelledAt === null,
        ));

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => count($items),
        ];
    }

    public function hasAny(): bool
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->cancelledAt === null) {
                return true;
            }
        }

        return false;
    }

    public function findById(string $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }

    public function markCanceled(string $id, DateTimeImmutable $cancelledAt): void
    {
        $this->canceled[] = compact('id', 'cancelledAt');

        $existing = $this->reservations[$id];
        $this->reservations[$id] = new Reservation(
            id: $existing->id,
            roomId: $existing->roomId,
            responsible: $existing->responsible,
            title: $existing->title,
            startsAt: $existing->startsAt,
            endsAt: $existing->endsAt,
            participants: $existing->participants,
            cancelledAt: $cancelledAt,
            createdAt: $existing->createdAt,
            updatedAt: $cancelledAt,
            roomName: $existing->roomName,
        );
    }

    public function countActiveFutureByRoom(string $roomId, DateTimeImmutable $now): int
    {
        $count = 0;

        foreach ($this->reservations as $reservation) {
            if ($reservation->roomId === $roomId
                && $reservation->cancelledAt === null
                && $reservation->startsAt > $now
            ) {
                $count++;
            }
        }

        return $count;
    }

    public function countByRoomIds(array $ids): array
    {
        $counts = [];

        foreach ($ids as $id) {
            $counts[$id] = 0;
        }

        foreach ($this->reservations as $reservation) {
            if (! array_key_exists($reservation->roomId, $counts)) {
                continue;
            }

            $counts[$reservation->roomId]++;
        }

        return $counts;
    }

    public function cancelActiveFutureByRoom(string $roomId, DateTimeImmutable $now, DateTimeImmutable $cancelledAt): void
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->roomId !== $roomId
                || $reservation->cancelledAt !== null
                || $reservation->startsAt <= $now
            ) {
                continue;
            }

            $this->markCanceled($reservation->id, $cancelledAt);
        }
    }

    public function cancelAllActiveByRoom(string $roomId, DateTimeImmutable $cancelledAt): void
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->roomId !== $roomId || $reservation->cancelledAt !== null) {
                continue;
            }

            $this->markCanceled($reservation->id, $cancelledAt);
        }
    }
}
