<?php

namespace App\Modules\Reservation\Infra\Persistence;

use App\Modules\Reservation\Application\Transaction;
use Illuminate\Support\Facades\DB;

final class LaravelTransaction implements Transaction
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
