# Validation

## Acceptance Criteria

| AC | Spec outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 | Do not persist room or reservations when a future active on that room has `participants` > C | `tests/Unit/Room/UpdateRoomTest.php:176-183` — `$this->assertSame([], $rooms->updated)` and `$this->assertSame(10, $rooms->rooms[...]->capacity)`; `tests/Feature/Room/RoomUpdateHttpTest.php:200-210` — `assertDatabaseHas` keeps name, capacity 10, `is_active` 1, reservation `participants` 10 and `cancelled_at` null | Yes |
| AC-002 | Singular `capacity` message for exactly one conflicting meeting | `tests/Unit/Room/UpdateRoomTest.php:205-208` — `$this->assertSame('Não é possível reduzir a capacidade. Existe 1 reunião marcada com mais participantes do que a nova capacidade. Altere essa reunião primeiro e depois volte.', $exception->getMessage())`; `tests/Feature/Room/RoomUpdateHttpTest.php:196-198` — `assertSessionHasErrors(['capacity' => '...Existe 1 reunião...'])` | Yes |
| AC-003 | Plural `capacity` message for n > 1 | `tests/Unit/Room/UpdateRoomTest.php:229-232` — `$this->assertSame('...Existem 2 reuniões marcadas...', $exception->getMessage())`; `tests/Feature/Room/RoomUpdateHttpTest.php:244-246` — same sentence on session `capacity` | Yes |
| AC-004 | Persist when every future active has `participants` <= C | `tests/Unit/Room/UpdateRoomTest.php:252-254` — `$this->assertSame(8, $result['room']->capacity)` and `$this->assertSame(8, $rooms->updated[0]['capacity'])`; `tests/Feature/Room/RoomUpdateHttpTest.php:289-296` — redirect + flash + `capacity` 8 | Yes |
| AC-005 | Do not apply AC-001 when proposed capacity >= current | `tests/Unit/Room/UpdateRoomTest.php:273-283` — `$this->assertSame(10, $same['room']->capacity)` and `$this->assertSame(12, $increase['room']->capacity)` with a 10-participant future | Yes |
| AC-006 | Ignore past, in-progress, canceled, and other-room over-capacity rows | `tests/Unit/Room/UpdateRoomTest.php:318-324` — `$this->assertSame(8, $result['room']->capacity)` while those four reservations stay uncancelled | Yes |
| AC-007 | Capacity error beats deactivate-decision payload | `tests/Unit/Room/UpdateRoomTest.php:344-351` — catch `CapacityReductionBlocked`, `$this->assertSame([], $rooms->updated)`; `tests/Feature/Room/RoomUpdateHttpTest.php:329-332` — session `capacity` message and `assertSessionDoesntHaveErrors(['scheduled_meetings_action', 'future_active_count'])` | Yes |
| AC-008 | Evaluate after `lockById`; write nothing on failure | `tests/Unit/Room/UpdateRoomTest.php:371-373` — `$this->assertSame(['018f2b5c-6a7f-7b12-9d6f-2f8a4e0c9c11'], $rooms->locked)` and `$this->assertSame([], $rooms->updated)` | Yes |
| AC-009 | Room/Edit shows the AC-002 sentence on Capacidade and stays on the form | `resources/js/Pages/Room/Edit.test.jsx:178-182` — `expect(screen.getByText(sentence)).toHaveAttribute('id', 'capacity-error')` and `getByRole('heading', { name: 'Editar sala' })` | Yes |
| AC-010 | No reservation edit, no silent participant shrink, no cancel as a side effect | `tests/Unit/Room/UpdateRoomTest.php:179-180` and `:395-397` — participants stay 10, `$this->assertSame([], $reservations->canceled)`; Feature rows keep `cancelled_at` null. No `reservations.update` route added. | Yes |

Edge cases: two futures with only one over C uses the singular sentence (`UpdateRoomTest.php:204-208`). Capacity drop plus `cancel` still blocks (`UpdateRoomTest.php:394-398`).

## Test Results

Worker-run checks (harness re-runs the required gates after complete-phase):

- `php artisan test --testsuite=Unit --filter=UpdateRoom`: 18 passed (84 assertions)
- `php artisan test --testsuite=Unit --coverage --min=80`: 58 passed (247 assertions), exit 0
- `php artisan test --testsuite=Feature --filter=RoomUpdateHttp`: 10 passed (74 assertions)
- `php artisan test --testsuite=Feature`: 117 passed (1062 assertions)
- `npx vitest run resources/js/Pages/Room/Edit.test.jsx`: 7 passed
- `npm run test:coverage`: 96 passed; statements 96.17%
- `vendor/bin/pint --test`: passed
- `npm run lint`: passed
- `composer run build`: passed
- `npm run build`: passed

`php -m` in this worktree did not list `pcov` or `xdebug`. The unit command exited 0 with `--coverage --min=80`, but this worker did not see a printed coverage table. The harness must collect Application coverage itself.

## Required Gates

Declared in `tasks.md` `harness.gates`. This worker ran the same commands as a pre-check. Do not treat that as the harness final gate.

## Review Result

Not run. Execute only. Review follows complete-phase.

## Final Status

Implementation of T1–T6 is ready for harness checks.

Behavior: after `lockById`, a lower capacity is refused when this room has future actives with `participants` greater than the new number. `CapacityReductionBlocked` maps to the `capacity` field. Increase, same capacity, and fitting futures persist. The deactivate dialog is not shown when this conflict applies. Room/Edit already rendered `errors.capacity`; a Vitest now asserts the singular sentence. Screen copy documents both messages.

Extra files beyond the task file list: none required by a surprise dependency. `CapacityReductionBlocked.php` is the planned error class. `validation.md` is required by the execute packet.

No product commit. No reservation edit. No Playwright.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test --testsuite=Unit --coverage --min=80` — exit=0 (required)
- ✅ `npm run test:coverage` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run lint` — exit=0 (required)
- ✅ `composer run build` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
