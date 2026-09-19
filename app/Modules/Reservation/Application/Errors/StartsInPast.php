<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class StartsInPast extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O horário inicial não pode estar no passado.');
    }
}
