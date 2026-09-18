<?php

namespace App\Modules\Room\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Room\Application\UseCases\ListRooms;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class IndexRoomController extends Controller
{
    public function __invoke(Request $request, ListRooms $listRooms): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $result = $listRooms->execute($page, 15);

        $items = array_map(
            fn (Room $room): array => $this->toListItem($room),
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
        ]);
    }

    /**
     * @return array{id: string, name: string, capacity: int, is_active: bool, status: string, created_at: ?string}
     */
    private function toListItem(Room $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'is_active' => $room->isActive,
            'status' => $room->isActive ? 'Ativa' : 'Inativa',
            'created_at' => $this->formatDate($room->createdAt),
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
