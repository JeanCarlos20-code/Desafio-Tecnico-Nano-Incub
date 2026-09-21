<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Domain\Entities\Reservation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReservationListStatusTest extends TestCase
{
    public function test_list_status_returns_cancelled_when_cancelled_at_is_set_including_when_ends_at_is_past(): void
    {
        $now = new DateTimeImmutable('2026-09-21 12:00:00');

        $cancelledPast = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 09:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 09:30:00'),
            cancelledAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
        $cancelledFuture = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 13:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 13:30:00'),
            cancelledAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );

        $this->assertSame('cancelled', $cancelledPast->listStatus($now));
        $this->assertSame('cancelled', $cancelledFuture->listStatus($now));
    }

    public function test_list_status_returns_passed_when_cancelled_at_is_null_and_ends_at_is_before_now(): void
    {
        $now = new DateTimeImmutable('2026-09-21 12:00:00');
        $reservation = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 09:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 09:30:00'),
            cancelledAt: null,
        );

        $this->assertSame('passed', $reservation->listStatus($now));
    }

    public function test_list_status_returns_active_when_cancelled_at_is_null_and_ends_at_is_not_before_now(): void
    {
        $now = new DateTimeImmutable('2026-09-21 12:00:00');

        $future = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 13:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 13:30:00'),
            cancelledAt: null,
        );
        $inProgress = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 11:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 13:00:00'),
            cancelledAt: null,
        );
        $endingNow = $this->reservation(
            startsAt: new DateTimeImmutable('2026-09-21 11:00:00'),
            endsAt: new DateTimeImmutable('2026-09-21 12:00:00'),
            cancelledAt: null,
        );

        $this->assertSame('active', $future->listStatus($now));
        $this->assertSame('active', $inProgress->listStatus($now));
        $this->assertSame('active', $endingNow->listStatus($now));
    }

    private function reservation(
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        ?DateTimeImmutable $cancelledAt,
    ): Reservation {
        return new Reservation(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c21',
            roomId: 'room-1',
            responsible: 'Ada',
            title: 'Daily',
            startsAt: $startsAt,
            endsAt: $endsAt,
            participants: 4,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
