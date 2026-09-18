# Specification

## Context

The rooms list desktop table (`resources/js/Pages/Room/Index.jsx`, `GET /rooms`) already shows `Nome`, `Capacidade`, `Status`, `Criada em`, and `Ações`. A 0009 repair gave leftover width to `Nome` and marked the other columns `w-0 whitespace-nowrap`. On wide viewports the capacity integer no longer sits under the `Capacidade` header, the secondary columns stay glued to their content while `Nome` takes all extra space, and `Ações` stays `text-right`. The human plan revision requires `Ações` to be centered as well.

## Problem

An administrator reading the rooms table cannot visually pair the capacity number with the `Capacidade` heading. The name column still should grow first, but on very large screens the leftover space should also reach `Capacidade`, `Status`, `Criada em`, and `Ações`. The `Ações` header and row buttons must sit in the center of that column, not on the right.

## Problem Statement

The rooms list desktop table SHALL place each room’s capacity integer under the `Capacidade` header. `Nome` SHALL keep first claim on leftover width. On Tailwind `xl` viewports the system SHALL also give a modest width share to `Capacidade`, `Status`, `Criada em`, and `Ações`. The `Ações` header and each row’s action controls SHALL share `text-center`. Mobile cards, backend list data, and existing CRUD behavior stay out of this change.

## Goal

- Capacity cells and the `Capacidade` header share the same column box and the same centered text alignment.
- `Nome` remains the column that absorbs most leftover width.
- At `xl` and above, `Capacidade`, `Status`, `Criada em`, and `Ações` receive a leftover share (`xl:w-[16%]` each).
- `Ações` header and cells are centered (`text-center`), not `text-right`.
- Horizontal scroll, nowrap secondary labels, and mobile cards remain.

## User Stories

### P1: Read capacity under its header ⭐ MVP

**User Story**: As an administrator on `/rooms`, I want the capacity number to sit under the `Capacidade` heading so that I can scan the table without guessing which figure belongs to that column.

**Why P1**: This is the user’s primary request.

**Acceptance Criteria**:

1. WHEN the desktop rooms table is shown THEN the system SHALL render the `Capacidade` header and each row’s capacity integer in the same column with the same horizontal text alignment class (`text-center`)
2. IF a room row is rendered on the desktop table THEN the system SHALL apply `w-0` to the `Capacidade` header and capacity cells so the column stays compact below `xl`
3. The desktop rooms table SHALL keep `table-auto` and SHALL NOT switch to `table-fixed`

**Independent Test**: Vitest `Room/Index.test.jsx` asserts shared `text-center` on the `Capacidade` header and a capacity cell.

---

### P1: Keep name first, share leftover on very large screens ⭐ MVP

**User Story**: As an administrator on a very large monitor, I want `Nome` to still take most extra table width, and I want `Capacidade`, `Status`, `Criado`/`Criada em`, and `Ações` to pick up a little leftover space so the table does not leave a huge empty name gutter.

**Why P1**: Second half of the user request.

**Acceptance Criteria**:

1. WHILE the desktop rooms table is shown the system SHALL keep leftover-width priority on `Nome` via `w-full` + `min-w-0` and no max-width / no `xl:w-[16%]` on that column
2. WHEN the viewport is Tailwind `xl` or wider THEN the system SHALL apply `xl:w-[16%]` to `Capacidade`, `Status`, `Criada em`, and `Ações` headers and matching cells
3. WHILE the desktop rooms table is shown the system SHALL keep `w-0` and `whitespace-nowrap` on those four secondary headers so they stay compact below `xl`, keep `overflow-x-auto` around the table, and keep mobile stacked cards with `md:hidden`

**Independent Test**: Vitest updates the current compact-column case to the new class contract; other Index cases stay.

---

### P1: Center the actions column ⭐ MVP

**User Story**: As an administrator on `/rooms`, I want the `Ações` heading and the edit/delete buttons centered in that column so the actions sit under the header instead of hugging the right edge.

**Why P1**: Human plan revision: “e ações ficar centralizada também”.

**Acceptance Criteria**:

1. WHEN the desktop rooms table is shown THEN the system SHALL apply `text-center` to the `Ações` header and each row’s `Ações` cell
2. IF the desktop rooms table is shown THEN the system SHALL NOT apply `text-right` to the `Ações` header or `Ações` cells

**Independent Test**: Vitest replaces the current `Ações` `text-right` assertion with shared `text-center` on the header and an actions cell.

## Acceptance Criteria

