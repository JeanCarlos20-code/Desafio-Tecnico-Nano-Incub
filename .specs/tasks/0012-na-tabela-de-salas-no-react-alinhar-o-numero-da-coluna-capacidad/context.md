# Task Context

## Relevant Documentation

- `docs/screens/screen-rooms-list.md` — desktop table columns are `Nome`, `Capacidade`, `Status`, `Criada em`, `Ações`. Do not show the room id. Do not silently truncate short names. Desktop shows all columns; tablet may scroll horizontally; mobile uses stacked cards (`md:hidden`). The screen still says “keep row actions aligned on the right”; this task overrides that line so `Ações` is centered. Visual reference: `.local/image/screen-rooms-list.png`.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — Inertia + React + Tailwind in the same Laravel app. No public REST for this UI.
- `docs/adr/004-modulo-rooms-ciclo-de-vida-minimo.md` — list contract is name, capacity, status, created date. No schema or API change here.
- `docs/architecture.md` / `docs/tree.md` — page lives at `resources/js/Pages/Room/Index.jsx`. No new hook, service, or backend layer for CSS.
- `docs/test/unit.md` — React layout/state belongs to Vitest. CSS class contracts are allowed when visual styling is the behavior. No real Laravel/MySQL.
- `docs/test/integration.md` — Laravel request + MySQL 8. This task does not change a controller, FormRequest, or persistence.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project exists; do not bootstrap one.
- Task `0009` AC-018 (human repair) previously forced leftover width onto `Nome` and kept `Capacidade` / `Status` / `Criada em` / `Ações` compact with `w-0` + `whitespace-nowrap`. That contract is what the user is now changing.
- User text (keep original): “Na tabela de salas no React, alinhar o número da coluna capacidade com o cabeçalho Capacidade. O nome continua com maior prioridade no crescimento da página, mas em telas muito grandes espalhar um pouco o espaço também com Capacidade, Status, Criado e Ações.”
- Human plan revision (keep original): “e ações ficar centralizada também”

## Relevant Components

- `app` monolith: Laravel + Inertia 2 + React + Tailwind + Eloquent/MySQL 8.
- Rooms list page already shipped: `resources/js/Pages/Room/Index.jsx` (desktop `<table>` from `md` up; mobile `<ul>` cards).
- Existing Vitest contract: `resources/js/Pages/Room/Index.test.jsx` case `keeps compact columns from stretching and allows horizontal table scroll` asserts `table-auto`, `overflow-x-auto`, `Nome` `min-w-0`, `Ações` `w-0` / `whitespace-nowrap` / `text-right`, other headers `whitespace-nowrap`, mobile `md:hidden`.
- `RowActions` is `inline-flex gap-2` inside the `Ações` td. `text-center` on the th/td centers that inline group under the header. Do not rewrite `RowActions` unless `text-center` is not enough.
- Backend list flow unchanged: `IndexRoomController` + Feature `RoomIndexHttpTest`. Do not retouch PHP.
- Gates (`harness/stack.yml`): PHPUnit Unit (`--coverage --min=80` on Application), Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. `npx playwright test` is catalogued but not runnable.

## Relevant Code

- Desktop table: `w-full table-auto text-left`. `Nome` th/td use `min-w-0`. `Capacidade`, `Status`, `Criada em`, and `Ações` th/td use `w-0 whitespace-nowrap`. `Ações` is `text-right` today.
- `w-0` + `table-auto` collapses secondary columns to a zero specified width. The short capacity integer then sits in a squeezed column box that does not share a stable width with the longer `Capacidade` header, so the number does not line up under the header.
- The same `w-0` rule also prevents leftover viewport width from reaching `Capacidade`, `Status`, `Criada em`, and `Ações`. `Nome` absorbs essentially all extra space — that was the 0009 repair, and it is now too aggressive on very large screens.
- Mobile cards already label capacity as `Capacidade: {n}` and are out of this visual bug. Keep them.
- Horizontal scroll wrapper `overflow-x-auto` and surface `min-w-0 md:block` stay. Do not switch to `table-fixed` (risk of clipping names; screen forbids silent truncation).

## Existing Constraints

- Frontend-only. No Domain / Application / FormRequest / migration / Inertia prop change.
- Do not add an ID column. Do not truncate room names. Do not drop mobile cards or `overflow-x-auto`.
- Center `Ações` (header and cells). Do not keep `text-right` on that column.
- Keep header labels in Portuguese (`Criada em`, not a rename to “Criado”).
- Existing Room Index Vitest/Feature cases are contracts except the compact-`w-0` and `Ações` `text-right` assertions, which this task intentionally replaces.
- PHP Application coverage `>= 80%`. React coverage `>= 80%`. Do not invent Playwright.

## Important Decisions

- Align capacity by giving `Capacidade` th and td the same column box and shared `text-center`. Human repair: the number must sit centered under `Capacidade`.
- Keep `w-0` on `Capacidade`, `Status`, `Criada em`, and `Ações` below `xl` so `Nome` stays the largest column when the desktop table shrinks. Keep `whitespace-nowrap` so those headers/values do not wrap.
- `Nome` keeps leftover-width priority via `w-full` + `min-w-0` and no max-width / no small percentage cap.
- “Very large screens” means Tailwind `xl` (1280px). At `xl`, the four secondary columns get a shared width token (`xl:w-[16%]` each) so leftover is no longer 100% `Nome` and the gutter between `Nome` and `Capacidade` stays smaller. Below `xl`, they stay compact with `w-0` + `whitespace-nowrap` so `Nome` remains the largest column.
- Human revision: `Ações` th and td use shared `text-center` (not `text-right`). `RowActions` stays `inline-flex`.
- Keep `table-auto` + `overflow-x-auto`. Update `docs/screens/screen-rooms-list.md` desktop rules so width sharing and centered actions replace the old right-aligned / compact-`w-0` contract.
- E2E not applicable: no Playwright runner. Layout is protected by Vitest class contracts. Human may refuse that reason.
