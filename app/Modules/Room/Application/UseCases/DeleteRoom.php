<?php

namespace App\Modules\Room\Application\UseCases;

use App\Modules\Reservation\Application\Transaction;
use App\Modules\Reservation\Domain\Clock;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Domain\Repositories\RoomRepository;

final class DeleteRoom
{
    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly ReservationRepository $reservations,
        private readonly Clock $clock,
        private readonly Transaction $transaction,
    ) {}

    public function execute(string $id): void
    {
        $this->transaction->run(function () use ($id): void {
            if ($this->rooms->lockById($id) === null) {
                throw new RoomNotFound;
            }

            $this->reservations->cancelAllActiveByRoom($id, $this->clock->now());
            $this->rooms->delete($id);
        });
    }
}
