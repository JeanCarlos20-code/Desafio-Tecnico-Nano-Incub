<?php

namespace App\Modules\Reservation\Application;

interface Transaction
{
    public function run(callable $callback): mixed;
}
