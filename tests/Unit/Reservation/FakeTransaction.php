<?php

namespace Tests\Unit\Reservation;

use App\Modules\Reservation\Application\Transaction;

final class FakeTransaction implements Transaction
{
    public int $runs = 0;

    public function run(callable $callback): mixed
    {
        $this->runs++;

        return $callback();
    }
}
