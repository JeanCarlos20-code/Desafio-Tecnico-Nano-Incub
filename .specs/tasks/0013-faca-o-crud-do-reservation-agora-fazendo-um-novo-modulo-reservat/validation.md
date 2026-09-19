# Validation

## Acceptance Criteria

Repair only: close the required frontend coverage gate. Product behavior is unchanged.

- AC-014 / AC-022: list filtered-empty state offers `Limpar filtros` and that action visits `/reservations` with the current date, no `room_id`, and `page` 1
- AC-021: unexpected cancel failure shows `Não foi possível cancelar a reserva. Tente novamente.` and keeps the dialog open
- AC-018: create form writes sala, responsável, título, início, fim, and participantes through the Inertia form
- List retry error callbacks keep `Não foi possível carregar as reservas.`

## Test Results

Vitest + coverage (`npm run test:coverage`): 110 passed (17 files). Functions 88.61% (109/123), statements/lines 95.79%, branches 85.75%. Thresholds 80% met.

Added/extended Reservation page unit tests only. Existing tests were not weakened or deleted.

E2E: not applicable (no Playwright runner).

## Required Gates

Local repair re-ran `npm run test:coverage` (green). Other required gates were already green on the previous harness run. The harness re-runs deterministic checks after this phase; this worker does not declare them green.

## Review Result

No prior review. Repair targeted the failed `frontend` check only. No new review findings invented.

## Final Status

Repair is ready for harness checks. No commit from this worker.

### Extra files (dependency, not scope creep)

None. Only existing Reservation Vitest files were extended:

- `resources/js/Pages/Reservation/Index.test.jsx` — click `Limpar filtros`, exercise retry error callbacks, cover unexpected cancel failure (AC-014/021/022)
- `resources/js/Pages/Reservation/Create.test.jsx` — write the six create fields into the form (AC-018)

No product code changes. No SPEC_DEVIATION markers.

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
