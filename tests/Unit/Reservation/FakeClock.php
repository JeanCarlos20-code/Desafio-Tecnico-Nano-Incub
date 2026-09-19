<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Domain\Clock;
use DateTimeImmutable;

final class FakeClock implements Clock
{
    public function __construct(public DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
