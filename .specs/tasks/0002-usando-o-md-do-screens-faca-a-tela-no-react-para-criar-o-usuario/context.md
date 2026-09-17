# Task Context

## Relevant Documentation

- `docs/screens/screen-create-user.md` — primary source of truth for this screen (route `/register`, POST `/register`, layout, copy, validation, states, a11y). Visual reference: `.local/image/screen-create-user.png`.
- `docs/tree.md` — Inertia page at `resources/js/Pages/<Feature>/Create.jsx`; feature components next to the page; `Services` owns Inertia POST URLs; no hook unless behavior is reused.
- `docs/architecture.md` — React → Service → Inertia → route → thin controller → Form Request → use case. Server validation is authoritative (RNF08).
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `users` contract, Argon hash, `remember_token` not a create field, email unique including soft-deleted rows.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — one Laravel app; no public JSON API for this UI.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF01 login and RF07 reservations are separate features. This task must not build those screens.
- `docs/test/unit.md` — React unit tests (RTL + mocked Inertia) for loading/error/empty/interaction; no real Laravel.
- `docs/test/integration.md` — every controller action needs Feature tests on real HTTP + DB.
- `docs/test/e2e.md` — Playwright is the E2E tool, but this repo has no Playwright runner yet; do not bootstrap it here.
- `harness/stack.yml` — app gates: `php artisan test`, `--testsuite=Unit|Feature`, `npm test`, `npx playwright test`, `vendor/bin/pint --test`, `npm run build`.
- `.specs/tasks/create-user/spec.md` — persistence + `GET /users/create` + `POST /users` already verified. Unconfirmed assumptions there (`users.create` redirect, no `auth`, password min 8) are overridden by the screen where they conflict, except password min 8 (screen says follow Laravel defaults and change the placeholder).

## Relevant Components

- `app` monolith: Laravel 12 + Inertia React 19 + Tailwind 4 + PHPUnit. Vitest is listed in stack.yml (`npm test`) but is not installed.

## Relevant Code

- `resources/js/Pages/User/Create.jsx` — bare zinc form (`Cadastrar usuário`); uses `AppLayout`; no brand panel, icons, password toggle, loading label, or login link.
- `resources/js/Services/users.js` — `form.post('/users')`.
- `resources/js/Layouts/AppLayout.jsx` — single-column admin shell; **not** the register card. Register must not wrap in this layout.
- `app/Modules/User/Infra/Http/Controllers/UserController.php` — `create` renders `User/Create`; `store` runs `CreateUser` and redirects to `users.create`. No `Auth::login`. Domain `User::$id` is a public readonly property.
- `app/Modules/User/Infra/Http/Requests/StoreUserRequest.php` — `name` required string max 255; `email` required unique on `users`; `password` `Password::defaults()` (8). No `prepareForValidation`, no Portuguese `messages()`.
- `app/Modules/User/Application/UseCases/CreateUser.php` — persist only; do not put HTTP validation back here.
- `routes/web.php` — `GET /users/create` (`users.create`), `POST /users` (`users.store`). No `register`, `/login`, or `/reservations`.
- `tests/Feature/User/CreateUserHttpTest.php` — Inertia component + validation + unique including soft-delete. Expects redirect to `users.create`. No session assertion.
- `tests/Unit/User/CreateUserTest.php` — use-case delegation; do not weaken.
- `README.md` — one sentence about optional registration; does not document access policy.
- No `resources/js/**/*.test.*`, no Playwright config, no meeting-room image in `public/`.

## Existing Constraints

- Do not invent a REST API, Fortify, or an icon library. Inline SVGs.
- Do not move validation into the use case (already decided).
- Do not add `Pages/User/Index.jsx`, `Edit.jsx`, or `Hooks/` for one-off toggle state.
- `resources/js/Services/users.js` groups as module `resources` (not `user`). `routes/web.php` groups as `routes`. `Pages/Reservation/*` groups as `reservation`.
- Password hashing and unique email (including trash) already exist; keep them.
- Login (RF01) and reservations CRUD (RF07+) are out of this task.

## Important Decisions

- Keep rendering `User/Create`; expose the screen at `GET /register` and submit `POST /register`. Keep `GET /users/create` and `POST /users` as aliases to the same controller actions.
- Keep `Password::defaults()` (8 characters). Placeholder and password error copy use 8, not the mock’s “6”.
- After a valid POST: `Auth::login` the Eloquent model, redirect to `/reservations`. Add a named `GET /reservations` Inertia stub (`Reservation/Index`) so the success URL is not a 404. Stub is not reservation CRUD.
- `/register` stays public (no `auth` middleware) because login does not exist. Document that in README. `Ir para o login` is an Inertia `Link` to `/login` without implementing the login page.
- Brand panel: Tailwind navy overlay + copy from the screen spec. Optional local `public/images/register-hero.jpg`; no remote hotlink. Exact mock pixel sizes are not required.
- Unexpected failures: page-level banner `Não foi possível criar o usuário. Tente novamente.` via Inertia `onException` / non-422 visit failure; `aria-live="polite"`.
- Validation errors: show `form.errors.*` under fields; `onError` → `reset('password')`, keep name/email, `aria-invalid` / `aria-describedby`, focus first invalid field.
- Frontend tests: add Vitest + RTL + jsdom (`npm test` = `vitest run`) in `vite.config.js`. Mock `@inertiajs/react`. Do not add Playwright in this task.
- No confirmed TLC lessons.
