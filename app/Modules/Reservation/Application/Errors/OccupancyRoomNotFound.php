<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class OccupancyRoomNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sala não encontrada.');
    }
}
