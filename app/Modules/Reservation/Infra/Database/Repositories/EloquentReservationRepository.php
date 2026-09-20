<?php

namespace App\Modules\Reservation\Infra\Database\Repositories;

use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use App\Modules\Reservation\Infra\Database\Models\Reservation as ReservationModel;
use DateTimeImmutable;

final class EloquentReservationRepository implements ReservationRepository
{
    public function create(
        string $roomId,
        string $responsible,
        string $title,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        int $participants,
    ): Reservation {
        $model = ReservationModel::query()->create([
            'room_id' => $roomId,
            'responsible' => $responsible,
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participants' => $participants,
            'cancelled_at' => null,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function hasActiveOverlap(string $roomId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): bool
    {
        return ReservationModel::query()
            ->where('room_id', $roomId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    public function listPage(
        int $page,
        int $perPage,
        ?string $roomId,
        ?DateTimeImmutable $rangeStart,
        ?DateTimeImmutable $rangeEndExclusive,
    ): array {
        $query = ReservationModel::query()
            ->with('room')
            ->whereNull('cancelled_at')
            ->when(
                $rangeStart !== null && $rangeEndExclusive !== null,
                fn ($builder) => $builder
                    ->where('starts_at', '>=', $rangeStart)
                    ->where('starts_at', '<', $rangeEndExclusive),
            )
            ->when($roomId, fn ($builder) => $builder->where('room_id', $roomId))
            ->orderBy('starts_at')
            ->orderBy('id');

        $total = (clone $query)->count();
        $models = $query->forPage($page, $perPage)->get();

        return [
            'items' => $models->map(fn (ReservationModel $model): Reservation => $this->toDomain($model))->all(),
            'total' => $total,
        ];
    }

    public function hasAny(): bool
    {
        return ReservationModel::query()->whereNull('cancelled_at')->exists();
    }

    public function findById(string $id): ?Reservation
    {
        $model = ReservationModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function markCanceled(string $id, DateTimeImmutable $cancelledAt): void
    {
        ReservationModel::query()->whereKey($id)->whereNull('cancelled_at')->update([
            'cancelled_at' => $cancelledAt,
        ]);
    }

    public function countActiveFutureByRoom(string $roomId, DateTimeImmutable $now): int
    {
        return ReservationModel::query()
            ->where('room_id', $roomId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', $now)
            ->count();
    }

    public function countByRoomIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ReservationModel::query()
            ->whereIn('room_id', $ids)
            ->selectRaw('room_id, count(*) as aggregate')
            ->groupBy('room_id')
            ->pluck('aggregate', 'room_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function cancelActiveFutureByRoom(string $roomId, DateTimeImmutable $now, DateTimeImmutable $cancelledAt): void
    {
        ReservationModel::query()
            ->where('room_id', $roomId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', $now)
            ->update(['cancelled_at' => $cancelledAt]);
    }

    public function cancelAllActiveByRoom(string $roomId, DateTimeImmutable $cancelledAt): void
    {
        ReservationModel::query()
            ->where('room_id', $roomId)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => $cancelledAt]);
    }

    private function toDomain(ReservationModel $model): Reservation
    {
        return new Reservation(
            id: (string) $model->getKey(),
            roomId: (string) $model->room_id,
            responsible: $model->responsible,
            title: $model->title,
            startsAt: self::immutable($model->starts_at) ?? new DateTimeImmutable,
            endsAt: self::immutable($model->ends_at) ?? new DateTimeImmutable,
            participants: (int) $model->participants,
            cancelledAt: self::immutable($model->cancelled_at),
            createdAt: self::immutable($model->created_at),
            updatedAt: self::immutable($model->updated_at),
            roomName: $model->relationLoaded('room') && $model->room !== null
                ? (string) $model->room->name
                : '',
        );
    }

    private static function immutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
