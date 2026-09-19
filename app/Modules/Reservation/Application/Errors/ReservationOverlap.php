<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class ReservationOverlap extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Já existe uma reserva ativa neste horário para a sala.');
    }
}
