<?php

namespace App\Modules\Reservation\Domain\Repositories;

use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;

interface ReservationRepository
{
    public function create(
        string $roomId,
        string $responsible,
        string $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        int $participants,
    ): Reservation;

    public function hasActiveOverlap(string $roomId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): bool;

    /**
     * @return array{items: list<Reservation>, total: int}
     */
    public function listPage(
        int $page,
        int $perPage,
        ?string $roomId,
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEndExclusive,
    ): array;

    public function hasAny(): bool;

    public function findById(string $id): ?Reservation;

    public function markCanceled(string $id, DateTimeImmutable $cancelledAt): void;

    public function countActiveFutureByRoom(string $roomId, DateTimeImmutable $now): int;

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    public function countByRoomIds(array $ids): array;

    public function cancelActiveFutureByRoom(string $roomId, DateTimeImmutable $now, DateTimeImmutable $cancelledAt): void;

    public function cancelAllActiveByRoom(string $roomId, DateTimeImmutable $cancelledAt): void;
}
