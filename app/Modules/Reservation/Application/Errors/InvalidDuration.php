<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class InvalidDuration extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A reserva deve durar entre 30 minutos e 4 horas.');
    }
}
