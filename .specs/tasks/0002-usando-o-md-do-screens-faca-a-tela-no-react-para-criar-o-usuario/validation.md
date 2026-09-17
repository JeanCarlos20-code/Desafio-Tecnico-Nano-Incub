# Validation

Human-feedback repair: white `/register` screen (Inertia eager glob imported `Create.test.jsx` / Vitest into the client bundle) and missing focused Vitest coverage for production React modules. No commit, merge, push, or deploy.

## Acceptance Criteria

| AC | Spec-defined outcome | Evidence | Covered? |
| --- | --- | --- | --- |
| AC-001 | Unauthenticated `GET /register` returns 200 and Inertia `User/Create` | `tests/Feature/User/CreateUserHttpTest.php:25` `assertOk()`; `:26` `assertInertia(...component('User/Create'))` | Yes |
| AC-002 | Heading `Criar usuário`, supporting text, primary `Criar usuário`, divider `Já tem uma conta?`, secondary `Ir para o login` | `resources/js/Pages/User/Create.test.jsx:50` heading; `:52` supporting text; `:54` primary button; `:55` divider; `:56` login link | Yes |
| AC-003 | Required Nome, E-mail, Senha with placeholders, autocomplete, `aria-required="true"` | `Create.test.jsx:66-76` placeholders, autocomplete, `aria-required`; `IconTextField.test.jsx:38-44` label/placeholder/autocomplete/`aria-required`; `PasswordField.test.jsx:31-34` password placeholder/autocomplete/`aria-required` | Yes |
| AC-004 | Brand wordmark `ReservaSalas` and institutional copy | `Create.test.jsx:83` ReservaSalas; `:86-91` copy; `BrandPanel.test.jsx:15-21` wordmark and copy | Yes |
| AC-005 | `Ir para o login` navigates to `/login` | `Create.test.jsx:97` `toHaveAttribute('href', '/login')` | Yes |
| AC-006 | Submit sends Inertia POST `/register` with name, email, password via `Services/users.js` | `Create.test.jsx:113` `form.post` called with `'/register'`; `users.test.js:19` `form.post` with `'/register'` | Yes |
| AC-007 | Valid POST persists, authenticates, redirects to `/reservations` | `CreateUserHttpTest.php:44` `assertRedirect(route('reservations.index'))`; `:46` `assertAuthenticated()`; `:47` `assertDatabaseCount('users', 1)`; `:55` Inertia `Reservation/Index`; `Index.test.jsx:14` heading `Reservas` | Yes |
| AC-008 | Missing name, email, or password returns a field error and does not persist | `CreateUserHttpTest.php:82` `assertSessionHasErrors([$field => $message])`; `:84` `assertDatabaseCount('users', 0)` | Yes |
| AC-009 | Backend field errors shown with `aria-invalid` and `aria-describedby` | `Create.test.jsx:181-193` name/email/password; `IconTextField.test.jsx:54-56` error id + `aria-invalid` + `aria-describedby`; `PasswordField.test.jsx:75-77` same | Yes |
| AC-010 | Duplicate email including soft-deleted row errors on `email` without another row | `CreateUserHttpTest.php:100` unique message; `:102` count 1; `:120-122` trashed count 1 | Yes |
| AC-011 | Password shorter than 8 characters errors on `password` without persist | `CreateUserHttpTest.php:135` Portuguese min message; `:137` `assertDatabaseCount('users', 0)` | Yes |
| AC-012 | While processing, primary button disabled, label `Criando usuário...`, further submits ignored | `Create.test.jsx:154` `toBeDisabled()`; `:155` original label absent; `:164` `form.post` not called | Yes |
| AC-013 | Password visibility toggle `password`/`text` with `Mostrar senha` / `Ocultar senha` | `Create.test.jsx:127-128` click; `:146-147` keyboard Enter; `PasswordField.test.jsx:46-52` click toggle; `:65-67` keyboard Enter | Yes |
| AC-014 | Validation failure keeps name/email and clears password | `Create.test.jsx:208-210` kept values and empty password; `:229` `form.reset` with `'password'`; `users.test.js:31` `form.reset('password')` | Yes |
| AC-015 | Non-validation failure shows `Não foi possível criar o usuário. Tente novamente.` in `aria-live="polite"` and keeps the form visible | `Create.test.jsx:260` `expect(httpExceptionResult).toBe(false)`; `:262-265` banner `aria-live="polite"` and form still visible; `users.test.js:42-43` `onHttpException()` / `onNetworkError()` return `false` | Yes |
| AC-016 | Wide viewport two-column card; narrow viewport one column, compact/hidden hero, full-width controls, no horizontal scroll | `Create.test.jsx:274` `lg:grid-cols-`; `:275-276` hero `hidden` + `lg:flex`; `:277-278` `w-full`; `:279` `overflow-x-hidden`; `BrandPanel.test.jsx:29-30` hero `hidden` + `lg:flex` | Yes |
| AC-017 | README documents public `/register` while login is unimplemented, and that public admin self-registration is not recommended in production | `README.md:3` public because login is unimplemented; challenge convenience; not recommended in production | Yes |
| AC-018 | Accepted email persisted trimmed and lowercased | `CreateUserHttpTest.php:196` `assertDatabaseHas(['email' => 'ada@example.com'])` after ` Ada@Example.com ` | Yes |
| AC-019 | Validation errors move focus to the first invalid field | `Create.test.jsx:245` `toHaveFocus()` on E-mail when it is the first error | Yes |
| AC-020 | Raw password not flashed or returned in error payloads/old input | `CreateUserHttpTest.php:216` password absent from `_old_input`; `:217` secret not in response content | Yes |

