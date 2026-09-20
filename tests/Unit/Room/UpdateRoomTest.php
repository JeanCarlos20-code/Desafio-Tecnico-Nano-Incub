<?php

namespace Tests\Unit\Room;

use App\Modules\Reservation\Domain\Entities\Reservation;
use App\Modules\Room\Application\Errors\CapacityReductionBlocked;
use App\Modules\Room\Application\Errors\DeactivationDecisionRequired;
use App\Modules\Room\Application\Errors\RoomNotFound;
use App\Modules\Room\Application\UseCases\UpdateRoom;
use App\Modules\Room\Domain\Entities\Room;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Reservation\FakeClock;
use Tests\Unit\Reservation\FakeReservationRepository;
use Tests\Unit\Reservation\FakeTransaction;

class UpdateRoomTest extends TestCase
{
    public function test_it_deactivates_with_keep_and_leaves_future_actives_unchanged(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));

        $result = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
            'keep',
        );

        $this->assertFalse($result['room']->isActive);
        $this->assertSame(UpdateRoom::FLASH_KEEP, $result['flash']);
        $this->assertNull($reservations->reservations['future']->cancelledAt);
        $this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->locked);
        $this->assertSame([], $reservations->canceled);
    }

    public function test_it_deactivates_with_cancel_and_sets_cancelled_at_only_on_future_actives(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $now = new DateTimeImmutable('2026-09-21 12:00:00');
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));
        $reservations->seed($this->reservation('in-progress', '2026-09-21 11:00:00', '2026-09-21 13:00:00'));

        $result = $this->useCase($rooms, $reservations, $now)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
            'cancel',
        );

        $this->assertFalse($result['room']->isActive);
        $this->assertSame(UpdateRoom::FLASH_CANCEL, $result['flash']);
        $this->assertEquals($now, $reservations->reservations['future']->cancelledAt);
        $this->assertNull($reservations->reservations['in-progress']->cancelledAt);
    }

    public function test_it_throws_deactivation_decision_required_and_writes_nothing_when_futures_exist_without_action(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                20,
                false,
                null,
            );
            $this->fail('Expected DeactivationDecisionRequired');
        } catch (DeactivationDecisionRequired $exception) {
            $this->assertSame(1, $exception->futureActiveCount);
            $this->assertSame([], $rooms->updated);
            $this->assertSame([], $reservations->canceled);
            $this->assertNull($reservations->reservations['future']->cancelledAt);
        }
    }

    public function test_it_deactivates_without_a_dialog_path_when_no_future_active_exists(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('in-progress', '2026-09-21 11:00:00', '2026-09-21 13:00:00'));

        $result = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
            null,
        );

        $this->assertFalse($result['room']->isActive);
        $this->assertSame(UpdateRoom::FLASH_UPDATED, $result['flash']);
        $this->assertNull($reservations->reservations['in-progress']->cancelledAt);
        $this->assertSame([], $reservations->canceled);
    }

    public function test_it_cancel_leaves_past_in_progress_and_already_canceled_reservations_unchanged(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $alreadyCanceledAt = new DateTimeImmutable('2026-09-20 09:00:00');
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));
        $reservations->seed($this->reservation('past', '2026-09-20 10:00:00', '2026-09-20 10:30:00'));
        $reservations->seed($this->reservation('in-progress', '2026-09-21 11:00:00', '2026-09-21 13:00:00'));
        $reservations->seed($this->reservation(
            'already',
            '2026-09-23 10:00:00',
            '2026-09-23 10:30:00',
            $alreadyCanceledAt,
        ));

        $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
            'cancel',
        );

        $this->assertNotNull($reservations->reservations['future']->cancelledAt);
        $this->assertNull($reservations->reservations['past']->cancelledAt);
        $this->assertNull($reservations->reservations['in-progress']->cancelledAt);
        $this->assertEquals($alreadyCanceledAt, $reservations->reservations['already']->cancelledAt);
    }

    public function test_it_ignores_action_when_the_room_is_already_inactive(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: false));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00'));

        $result = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            20,
            false,
            'cancel',
        );

        $this->assertFalse($result['room']->isActive);
        $this->assertSame(UpdateRoom::FLASH_UPDATED, $result['flash']);
        $this->assertNull($reservations->reservations['future']->cancelledAt);
        $this->assertSame([], $reservations->canceled);
    }

    public function test_it_throws_capacity_reduction_blocked_and_writes_nothing_when_a_future_active_exceeds_the_new_capacity(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                true,
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked $exception) {
            $this->assertSame(1, $exception->conflictingCount);
            $this->assertSame([], $rooms->updated);
            $this->assertSame([], $reservations->canceled);
            $this->assertSame(10, $reservations->reservations['future']->participants);
            $this->assertNull($reservations->reservations['future']->cancelledAt);
            $this->assertSame(10, $rooms->rooms['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11']->capacity);
            $this->assertSame('Sala Azul', $rooms->rooms['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11']->name);
            $this->assertTrue($rooms->rooms['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11']->isActive);
        }
    }

    public function test_it_uses_the_singular_capacity_message_when_exactly_one_future_meeting_exceeds(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('over', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));
        $reservations->seed($this->reservation('fits', '2026-09-23 10:00:00', '2026-09-23 10:30:00', participants: 8));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                true,
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked $exception) {
            $this->assertSame(1, $exception->conflictingCount);
            $this->assertSame(
                'Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_uses_the_plural_capacity_message_when_two_future_meetings_exceed(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('first', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));
        $reservations->seed($this->reservation('second', '2026-09-23 10:00:00', '2026-09-23 10:30:00', participants: 9));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                true,
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked $exception) {
            $this->assertSame(2, $exception->conflictingCount);
            $this->assertSame(
                'Não é possível reduzir a capacidade. Existem 2 reuniões marcadas com mais participantes do que a nova capacidade. Altere essas reuniões primeiro e depois volte.',
                $exception->getMessage(),
            );
            $this->assertSame([], $rooms->updated);
        }
    }

    public function test_it_persists_a_lower_capacity_when_every_future_active_fits(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 8));

        $result = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            8,
            true,
        );

        $this->assertSame(8, $result['room']->capacity);
        $this->assertSame(UpdateRoom::FLASH_UPDATED, $result['flash']);
        $this->assertSame(8, $rooms->updated[0]['capacity']);
        $this->assertNull($reservations->reservations['future']->cancelledAt);
        $this->assertSame(8, $reservations->reservations['future']->participants);
    }

    public function test_it_persists_an_increase_or_same_capacity_even_if_a_future_meeting_equals_the_current_capacity(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));

        $same = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            10,
            true,
        );

        $this->assertSame(10, $same['room']->capacity);
        $this->assertSame(UpdateRoom::FLASH_UPDATED, $same['flash']);

        $increase = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            12,
            true,
        );

        $this->assertSame(12, $increase['room']->capacity);
        $this->assertSame(10, $reservations->reservations['future']->participants);
        $this->assertNull($reservations->reservations['future']->cancelledAt);
    }

    public function test_it_ignores_past_in_progress_canceled_and_other_room_meetings_when_reducing_capacity(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $alreadyCanceledAt = new DateTimeImmutable('2026-09-20 09:00:00');
        $reservations->seed($this->reservation('past', '2026-09-20 10:00:00', '2026-09-20 10:30:00', participants: 20));
        $reservations->seed($this->reservation('in-progress', '2026-09-21 11:00:00', '2026-09-21 13:00:00', participants: 20));
        $reservations->seed($this->reservation(
            'already',
            '2026-09-23 10:00:00',
            '2026-09-23 10:30:00',
            $alreadyCanceledAt,
            20,
        ));
        $reservations->seed($this->reservation(
            'other-room',
            '2026-09-22 10:00:00',
            '2026-09-22 10:30:00',
            participants: 20,
            roomId: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c22',
        ));

        $result = $this->useCase($rooms, $reservations)->execute(
            '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            'Sala Verde',
            8,
            true,
        );

        $this->assertSame(8, $result['room']->capacity);
        $this->assertSame(UpdateRoom::FLASH_UPDATED, $result['flash']);
        $this->assertNull($reservations->reservations['past']->cancelledAt);
        $this->assertNull($reservations->reservations['in-progress']->cancelledAt);
        $this->assertEquals($alreadyCanceledAt, $reservations->reservations['already']->cancelledAt);
        $this->assertNull($reservations->reservations['other-room']->cancelledAt);
        $this->assertSame(20, $reservations->reservations['other-room']->participants);
    }

    public function test_it_prefers_capacity_reduction_blocked_over_deactivation_decision_required(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                false,
                null,
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked $exception) {
            $this->assertSame(1, $exception->conflictingCount);
            $this->assertSame(
                'Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.',
                $exception->getMessage(),
            );
            $this->assertSame([], $rooms->updated);
            $this->assertSame([], $reservations->canceled);
            $this->assertTrue($rooms->rooms['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11']->isActive);
        }
    }

    public function test_it_evaluates_the_capacity_rule_after_lock_by_id(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                true,
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked) {
            $this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->locked);
            $this->assertSame([], $rooms->updated);
            $this->assertSame([], $reservations->canceled);
        }
    }

    public function test_it_blocks_capacity_drop_with_keep_or_cancel_and_does_not_deactivate(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;
        $rooms->seed($this->room(isActive: true));
        $reservations->seed($this->reservation('future', '2026-09-22 10:00:00', '2026-09-22 10:30:00', participants: 10));

        try {
            $this->useCase($rooms, $reservations)->execute(
                '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
                'Sala Verde',
                8,
                false,
                'cancel',
            );
            $this->fail('Expected CapacityReductionBlocked');
        } catch (CapacityReductionBlocked $exception) {
            $this->assertSame(1, $exception->conflictingCount);
            $this->assertSame([], $rooms->updated);
            $this->assertSame([], $reservations->canceled);
            $this->assertNull($reservations->reservations['future']->cancelledAt);
            $this->assertTrue($rooms->rooms['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11']->isActive);
        }
    }

    public function test_it_throws_room_not_found_when_the_room_is_missing(): void
    {
        $rooms = new FakeRoomRepository;
        $reservations = new FakeReservationRepository;

        try {
            $this->useCase($rooms, $reservations)->execute('missing-id', 'Sala', 8, true);
            $this->fail('Expected RoomNotFound');
        } catch (RoomNotFound) {
            $this->assertSame([], $rooms->updated);
            $this->assertSame(['missing-id'], $rooms->locked);
        }
    }

    private function useCase(
        FakeRoomRepository $rooms,
        FakeReservationRepository $reservations,
        ?DateTimeImmutable $now = null,
    ): UpdateRoom {
        return new UpdateRoom(
            $rooms,
            $reservations,
            new FakeClock($now ?? new DateTimeImmutable('2026-09-21 12:00:00')),
            new FakeTransaction,
        );
    }

    private function room(bool $isActive): Room
    {
        return new Room(
            id: '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
            name: 'Sala Azul',
            capacity: 10,
            isActive: $isActive,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            deletedAt: null,
        );
    }

    private function reservation(
        string $id,
        string $startsAt,
        string $endsAt,
        ?DateTimeImmutable $cancelledAt = null,
        int $participants = 2,
        string $roomId = '018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11',
    ): Reservation {
        return new Reservation(
            id: $id,
            roomId: $roomId,
            responsible: 'Ada',
            title: $id,
            startsAt: new DateTimeImmutable($startsAt),
            endsAt: new DateTimeImmutable($endsAt),
            participants: $participants,
            cancelledAt: $cancelledAt,
            createdAt: new DateTimeImmutable('2026-09-21 08:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-21 08:00:00'),
        );
    }
}
