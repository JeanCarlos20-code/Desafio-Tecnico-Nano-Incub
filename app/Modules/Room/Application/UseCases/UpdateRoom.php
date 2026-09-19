<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Reservation\Application\Transaction;
use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use App\Modules\Room\Application\Errors\DeactivationDecisionRequired;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class UpdateRoom
{
    public const FLASH_UPDATED = 'Sala atualizada com sucesso.';

    public const FLASH_KEEP = 'Sala desativada. As reuniões programadas foram mantidas.';

    public const FLASH_CANCEL = 'Sala desativada. As reuniões futuras foram canceladas.';

    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly ReservationRepository $reservations,
        private readonly Clock $clock,
        private readonly Transaction $transaction,
    ) {}

    /**
     * @return array{room: Room, flash: string}
     */
    public function execute(
        string $id,
        string $name,
        int $capacity,
        bool $isActive,
        ?string $scheduledMeetingsAction = null,
    ): array {
        return $this->transaction->run(function () use ($id, $name, $capacity, $isActive, $scheduledMeetingsAction): array {
            $existing = $this->rooms->lockById($id);

            if ($existing === null) {
                throw new RoomNotFound;
            }

            $flash = self::FLASH_UPDATED;
            $deactivating = $existing->isActive && $isActive === false;

            if ($deactivating) {
                $now = $this->clock->now();
                $futureActiveCount = $this->reservations->countActiveFutureByRoom($id, $now);

                if ($futureActiveCount > 0) {
                    if ($scheduledMeetingsAction !== 'keep' && $scheduledMeetingsAction !== 'cancel') {
                        throw new DeactivationDecisionRequired($futureActiveCount);
                    }

                    if ($scheduledMeetingsAction === 'cancel') {
                        $this->reservations->cancelActiveFutureByRoom($id, $now, $now);
                        $flash = self::FLASH_CANCEL;
                    } else {
                        $flash = self::FLASH_KEEP;
                    }
                }
            }

            return [
                'room' => $this->rooms->update($id, $name, $capacity, $isActive),
                'flash' => $flash,
            ];
        });
    }
}