- **AC-001** WHEN the desktop rooms table is shown THEN the system SHALL render the `Capacidade` header and each capacity integer in the same column with shared `text-center` alignment
- **AC-002** IF the desktop rooms table is shown THEN the system SHALL apply `w-0` to `Capacidade`, `Status`, `Criada em`, and `Ações` so those columns stay compact below `xl` and `Nome` remains the largest
- **AC-003** WHILE the desktop rooms table is shown the system SHALL keep leftover-width priority on `Nome` (`w-full`, `min-w-0`, no max-width, no `xl:w-[16%]`)
- **AC-004** WHEN the viewport is Tailwind `xl` or wider THEN the system SHALL apply `xl:w-[16%]` to `Capacidade`, `Status`, `Criada em`, and `Ações`
- **AC-005** The desktop rooms table SHALL remain `table-auto` inside `overflow-x-auto`, secondary headers SHALL stay `whitespace-nowrap`, and mobile cards SHALL stay `md:hidden`
- **AC-006** The rooms list SHALL keep showing name, capacity, status, `DD/MM/YYYY` date, and actions, and SHALL NOT display the room id (existing contract)
- **AC-007** WHEN the desktop rooms table is shown THEN the system SHALL apply `text-center` to the `Ações` header and each `Ações` cell and SHALL NOT apply `text-right` on that column

## Edge Cases

- IF a room name is long THEN the system SHALL keep `table-auto` + `overflow-x-auto` and SHALL NOT truncate the name
- IF the viewport is below `md` THEN the system SHALL keep stacked cards and SHALL NOT depend on table column classes for the mobile layout
- IF the viewport is `md`/`lg` but below `xl` THEN secondary columns SHALL stay compact with `w-0` so `Nome` remains the largest column and `Ações` does not stretch
- IF the existing Vitest case still requires `w-0` or `text-right` on `Ações` THEN Execute SHALL replace those assertions to match AC-002 / AC-004 / AC-007 (intentional contract change, not a weakened test)
- Remaining implicit-requirement dimensions (auth, persistence, concurrency, retries) are N/A for this CSS-only scope

## Out of Scope

| Feature | Reason |
| --- | --- |
| Backend list / Inertia props / Room schema | Already delivered; numbers already render |
| Mobile card layout rewrite | Cards already show `Capacidade: {n}` |
| `table-fixed` or name truncation | Screen forbids silent truncation |
| Rename `Criada em` to `Criado` | User shorthand; keep the existing header |
| Right-align the capacity number | Capacity and its header share `text-center`; only leftover-width sharing stays on `xl` |
| Rewrite `RowActions` markup | `inline-flex` plus `text-center` on the cell is enough |
| Playwright / new E2E runner | No Playwright project; do not bootstrap here |
| Reservations table or other screens | User asked only the rooms table |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| What “align” means for capacity | Same column box + shared `text-center` on `Capacidade` th/td | Human repair: the number still was not centered under `Capacidade` | y |
| What “ações ficar centralizada” means | Shared `text-center` on `Ações` th and td; drop `text-right` | Human revision; `RowActions` is `inline-flex` so the button group centers with the header | y |
| What “very large screens” means | Tailwind `xl` (1280px) | Standard Tailwind token; `md` is already the desktop table breakpoint | n |
| How much leftover the secondary columns take | `xl:w-[16%]` each (about 64% combined; `Nome` keeps the rest and stays largest) | Human repair: 10% still left too much space between Nome and Capacidade | y |
| Label “Criado” | Keep visible header `Criada em` | Existing screen contract | n |
| Pixel-perfect jsdom geometry | Assert Tailwind class contracts, not computed offsets | jsdom does not layout tables | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Keep `w-0` and only change capacity `text-align`.** Smallest diff. Does not give secondary columns leftover space on large screens and can still mis-size the column box versus the header.
2. **`table-fixed` plus percentage columns.** Predictable boxes. Risks clipping long names (forbidden) unless overflow/truncate is added.
3. **Keep `table-auto`, center capacity with `text-center`, keep secondary columns compact with `w-0` below `xl`, give them `xl:w-[16%]` on very large screens, give `Nome` leftover via `w-full` + `min-w-0`, center `Ações`.** Human repair: keep this layout and shrink the leftover gutter between `Nome` and `Capacidade`. Selected.
4. **Keep `Ações` `text-right` (previous plan).** Rejected by human feedback.

## Selected Approach

Approach 3. Edit only `Index.jsx` desktop table classes. Replace `text-right` on `Ações` th/td with `text-center`. Update the existing compact-column Vitest case. Document the desktop width rules and centered actions in `docs/screens/screen-rooms-list.md`. No PHP, no mobile-card rewrite, no Playwright, no `RowActions` rewrite.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| ROOM-01 | P1: Read capacity under its header | Execute | Implemented |
| ROOM-02 | P1: Read capacity under its header | Execute | Implemented |
| ROOM-03 | P1: Keep name first, share leftover on very large screens | Execute | Implemented |
| ROOM-04 | P1: Keep name first, share leftover on very large screens | Execute | Implemented |
| ROOM-05 | P1: Center the actions column | Execute | Implemented |

**Coverage:** 5 total, 5 mapped to tasks, 0 unmapped
