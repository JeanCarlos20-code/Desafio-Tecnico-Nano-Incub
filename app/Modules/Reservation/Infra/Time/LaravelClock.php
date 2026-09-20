<?php

namespace App\Modules\Reservation\Infra\Time;

use App\Modules\Reservation\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

final class LaravelClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface(Date::now());
    }
}
