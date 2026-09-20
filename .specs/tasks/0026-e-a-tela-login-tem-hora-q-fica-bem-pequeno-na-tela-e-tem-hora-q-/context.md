# Task Context

## Relevant Documentation

- `docs/screens/screen-login.md` — guest routes `GET /` and `GET /login`, submit `POST /login`, success `/reservations`. Desktop: centered card, max width, two columns near `46% / 54%`, hero visible. Portrait tablet / mobile: one column, hide or compact the large image, keep `ReservaSalas` near the top, fields and `Entrar` full width, at least `24px` horizontal spacing, no horizontal scroll. Visual file `.local/image/screen-login.png` guides composition; exact image pixels are not a contract. Auth, validation, and accessibility stay as written.
- `docs/adr/007-adotar-laravel-breeze-para-o-login.md` — Breeze `AuthenticatedSessionController` + `LoginRequest::authenticate()`. Keep `User/Login`. Do not reopen register, reset, or a custom login use case.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — Inertia 2 + React + Tailwind in the same Laravel app. Layout is Tailwind on the Inertia page.
- `docs/architecture.md` / `docs/tree.md` — page at `resources/js/Pages/User/Login.jsx`. Brand column is `Pages/User/Components/BrandPanel.jsx`. No new hook, service, or PHP layer for CSS.
- `docs/test/unit.md` — React layout belongs to Vitest. CSS class contracts are allowed when visual styling is the behavior. No real Laravel/MySQL.
- `docs/test/integration.md` — Laravel request + MySQL 8. This task does not change a controller, FormRequest, session, or persistence.
- `docs/test/e2e.md` — browser + React + Laravel + MySQL for selected full flows. No Playwright project or dependency exists; `stack.yml` lists `npx playwright test` but it is not runnable. Do not bootstrap Playwright.
- User text (keep original): “e a tela login tem hora q fica bem pequeno na tela, e tem hora q fica normal, da uma aumentada nesse login quando a tela é maior, deixa ele responsivo a parte do login”

## Relevant Components

- `app` monolith: Laravel + Inertia 2 + React + Tailwind + Eloquent/MySQL 8.
- Login page: `resources/js/Pages/User/Login.jsx` (`data-layout="login-card"`).
- Hero: `resources/js/Pages/User/Components/BrandPanel.jsx` (`data-layout="register-hero"`, `hidden lg:flex`, `min-h-[28rem]`, CSS `background-image` — no intrinsic width).
- Existing Vitest: `resources/js/Pages/User/Login.test.jsx` case `uses a two-column card on wide viewports…` asserts `lg:grid-cols-`, hero `hidden` + `lg:flex`, `Entrar` `w-full`, outer `overflow-x-hidden`, form still contains Reserva/Salas. It does not assert `w-full` or a larger max-width on the card.
- `BrandPanel.test.jsx` already covers hero hide/show. Do not retouch PHP (`LoginRequest`, Feature login HTTP).
- Gates (`harness/stack.yml`): PHPUnit Unit (`--coverage --min=80` on Application), Feature, `npm run test:coverage`, Pint, ESLint, `composer run build`, `npm run build`. Do not run Playwright.

## Relevant Code

- Outer shell: `flex min-h-dvh items-center justify-center-safe overflow-x-hidden bg-slate-100 px-6 py-8` (`px-6` = 24px).
- Card today: `mx-auto grid max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]`. There is **no `w-full`**.
- `max-w-5xl` is 64rem / 1024px. On a 1440–1920px desktop the card stays at most 1024px and looks small. Percentage tracks (`46%` / `54%`) are resolved against the grid container. An auto-sized container with no definite width lets those tracks collapse toward content. BrandPanel is `hidden` below `lg` and its photo is a background, so it does not establish width. Result: the card is sometimes content-sized (form-narrow) and sometimes closer to `max-w-5xl` — the reported flicker.
- Form column already `flex flex-col justify-center` with a compact Wordmark (`lg:hidden`). `Entrar` is already `w-full`. Keep those.
- Do not change `useForm`, `login()` from `Services/session`, banners, focus, or loading copy.

## Existing Constraints

- Frontend-only. No Domain / Application / FormRequest / session / Breeze / route / Inertia prop change.
- Do not add register, password recovery, or a `/register` link.
- Keep two columns at Tailwind `lg` (1024px) with proportions close to `46% / 54%`. Keep one column + hidden hero below `lg`. Keep ≥24px horizontal inset and no horizontal scroll.
- Existing Login Vitest/Feature/PHP Unit cases stay contracts except the layout case, which this task extends (add `w-full` and larger max-width; do not drop the two-column / hero / `w-full` button checks).
- PHP Application coverage `>= 80%`. React coverage `>= 80%`. Do not invent Playwright.

## Important Decisions

- Stabilize width by putting `w-full` on `[data-layout="login-card"]` so the card always consumes the padded viewport up to its max. That removes the shrink-to-content / percentage-track collapse.
- Enlarge the desktop cap: keep a usable floor with `max-w-6xl` (72rem / 1152px) and grow to `xl:max-w-7xl` (80rem / 1280px) when the viewport is Tailwind `xl` (1280px) or wider. Drop the current standalone `max-w-5xl` so large screens actually get a bigger card.
- Keep `lg:grid-cols-[minmax(0,46%)_minmax(0,54%)]`, hero `hidden lg:flex`, outer `px-6` / `overflow-x-hidden` / viewport centering. Do not rewrite BrandPanel unless `w-full` on the card is not enough (it should be).
- Assert the Tailwind class contract in Vitest (jsdom does not apply media queries or compute layout).
- Update `docs/screens/screen-login.md` Responsiveness with the `w-full` + `max-w-6xl` / `xl:max-w-7xl` tokens so the screen doc matches the code.
- Integration and E2E are not applicable: no HTTP/DB change; no Playwright runner. Human may refuse those reasons.