White-screen regression (human feedback, not a numbered AC): Inertia resolve map must not eager-import `*.test.jsx` / `*.spec.jsx`. Evidence: `resources/js/app.jsx:7-12` negated glob; `resources/js/app.test.js:11-14` source assertions. Runtime: `curl http://127.0.0.1:5173/resources/js/app.jsx` imports `Index.jsx`, `BrandPanel.jsx`, `IconTextField.jsx`, `PasswordField.jsx`, `Create.jsx` only. No `Create.test.jsx`, no `vitest`.

Existing Unit `CreateUserTest` was not weakened. `Create.test.jsx` remains 15 tests. No tests skipped, deleted, or weakened.

## Test Results

- Vitest (`npm test`): **7 files, 29 passed, 0 failed**. Previous repair had 1 file / 15 tests (`Create.test.jsx`). Added 14 tests; Create page tests unchanged (15).
  - `resources/js/app.test.js` (1)
  - `resources/js/Services/users.test.js` (3)
  - `resources/js/Pages/User/Components/BrandPanel.test.jsx` (2)
  - `resources/js/Pages/User/Components/IconTextField.test.jsx` (3)
  - `resources/js/Pages/User/Components/PasswordField.test.jsx` (4)
  - `resources/js/Pages/Reservation/Index.test.jsx` (1)
  - `resources/js/Pages/User/Create.test.jsx` (15)
- PHPUnit suites were not re-run in this repair: no PHP change.

No tests skipped, deleted, or weakened.

## Required Gates

Worker-run (harness will re-run deterministically):

| Gate | Command | Result |
| --- | --- | --- |
| frontend | `npm test` | passed (29 tests / 7 files) |
| unit | `php artisan test --testsuite=Unit` | not re-run (JS-only repair) |
| integration | `php artisan test --testsuite=Feature` | not re-run (JS-only repair) |
| lint | `vendor/bin/pint --test` | not re-run (JS-only repair) |
| build | `npm run build` | not re-run this round |

Playwright was not run (out of scope; no runner in repo).

## Review Result

Human feedback: the register screen stayed white, and several React production files had no dedicated tests.

- **White screen**: `resources/js/app.jsx` used `import.meta.glob('./Pages/**/*.jsx', { eager: true })`, so Vite compiled `Create.test.jsx` (and Vitest) into the client bootstrap. Resolve now uses `['./Pages/**/*.jsx', '!./Pages/**/*.test.jsx', '!./Pages/**/*.spec.jsx']`. The first attempted ignore (`!./**/*.test.jsx`) did not drop `Create.test.jsx` from the Vite transform; the Pages-prefixed ignore does. Colocated `*.test.jsx` files stay under Pages for Vitest (`include: resources/js/**/*.test.{js,jsx}`) but are excluded from the page map. The bootstrap regression test lives in `resources/js/app.test.js` so the page glob cannot pick it up.
- **Untested React modules**: added focused RTL tests for `BrandPanel`, `IconTextField`, `PasswordField`, `Reservation/Index`, plus `Services/users.js`. Create page tests kept.

Extra files beyond the original execute allowlist (concrete dependency):

| File | Reason |
| --- | --- |
| `resources/js/app.jsx` | Root cause of the blank `#app`: Inertia eager glob must not import tests |
| `resources/js/app.test.js` | Regression test for the resolve map; kept outside `Pages/` |
| `resources/js/Pages/User/Components/BrandPanel.test.jsx` | Focused coverage for untested production component |
| `resources/js/Pages/User/Components/IconTextField.test.jsx` | Focused coverage for untested production component |
| `resources/js/Pages/User/Components/PasswordField.test.jsx` | Focused coverage for untested production component |
| `resources/js/Pages/Reservation/Index.test.jsx` | Focused coverage for the post-register stub |
| `resources/js/Services/users.test.js` | Direct POST `/register` + password-reset + non-422 stay-on-page callbacks |

Login CRUD, reservation CRUD, and Playwright were not added.

## Final Status

Repair complete for the human-feedback white screen and missing React unit tests. Ready for harness deterministic gates and independent re-review. No commit from this worker.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `npm test` — exit=0 (required)
- ✅ `php artisan test --testsuite=Unit` — exit=0 (required)
- ✅ `php artisan test --testsuite=Feature` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
- ✅ `php artisan test` — exit=0 (required)
- ✅ `vendor/bin/pint --test` — exit=0 (required)
- ✅ `npm run build` — exit=0 (required)
<!-- harness-checks:end -->
