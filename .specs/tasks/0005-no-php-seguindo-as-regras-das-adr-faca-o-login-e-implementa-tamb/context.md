# Task Context

## Relevant Documentation

- `docs/screens/screen-login.md` — source of truth for RF01 UI (`GET/POST /login`, guests only, copy, states, a11y, generic invalid-credentials text, CSRF, session regenerate, rate limit, logout session invalidation). Visual: `.local/image/screen-login.png`. Exact mock pixels are not required.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF01 login, RF02 protected panel routes. RNF07 native Laravel/Breeze-style auth (do not reinvent). RNF08 server validation. RNF10 hashed passwords. RNF14 idiomatic Laravel. Closed scope: no password recovery, JWT, roles, email verification.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — `Authenticatable` + session guard + `HASH_DRIVER=argon` + `SoftDeletes` (trashed users must not authenticate). `remember_token` is runtime-only, not a form field.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — one Laravel app; Inertia pages; no public JSON API for this UI.
- `docs/architecture.md` — React → Service → Inertia → route → thin controller → Form Request. Domain stays pure PHP. Login is Laravel session auth: keep `Auth::attempt` in Infra, not in a Domain/Application use case.
- `docs/tree.md` — HTTP under `app/Modules/User/Infra/Http/{Controllers,Requests}`; pages under `resources/js/Pages/<Feature>/`; services own Inertia URLs.
- `docs/test/unit.md` — React unit tests (RTL, mocked Inertia) for copy, loading, errors, interaction. No real Laravel.
- `docs/test/integration.md` — every new controller action needs Feature tests on real HTTP + MySQL.
- `docs/test/e2e.md` — Playwright is the E2E tool; this repo still has no Playwright runner. Do not bootstrap it here.
- `docs/reviews/review-security.md` — protect panel routes with `auth`; generic credentials errors; no password in logs/old input; CSRF stays on.
- `harness/stack.yml` — gates: `php artisan test --testsuite=Unit|Feature`, `npm test`, `vendor/bin/pint --test`, `npm run build`. `npx playwright test` exists in the catalog but is not runnable.
- `README.md` — still says `/register` is public *because login is unimplemented*. That sentence must change when login ships.
- No confirmed TLC lessons.

## Relevant Components

- `app` monolith: Laravel 12, Inertia React (`@inertiajs/react` 3.x already installed; ADR text says Inertia 2 — do not downgrade), Tailwind 4, PHPUnit, Vitest. User module already persists administrators and auto-logs them in after `POST /register`.

## Relevant Code

- `routes/web.php` — `GET/POST /register`, `GET/POST /users*`, public `GET /reservations` stub. No `/login`, no `/logout`, no `auth`/`guest` middleware.
- `bootstrap/app.php` — no `redirectGuestsTo` / `redirectUsersTo`.
- `app/Modules/User/Infra/Database/Models/User.php` — `Authenticatable`, `HasUuids`, `SoftDeletes`, `password` cast `hashed`, `password`/`remember_token` hidden. Guard already points here (`config/auth.php`).
- `app/Modules/User/Infra/Http/Controllers/UserController.php` — create/store only; `Auth::login` after register. Do not fold login into this controller.
- `app/Modules/User/Infra/Http/Requests/StoreUserRequest.php` — register validation + email trim/lowercase. Login needs its own Form Request (required password, no unique/min-8-on-login).
- `app/Modules/User/Application/UseCases/CreateUser.php` — persist only; leave it alone.
- `resources/js/Pages/User/Create.jsx` — split-card register; `Link` already points at `/login` (currently 404). Reuse `BrandPanel`, `IconTextField`, `PasswordField`. Login copy/footer/button differ; do not restyle Create into login.
- `resources/js/Pages/User/Components/BrandPanel.jsx` — register footer `Comece agora e ajude a manter o seu time mais produtivo.` Login footer is `Mais produtividade para o seu time.` Add a `footer` prop; default keeps register tests green.
- `resources/js/Services/users.js` — `form.post('/register')`. Add `Services/session.js` for `POST /login` (and logout). Do not put `/login` inside `users.js`.
- `resources/js/Layouts/AppLayout.jsx` — post-login shell for `Reservation/Index`; no logout control yet.
- `tests/Feature/User/CreateUserHttpTest.php` — register still works if `/reservations` requires `auth` (it already authenticates after store). Do not weaken it.
- `database/seeders/DatabaseSeeder.php` — creates `test@example.com` with factory password `password`. RF19 rooms/reservations seeder is out of this task.
- Vitest is installed (`npm test` = `vitest run`). No Playwright config.
- No `lang/` JSON; Portuguese messages live on the Form Request.

## Existing Constraints

- Do not install Breeze/Fortify/Sanctum SPA/JWT. Mirror Laravel session login (`Auth::attempt`, session regenerate, `RateLimiter`, `guest`/`auth` middleware).
- Do not add an `AuthenticateUser` use case: Application cannot depend on `Illuminate\Support\Facades\Auth`.
- Do not compare passwords manually. Do not log or flash the raw password (`->onlyInput('email')`).
- Do not add password recovery, remember-me checkbox, email verification, or roles.
- Do not add a register link on the login screen (screen-login: omit unless registration is intentionally enabled *and* documented — registration stays at `/register` for the challenge, documented in README, not advertised on login).
- Do not invent a REST login endpoint.
- `resources/js/Services/*.js` groups as module `resources`. `routes/web.php` as `routes`. `bootstrap/app.php` as `bootstrap`. `Pages/User/*` and `app/Modules/User/*` as `user`.
- Playwright remains deferred.

## Important Decisions

- Page: `resources/js/Pages/User/Login.jsx` (not `Auth/Login`) so it colocates with BrandPanel and groups as `user`.
- HTTP: `LoginController` + `LoginRequest` in `app/Modules/User/Infra/Http`. `GET /login` (`login`), `POST /login` (`login.store`), `POST /logout` (`logout`).
- Authenticate with `Auth::attempt($this->only('email', 'password'))` then `$request->session()->regenerate()` then `redirect()->intended(route('reservations.index'))`.
- Invalid credentials and lockout use error key `credentials` with `E-mail ou senha inválidos.` (lockout may use a distinct too-many-attempts message on the same banner). Do not use `E-mail não encontrado` / `Senha incorreta`. Do not mark the email field invalid for a credentials miss.
- Rate limit: Laravel `RateLimiter` in `LoginRequest`, 5 attempts per normalized email + IP, then 60s lockout (starter-kit default).
- Middleware: `guest` on login + register aliases; `auth` on `/reservations` and `POST /logout`. `redirectGuestsTo(route('login'))`, `redirectUsersTo(route('reservations.index'))`.
- Logout: `Auth::logout()`, `session()->invalidate()`, `regenerateToken()`, redirect `/login`. AppLayout gets a `Sair` control that posts through `session.js` so logout is reachable after login.
- `/register` stays guest-accessible for the challenge. README must stop saying login is missing and must state that public admin self-registration is still a challenge convenience, not production-safe.
- Password field: no English placeholder; `type=password` masks input (mock dots are typed characters). Reuse `PasswordField` (visibility toggle stays).
- Email: `prepareForValidation` trim + lowercase before attempt, matching stored emails.
- Frontend tests: Vitest on `Login.test.jsx` + `session.test.js` (+ BrandPanel footer + AppLayout Sair). Feature tests in `tests/Feature/User/LoginHttpTest.php`.
