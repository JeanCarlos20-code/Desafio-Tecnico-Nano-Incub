<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Domain\Repositories\ReservationRepository;
use App\Modules\Room\Application\UseCases\ListRooms;
use App\Modules\Room\Domain\Entities\Room;
use App\Modules\Room\Infra\Http\Requests\IndexRoomRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class IndexRoomController extends Controller
{
    public function __invoke(IndexRoomRequest $request, ListRooms $listRooms, ReservationRepository $reservations): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $status = $request->validated('status') ?? 'all';
        $result = $listRooms->execute($page, 15, $status);
        $counts = $reservations->countByRoomIds(array_map(
            fn (Room $room): string => $room->id,
            $result['items'],
        ));

        $items = array_map(
            fn (Room $room): array => $this->toListItem($room, (int) ($counts[$room->id] ?? 0) > 0),
            $result['items'],
        );

        $paginator = (new LengthAwarePaginator(
            $items,
            $result['total'],
            15,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        ))->withQueryString();

        return Inertia::render('Room/Index', [
            'rooms' => $paginator->toArray(),
            'filters' => [
                'status' => $status,
            ],
            'hasAny' => $result['hasAny'],
        ]);
    }

    /**
     * @return array{id: string, name: string, capacity: int, is_active: bool, status: string, created_at: ?string, has_reservations: bool}
     */
    private function toListItem(Room $room, bool $hasReservations): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'is_active' => $room->isActive,
            'status' => $room->isActive ? 'Ativa' : 'Inativa',
            'created_at' => $this->formatDate($room->createdAt),
            'has_reservations' => $hasReservations,
        ];
    }

    private function formatDate(?DateTimeImmutable $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date
            ->setTimezone(new DateTimeZone((string) config('app.timezone')))
            ->format('d/m/Y');
    }
}
