# Task Context

## Relevant Documentation

- `docs/context.md` / `docs/architecture.md` / `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` / `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RNF03 and ADR-003 require React through **Inertia.js 2** in one Laravel app. Screens also name Inertia.js 2. There is no public REST UI contract.
- `docs/tree.md` — React pages in `resources/js/Pages`, shared UI in `Components` / `Layouts`, Inertia URLs isolated in `Services`.
- `docs/test/unit.md` — React pages, hooks, forms, and services are Vitest with Inertia mocked. Do not unit-test `package.json` / `composer.json` as configuration. Do not hit real HTTP or MySQL.
- `docs/test/integration.md` — Laravel request + middleware + FormRequest + controller + MySQL 8. This task does not add a controller or persistence flow. Existing Feature tests already cover `Inertia::render`.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project or dependency exists; `stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright.
- User text (keep original): “eu vi aqui que o projeto exige inertia.js 2 e o codigo usa o 3, faça ele usar o 2 e se precisa refatore pra usar o 2, apenas se necessário”

## Relevant Components

- `app` monolith: Laravel 12, PHP 8.2+, React 19.3, Tailwind 4, Vite 7, MySQL 8.
- Frontend adapter: `package.json` `@inertiajs/react` `^3.7.1` (lock `3.7.1` + `@inertiajs/core` `3.7.1`).
- Laravel adapter: `composer.json` `inertiajs/inertia-laravel` `^3.3` (lock `v3.3.4`).
- Bootstrap: `resources/js/app.jsx` (`createInertiaApp` + `createRoot`), `resources/views/app.blade.php` (`@inertia`, `@inertiaHead`).
- Shared props: `app/Http/Middleware/HandleInertiaRequests.php` (`rootView = app`, `version`, `share` of `auth.user.name` and flash).
- Pages/services that call Inertia: `Services/session.js`, `Services/rooms.js`, `Services/reservations.js`, `Pages/User/Login.jsx`, `Pages/Room/{Index,Create,Edit}.jsx`, `Pages/Reservation/{Index,Create,Edit}.jsx`, `Layouts/AppLayout.jsx` (`Link`, `useForm`, `usePage`).
- Gates (`harness/stack.yml`): PHPUnit Unit (`--coverage --min=80` on Application), Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. Do not run Playwright.

## Relevant Code

- PHP controllers only use `Inertia::render('…', props)`. No `Inertia::lazy()`, `optional()`, `defer()`, `merge()`, or v3-only facade APIs. Middleware is the stock v2-compatible `share` / `version` / `rootView` pattern. No published `config/inertia.php`.
- Client APIs in use that exist on Inertia 2: `createInertiaApp`, `useForm`, `usePage`, `Link`, `router.get`, `form.post` / `put` / `patch` / `delete`, `onError`, `onSuccess`, `onFinish`.
- **v3-only visit callbacks in product JS:** `onHttpException` and `onNetworkError` (v3 renamed `invalid` → `httpException`, `exception` → `networkError`). Used in `session.js` (defaults those handlers to `false` so the page stays put), `Login.jsx`, Room Create/Edit/Index retry, Reservation Create/Edit/Index cancel + retry. Vitest asserts those option names in `session.test.js`, `Login.test.jsx`, `Room/Create.test.jsx`, `Reservation/Create.test.jsx`, `Reservation/Index.test.jsx`.
- Inertia **2.x `VisitCallbacks` do not include** `onHttpException`, `onNetworkError`, `onInvalid`, or `onException`. v2 fires cancelable **global** events `inertia:invalid` and `inertia:exception`. Subscribe with `router.on('invalid' | 'exception', cb)` (returns unsubscribe). Call `event.preventDefault()` to skip the default non-Inertia modal / rethrow. Returning `false` from a visit option is a v3 visit-callback convention; on v2 it must become `preventDefault` on the global event.
- Pairing: official v3 upgrade moves client and `inertiajs/inertia-laravel` together. Downgrade must move **both** to `^2.0`. `inertia-laravel` 2.x supports Laravel 12 from v2.0.2. `@inertiajs/react` 2.x peer includes React 19 (`^16.9 || ^17 || ^18 || ^19`). Keep React 19. Inertia 2 still uses Axios internally; `axios` already exists for `bootstrap.js`.
- `createInertiaApp` React setup in the v2 docs matches `app.jsx` today. No `@inertiajs/vite` plugin (v3 SSR helper). `app.test.js` only asserts the glob exclude list.

## Existing Constraints

- One Laravel app; do not split SPA + API (ADR-003, ADR-001).
- Refactor client code **only** where v3-only APIs would go silent on v2. Do not restyle screens, change routes, props, FormRequests, or domain rules.
- Do not change PHP `Inertia::render` or `HandleInertiaRequests` unless the 2.x adapter fails to boot (not expected).
- Do not publish or invent `config/inertia.php`.
- Do not downgrade React, Vite, Tailwind, or Laravel.
- Existing Vitest and Feature suites are contracts: update callback names and Inertia mocks; do not drop failure-banner / return-false cases.
- Task 0005 context said “ADR text says Inertia 2 — do not downgrade”. This request explicitly overrides that.
- PHP Application coverage `>= 80%`. React coverage `>= 80%`. No Playwright.

## Important Decisions

- Pin `@inertiajs/react` to `^2.0` and `inertiajs/inertia-laravel` to `^2.0`, then refresh `package-lock.json` and `composer.lock` so resolved versions are 2.x only. Latest 2.x is intended (client ~2.3, adapter ~2.0.27), not an ancient 2.0.0.
- Keep React 19 and Laravel 12. They are compatible with Inertia 2.x.
- Necessary refactor: replace `onHttpException` / `onNetworkError` with a small Services helper that, for the duration of a visit, registers `router.on('invalid')` and `router.on('exception')`, maps the existing page handlers, calls `event.preventDefault()` when the handler returns `false`, and unsubscribes in `onFinish`. Pages/services pass `onInvalid` / `onException` (v2 event names). `session.js` keeps the default-`false` wrap so login still stays on the page when the page handler does not return `false`.
- Do not add a new global listener in `app.jsx` (would mix banners across pages). Do not leave v3 option names on visits (Inertia 2 ignores them; banners would die).
- Leave `createInertiaApp`, `Link`, `useForm` data/`processing`/`errors`, `router.get` list filters, `onSuccess` dialog close, and all PHP Inertia usage as they are.
- No new Feature or Playwright tests. Existing Feature Inertia responses stay the integration contract. Human may refuse the N/A reasons.
