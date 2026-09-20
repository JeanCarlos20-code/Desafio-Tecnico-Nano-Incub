<?php

namespace App\Modules\Reservation\Infra\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservation\Application\UseCases\ListReservations;
use App\Modules\Reservation\Domain\Entities\OccupancyRoom;
use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Reservation\Domain\OccupancyRoomCatalog;
use App\Modules\Reservation\Infra\Http\Requests\IndexReservationRequest;
use DateTimeZone;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class IndexReservationController extends Controller
{
    public function __invoke(
        IndexReservationRequest $request,
        ListReservations $listReservations,
        OccupancyRoomCatalog $rooms,
    ): Response {
        $timezone = (string) config('app.timezone');
        $period = $request->validated('period') ?? 'all';
        $startsOn = $request->validated('starts_on');
        $endsOn = $request->validated('ends_on');
        $roomId = $request->validated('room_id');
        $roomId = $roomId !== null ? (string) $roomId : null;
        $page = max(1, (int) $request->query('page', 1));

        $result = $listReservations->execute($page, 15, $roomId, $period, $startsOn, $endsOn, $timezone);
        $tz = new DateTimeZone($timezone);
        $timeFormat = $this->isSingleDayWindow($period, $startsOn, $endsOn) ? 'H:i' : 'd/m/Y H:i';

        $items = array_map(
            fn (Reservation $reservation): array => $this->toListItem($reservation, $tz, $timeFormat),
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

        return Inertia::render('Reservation/Index', [
            'reservations' => $paginator->toArray(),
            'filters' => [
                'room_id' => $roomId,
                'period' => $period,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ],
            'filterRooms' => array_map(
                fn (OccupancyRoom $room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                ],
                $rooms->listFilterOptions(),
            ),
            'hasAny' => $result['hasAny'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Reservation $reservation, DateTimeZone $timezone, string $timeFormat): array
    {
        $startsAt = $reservation->startsAt->setTimezone($timezone);
        $endsAt = $reservation->endsAt->setTimezone($timezone);

        return [
            'id' => $reservation->id,
            'room_id' => $reservation->roomId,
            'room_name' => $reservation->roomName,
            'responsible' => $reservation->responsible,
            'title' => $reservation->title,
            'date' => $startsAt->format('d/m/Y'),
            'starts_at' => $startsAt->format($timeFormat),
            'ends_at' => $endsAt->format($timeFormat),
            'participants' => $reservation->participants,
            'status' => 'active',
            'status_label' => 'Ativa',
        ];
    }

    private function isSingleDayWindow(string $period, ?string $startsOn, ?string $endsOn): bool
    {
        if ($startsOn !== null && $endsOn !== null) {
            return $startsOn === $endsOn;
        }

        return $period === 'today' || $period === 'tomorrow';
    }
}
