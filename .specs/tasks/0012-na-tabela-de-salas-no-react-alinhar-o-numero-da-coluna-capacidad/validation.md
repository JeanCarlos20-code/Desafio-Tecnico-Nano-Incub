# Validation

## Acceptance Criteria

| AC | Spec outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 | `Capacidade` header and capacity integer share `text-center` | `resources/js/Pages/Room/Index.test.jsx:109` `expect(capacityHeader.className).toMatch(/\btext-center\b/)` and `:110` `expect(capacityCell.className).toMatch(/\btext-center\b/)` | Yes |
| AC-002 | `w-0` on `Capacidade`, `Status`, `Criada em`, and `Ações` so they stay compact below `xl` | `Index.test.jsx:146` headers `toMatch(/\bw-0\b/)`; `:151` cells `toMatch(/\bw-0\b/)` | Yes |
| AC-003 | `Nome` leftover-width priority via `w-full` + `min-w-0`, no max-width, no `xl:w-[16%]` | `Index.test.jsx:124-125` `toMatch(/min-w-0/)`; `:126-127` `toMatch(/\bw-full\b/)`; `:128-129` `not.toMatch(/xl:w-\[16%\]/)`; `:130-131` `not.toMatch(/max-w-/)` | Yes |
| AC-004 | `xl:w-[16%]` on the four secondary columns | `Index.test.jsx:147` headers `toMatch(/xl:w-\[16%\]/)`; `:152` cells `toMatch(/xl:w-\[16%\]/)` | Yes |
| AC-005 | `table-auto` in `overflow-x-auto`, secondary headers `whitespace-nowrap`, mobile `md:hidden` | `Index.test.jsx:148` `toMatch(/whitespace-nowrap/)`; `:177` `toMatch(/table-auto/)`; `:179` `toMatch(/overflow-x-auto/)`; `:182` `toMatch(/md:hidden/)` | Yes |
| AC-006 | Name, capacity, status, `DD/MM/YYYY`, actions; no room id | Existing `Index.test.jsx:82-84` `queryByText('id-1'/'id-2')` not in document; `:85-94` headers plus `Sala Azul`, `10`, `Ativa`/`Inativa`, `18/09/2026` | Yes |
| AC-007 | `Ações` header and cells use `text-center` and not `text-right` | `Index.test.jsx:162-163` `toMatch(/\btext-center\b/)`; `:164-165` `not.toMatch(/\btext-right\b/)` | Yes |

Edge cases: long names stay on `table-auto` (no `table-fixed`) at `Index.test.jsx:177-178`. Viewport below `md` keeps stacked cards at `:182`. Below `xl`, secondary columns stay compact with `w-0` at `:146`/`:151`.

Extra files: `spec.md`, `context.md`, `tasks.md`, and `docs/screens/screen-rooms-list.md` were updated because the human repair changed the leftover-share token from `xl:w-[10%]` to `xl:w-[16%]`. Those docs are the review contract for the new width share.

## Test Results

Frontend unit (`npm run test:coverage`): 15 files, 98 tests passed. Room/Index remains 13 cases. Alignment cases now require `xl:w-[16%]` on the four secondary columns and reject that token on `Nome`. Coverage 95.52% statements / 95.52% lines, above the 80% React threshold. Existing empty, delete, pagination, failure, and no-id cases stay.

Integration: not applicable for this CSS-only change. Existing Feature `RoomIndexHttpTest` was not rewritten.

E2E: not applicable. No Playwright runner.

## Required Gates

| Gate | Command | Local execute result |
| --- | --- | --- |
| frontend | `npm run test:coverage` | Passed locally (98 tests). Harness re-runs the official gate. |
| frontend_lint | `npm run lint` | Passed locally. Harness re-runs the official gate. |
| frontend_build | `npm run build` | Not re-run here; no build-config change. Harness re-runs this gate. |
| lint | `vendor/bin/pint --test` | Not re-run here; no PHP product change. Harness re-runs this gate. |
| php_build | `composer run build` | Not re-run here; no PHP product change. Harness re-runs this gate. |
| unit | `php artisan test --testsuite=Unit --coverage --min=80` | Not run here; no PHP product change. Harness re-runs this gate. |
| integration | `php artisan test --testsuite=Feature` | Not run here; no PHP product change. Harness re-runs this gate. |

Do not treat PHP or frontend-build gates as green from this report. The harness owns the final gate run.

## Review Result

Round 2 was APPROVED with no blockers/high. This repair answers the later human feedback: keep the current table design and shrink the leftover space between `Nome` and `Capacidade` by raising the secondary `xl` share from `10%` to `16%`.

## Final Status

Repair ready for harness checks. Secondary columns use `w-0 xl:w-[16%]`. `Nome` keeps `w-full` + `min-w-0`. `Capacidade` and `Ações` stay `text-center`. No commit from this worker.

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
