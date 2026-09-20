<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class CapacityExceeded extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O número de participantes excede a capacidade da sala.');
    }
}
