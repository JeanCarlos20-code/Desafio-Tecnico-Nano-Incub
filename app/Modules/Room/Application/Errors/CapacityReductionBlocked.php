<?php

namespace App\Modules\Room\Application\Errors;

use RuntimeException;

final class CapacityReductionBlocked extends RuntimeException
{
    public function __construct(public readonly int $conflictingCount)
    {
        parent::__construct(
            $conflictingCount === 1
                ? 'Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.'
                : "Não é possível reduzir a capacidade. Existem {$conflictingCount} reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte."
        );
    }
}
