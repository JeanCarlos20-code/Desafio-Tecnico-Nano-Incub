<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class InactiveRoom extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não é possível reservar uma sala inativa.');
    }
}
