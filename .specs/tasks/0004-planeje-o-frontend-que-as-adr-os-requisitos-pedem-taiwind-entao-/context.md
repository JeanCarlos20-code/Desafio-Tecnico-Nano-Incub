# Task Context

## Relevant Documentation

- `docs/context.md` — stack includes React via Inertia.js 2 and **Tailwind CSS** (RNF04).
- `docs/adr/001-delimitar-escopo-aos-requisitos-rf-e-rnf.md` — **RNF04** is Tailwind CSS; **RNF03** is React + Inertia. Do not add another CSS framework.
- `docs/adr/003-react-e-php-no-mesmo-projeto-laravel.md` — UI lives in `resources/js/` (`Pages/`, `Components/`, `app.jsx`); styles in `resources/css/`. One Laravel app, no separate SPA.
- `docs/architecture.md` — Pages compose components; feature UI stays near the feature; do not add layers without need. Frontend validation is UX-only (out of this task).
- `docs/tree.md` — `resources/js/Pages/<Feature>/`, `Layouts/`, `Components/`.
- `docs/screens/screen-create-user.md` — `/register` card is **centered** with max width; desktop two columns ≈ `44% / 56%`; mobile one column, hide/compact hero, full-width controls, ≥ `24px` horizontal spacing, **no horizontal scroll**. Visual tokens: light blue-gray page, white rounded card, navy hero, bright-blue primary.
- `docs/test/unit.md` — React unit tests via RTL; assert CSS classes **only when styling is the behavior**. Mock Inertia, not child trees.
- `docs/test/e2e.md` + `harness/stack.yml` — Playwright is catalogued; **no Playwright project exists**. Do not add E2E for this layout task.
- `.specs/STATE.md` — create-user Execute already PASS (form, HTTP, session). This task is layout/styling on that screen plus other existing React pages.

User request (literal): planeje o frontend que as adr os requisitos pedem taiwind então implemente tawind no react e deixa as telas responsivas e deixa a tela de registro no meio da tela.

## Relevant Components

- `app` (Laravel + Inertia + React monolith).
- Existing React pages: `User/Create` (register), `Reservation/Index` (post-register stub).
- Unused `Layouts/AppLayout.jsx` (zinc admin shell).
- Tailwind 4 pipeline: `package.json` (`tailwindcss` ^4, `@tailwindcss/vite` ^4), `vite.config.js` (`tailwindcss()`), `resources/css/app.css` (`@import 'tailwindcss'` + `@source` for `*.jsx`), `resources/views/app.blade.php` (`@vite` css + `app.jsx`).

## Relevant Code

- `resources/js/Pages/User/Create.jsx` — already Tailwind utilities; outer shell is `min-h-screen … px-6 py-8` with card `mx-auto max-w-5xl` (horizontal center only). Card has `data-layout="register-card"` and `lg:grid-cols-[minmax(0,44%)_minmax(0,56%)]`.
- `resources/js/Pages/User/Components/BrandPanel.jsx` — hero `hidden … lg:flex`.
- `resources/js/Pages/User/Create.test.jsx` — already asserts two-column / hidden-hero / `overflow-x-hidden` / `w-full` controls. **Does not assert vertical viewport centering.**
- `resources/js/Pages/Reservation/Index.jsx` — `<div className="p-8"><h1>Reservas</h1></div>` only.
- `resources/js/Layouts/AppLayout.jsx` — `min-h-screen bg-zinc-50` + `mx-auto max-w-lg px-4 py-12`; **not imported anywhere**.
- Do not change register POST, `Services/users.js`, or Laravel validation in this task.

## Existing Constraints

- Keep Tailwind **v4 + Vite plugin**. Do not add `tailwind.config.js` / PostCSS Tailwind v3, Bootstrap, or CSS modules as the design system.
- Do not reinstall Tailwind; it is already on the React pages.
- Do not implement `/login`, rooms, or reservation CRUD (ADR-001 leftover RFs).
- Do not pixel-match `.local/image/screen-create-user.png`; adapt responsively.
- Register must **not** use `AppLayout` (split marketing card vs admin shell) — same decision as create-user.
- Vitest: `npm test`. PHPUnit Feature tests for `/register` must keep passing. `npx playwright test` is not a gate.
- `docs/test/unit.md`: class-name assertions are allowed here because centering/responsiveness **are** the product behavior.

## Important Decisions

- **Styling system:** keep current Tailwind CSS v4 (`@import 'tailwindcss'` + `@tailwindcss/vite`). Optional `@theme` tokens for navy/primary from the screen spec; not required if existing `slate`/`blue` utilities stay.
- **Register centering:** page shell becomes a flex container that centers the card on both axes (`min-h-screen` or `min-h-dvh` + `items-center` + `justify-center` / `justify-center-safe`), keeps `overflow-x-hidden` and ≥24px horizontal padding. If the card is taller than the viewport, allow vertical scroll without clipping the top (`justify-center-safe` or equivalent padding).
- **Other screens:** wrap `Reservation/Index` in `AppLayout`; make `AppLayout` a responsive admin shell (`overflow-x-hidden`, constrained width, viewport padding). Do not invent reservation UI.
- **Tests:** extend Vitest layout assertions on `Create`; add `AppLayout` unit test; extend `Reservation/Index` to prove it uses the shell. No new PHP Feature cases unless a layout change breaks Inertia rendering (it should not).
