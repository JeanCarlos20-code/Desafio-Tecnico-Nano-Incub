<?php

namespace App\Modules\Reservation\Application\Errors;

use RuntimeException;

final class StartsInPast extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A data não pode estar no passado.');
    }
}
