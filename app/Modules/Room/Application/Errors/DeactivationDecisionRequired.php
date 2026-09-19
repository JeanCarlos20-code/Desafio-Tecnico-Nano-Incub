<?php

namespace App\Modules\Room\Application\Errors;

use RuntimeException;

final class DeactivationDecisionRequired extends RuntimeException
{
    public function __construct(public readonly int $futureActiveCount)
    {
        parent::__construct('A deactivation decision is required for scheduled meetings.');
    }
}
