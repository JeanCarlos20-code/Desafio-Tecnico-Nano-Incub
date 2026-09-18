# Task Context

## Relevant Documentation

- `docs/screens/screen-login.md` — login is `GET /login` + `POST /login`, guests only, success → `/reservations`. No password recovery. Registration link is optional only when administrator registration is intentionally enabled. User request enables that link.
- `docs/screens/screen-create-user.md` — public `GET /register` + `POST /register` already exists. Secondary control `Ir para o login` → `/login`. Visual pattern: divider + full-width secondary button.
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — RF01 administrator login; RF02 guests cannot use internal routes. Public self-registration is a challenge convenience, not a new RF.
- `docs/adr/002-usuario-minimo-uuidv7-argon2-auth-laravel.md` — Laravel session auth; do not invent a hasher, JWT, or extra roles.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — Inertia is the UI contract; no public REST.
- `docs/architecture.md` / `docs/tree.md` — thin HTTP adapters; React pages under `resources/js/Pages/User/`; Inertia navigation isolated from pages when a Service exists. This task only adds an Inertia `Link` (same pattern as `Create.jsx`).
- `docs/test/unit.md` — React page/form/state without Laravel/MySQL. No new backend use-case rules here.
- `docs/test/integration.md` — Laravel request + middleware + controller + MySQL 8. Home/login GET wiring belongs here; do not repeat the POST `/login` validation matrix.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected auth/navigation flows. No Playwright project exists; do not bootstrap one here.
- `README.md` — `/register` is already documented as public for the technical challenge.
- User text (keep original): “a tela principal do projeto deve ser a tela de login, e a tela de login deve colocar um lugar para cadastro para chamar a pagina de cadastro”.

## Relevant Components

- `app` monolith: Laravel + Inertia 2 + React + Tailwind + Eloquent/MySQL 8.
- User auth already shipped: `LoginController`, `LoginRequest`, `AuthenticateUser`, Inertia `Pages/User/Login.jsx`, `Services/session.js` `login` → `POST /login`.
- Registration already shipped: `UserController` + `Pages/User/Create.jsx` at `GET /register`, `Link` to `/login`.
- `routes/web.php`: `GET /` still returns Blade `welcome`. Guest group owns `/login` and `/register`. `bootstrap/app.php` sends guests to `route('login')` and authenticated users to `route('reservations.index')`.
- Tests: Vitest `resources/js/Pages/User/Login.test.jsx` (currently asserts **no** registration link); Feature `tests/Feature/User/LoginHttpTest.php`; leftover `tests/Feature/ExampleTest.php` `GET /` status 200 without `withoutVite()`.
- Gates (`harness/stack.yml`): PHPUnit Unit (`--coverage --min=80` on Application), Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. `npx playwright test` is catalogued but not runnable.

## Relevant Code

- `Route::get('/', fn () => view('welcome'))` is outside `guest`/`auth`. Opening the app shows the Laravel starter page, not login.
- `LoginController::create` already renders Inertia `User/Login`. Guest middleware already redirects authenticated users away from `/login`.
- `Login.jsx` has heading `Acesse sua conta`, email/password, `Entrar`. No divider, no `Link`, no register control. `Create.jsx` already has divider `Já tem uma conta?` and `Link href="/login"` labeled `Ir para o login`.
- `Login.test.jsx` mocks only `useForm` from `@inertiajs/react`. Adding `Link` requires the same `Link` mock as `Create.test.jsx`.
- `ExampleTest` will fail once `/` is an Inertia page unless it uses `withoutVite()` or is removed. Prefer covering `GET /` in `LoginHttpTest` and dropping the welcome smoke test.
- `resources/views/welcome.blade.php` becomes unused. Leave it; do not delete the Laravel default view in this task.
- No Domain/Application/FormRequest change. `POST /login`, rate limit, session regenerate, and create-user HTTP stay as they are.

## Existing Constraints

- Do not rebuild login authentication, validation, or the create-user screen.
- Do not add password recovery, JWT, extra roles, public REST, or Playwright.
- Do not change `POST /login`, `AuthenticateUser`, or `LoginRequest`.
- Keep named route `login` at `GET /login` (Laravel auth + screen contract).
- Existing Login Vitest/Feature tests are contracts; replace only the assertion that forbids a registration link.
- PHP coverage gate remains Application-only (`>= 80%`). React coverage `>= 80%` via Vitest.

## Important Decisions

- Serve the same `LoginController::create` Inertia page at `GET /` **and** `GET /login`, both under `guest` middleware. Guests see login at the app home without an extra 302. Authenticated `GET /` and `GET /login` redirect to `/reservations`.
- Do not make `/` the only login URL and do not `Route::redirect('/', '/login')` (extra hop; `ExampleTest` 302; weaker “home is login”).
- Mirror create-user navigation: divider `Não tem uma conta?` + secondary Inertia `Link` `Ir para o cadastro` with `href="/register"`. Keep password recovery absent.
- Public `/register` stays as already documented in README. No README rewrite unless a sentence becomes false (it should not).
- Update `docs/screens/screen-login.md` navigation so the enabled register CTA is the documented contract.
- E2E not applicable: no Playwright runner. Human may refuse that reason.
