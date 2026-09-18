# Laravel (PHP) Web Security Spec (Laravel 11/12, PHP 8.2+)

This document is designed as a **security spec** that supports:

1. **Secure-by-default code generation** for new Laravel (and Inertia) backend code.
2. **Security review / vulnerability hunting** in existing Laravel code (passive while editing, and active scan/report).

Also load `php-general-backend-security.md`. For Inertia + React UI, also load `javascript-typescript-react-web-frontend-security.md`.

It is written as normative requirements (`MUST`/`SHOULD`/`MAY`) plus audit rules.

**Project overrides (Painel Administrativo):** ADR-001 (scope RF/RNF only), ADR-002 (UUID v7, soft delete, `HASH_DRIVER=argon`, Laravel auth — no custom hasher/JWT/roles), ADR-003 (one Laravel app; React via Inertia; no public REST as the UI contract). Those ADRs win over generic “add JWT / Sanctum SPA / extra roles” advice.

---

## 0) Safety, boundaries, and anti-abuse constraints (MUST FOLLOW)

- MUST NOT request, output, log, or commit secrets (`APP_KEY`, DB passwords, session cookies, hashes, tokens).
- MUST NOT “fix” security by disabling protections (`APP_DEBUG=true` in production, `ValidateCsrfToken` / `VerifyCsrfToken` / `PreventRequestForgery` excepted on cookie-session routes, `$guarded = []` with `$request->all()`, raw SQL string interpolation).
- MUST prefer Laravel built-ins over custom security: session auth, Form Requests, Eloquent (bound parameters), `Hash` / `hashed` cast, middleware `auth`. ([Laravel hashing][1], [Eloquent mass assignment][2], [validation][3])
- MUST NOT invent authentication (JWT, homemade sessions, custom password algorithms). RNF07: official starter kit / Laravel auth.
- MUST provide evidence-based findings: path + line + snippet.
- MUST treat uncertainty honestly if a control lives in nginx / Forge / hosting.

---

## 1) Operating modes

### 1.1 Generation mode (default)

- MUST follow every **MUST** below.
- MUST use Form Requests (or `$request->validate`) on HTTP writes; pass `$request->validated()` into use cases, never `$request->all()` into `Model::create`.
- MUST keep Domain free of `Illuminate` (architecture); hashing and persistence stay in Infra.

### 1.2 Passive review mode

While editing Laravel/Inertia PHP: notice critical violations in touched code and mention a minimal Laravel-native fix.

### 1.3 Active audit mode

When the user asks to scan/audit:

1. `.env.example` vs secrets in git; `APP_DEBUG`; `APP_KEY`.
2. Auth: models, guards, middleware on internal routes (RF01/RF02).
3. Password hashing (`HASH_DRIVER`, `hashed` cast, no plaintext).
4. CSRF on `web` / Inertia POST/PUT/PATCH/DELETE.
5. Form Requests, `$fillable`, mass assignment.
6. Raw SQL / `whereRaw` / `DB::select` string concat.
7. Authorization vs IDOR (UUID still must be authorized).
8. Logging of passwords/tokens.
9. Concurrency on reservation overlap (RNF09) if that code exists.
10. Inertia: no public JSON API as the UI contract (ADR-003).

Finding format: Rule ID, severity, location (`file:line`), evidence, impact, fix, false-positive notes.

---

## 2) Untrusted input

- `$request->all()`, query, route params, JSON body, uploaded files
- headers (`X-Forwarded-*`, `Origin`, `Host`)
- cookies / session (tamper-evident but not a substitute for authz)
- Inertia props that originated from the client
- persisted user content (names, titles, room names)

---

## 3) Rules

### LARAVEL-AUTH-001: Use Laravel session auth

Severity: Critical (if a custom scheme is introduced)

Required:

- MUST authenticate administrators with Laravel’s session guard / official starter kit (Breeze or equivalent). ([Laravel hashing / starter kits][1])
- MUST NOT add JWT, homemade tokens, or a second login stack for the panel UI.
- MUST protect internal routes with `auth` (or equivalent) — RF02.

Insecure: custom `AuthController` that sets cookies by hand; `tymon/jwt-auth` for this app; API tokens as the only UI auth.

Fix: starter kit + `Route::middleware('auth')` around panel routes.

### LARAVEL-HASH-001: Argon via Laravel hasher

Severity: Critical

Required:

- MUST store passwords only as hashes. RNF10 is eliminatory.
- MUST use Laravel’s hasher (`HASH_DRIVER=argon` in this project; Argon2i via the `argon` driver) and the Eloquent `hashed` cast — not a custom class, not `md5`/`sha1`, not bcrypt-by-hand unless ADR-002 is superseded. ([Laravel hashing][1], [hashed cast][4])
- MUST verify with `Hash::check` / the attempt APIs, never `===` against plaintext.

Insecure: `'password' => $request->password` without hash; `Hash::make` with a local `password_hash` wrapper that changes parameters; logging `$request->password`.

Detection: search `password` assignments, `HASH_DRIVER`, `casts(): hashed`, `md5(`, `sha1(`.

Fix: `HASH_DRIVER=argon` in `.env.example`; model `password => hashed`; Form Request `Password::defaults()`.

### LARAVEL-ID-001: UUID public IDs

Severity: Medium / High for guessable admin resources

Required:

- MUST NOT use auto-increment integers as the public primary key for users (ADR-002: UUID v7 via `HasUuids`).
- MUST still authorize by ownership/role: UUID is not a secret capability.

Insecure: `$table->id()` on `users` after ADR-002; sequential IDs in URLs without authz.

### LARAVEL-MASS-001: Mass assignment

Severity: High

Required:

- MUST define `$fillable` (or equivalent Fillable attribute) on Eloquent models. Laravel blocks mass assignment until you do. ([Eloquent][2])
- MUST NOT set `$guarded = []` on models that take HTTP input.
- MUST NOT pass `$request->all()` into `create`/`fill`/`update`.

Insecure: `User::create($request->all())` (attacker adds extra columns if fillable is too wide, e.g. a future `is_admin`).

Fix: Form Request `validated()` → use case → repository; fillable only `name`, `email`, `password` on User.

### LARAVEL-VALID-001: Server-side validation

Severity: High

Required:

- MUST validate state-changing HTTP input in Form Requests (RNF08). Frontend checks are UX only.
- MUST use `$request->validated()` / `safe()` in controllers. ([validation][3])
- SHOULD reject unknown fields when the framework supports it (`FailOnUnknownFields` on newer Laravel).

Insecure: trusting Inertia form state; validating only in React.

### LARAVEL-SQL-001: No interpolated SQL

Severity: Critical

Required:

- MUST use Eloquent / query builder bindings.
- MUST NOT concatenate untrusted values into `whereRaw`, `DB::select`, `orderByRaw`, `selectRaw`.

Insecure: `whereRaw("email = '$email'")`, `orderBy($request->sort)` without an allowlist.

Fix: `where('email', $email)`; allowlist sort columns.

### LARAVEL-CSRF-001: Keep CSRF on the web stack

Severity: High

Required:

- MUST keep CSRF verification on cookie-session / Inertia mutations (`web` middleware). Inertia uses the XSRF cookie/header with Laravel. ([Sanctum SPA CSRF pattern][5]; same cookie idea applies to Inertia on the same origin.)
- MUST NOT `withoutMiddleware` CSRF on panel POST/PUT/PATCH/DELETE except documented webhooks with another authenticator.

Insecure: disabling CSRF “because Inertia”; excepting `*` from CSRF.

Note: this project is same-origin Inertia, not a separate SPA+API. Do not introduce Sanctum SPA + CORS as the default.

### LARAVEL-AUTHZ-001: Authorize after authenticate

Severity: High

Required:

- MUST NOT treat “knows the UUID” as authorization.
- MUST check the authenticated user may perform the action (policies/gates or explicit admin-only, matching RF01 — single administrator role; do not invent extra roles).

### LARAVEL-DEBUG-001: Production debug

Severity: High (production)

- MUST keep `APP_DEBUG=false` in production. Debug pages leak env, queries, and keys.
- `php artisan serve` / `APP_DEBUG=true` is fine locally; only flag production/Docker CMD that ships debug on.

### LARAVEL-SECRET-001: Env files

Severity: Critical

- MUST NOT commit `.env`. Keep `.env.example` without real secrets (RNF11).
- MUST NOT log `APP_KEY`, database URLs, or session payloads.

### LARAVEL-LOG-001: Sensitive data in logs

Severity: High

- MUST NOT log passwords, remember tokens, full session cookies, or password hashes.
- MUST NOT dump `$request->all()` when it contains `password`.

### LARAVEL-LOCK-001: Reservation overlap (when that module exists)

Severity: High (RNF09 / RF13)

- MUST prevent two concurrent requests from creating overlapping active reservations on the same room (transaction + lock or equivalent DB constraint).
- MUST NOT rely only on a read-then-write check without a lock.

### LARAVEL-INERTIA-001: No extra public API

Severity: Medium (scope / attack surface)

- MUST serve UI via Inertia responses (pages + props), not a parallel REST contract for the same screens (ADR-003).
- MUST NOT add CORS + token auth “for the React app”.

### LARAVEL-SERIAL-001: Dangerous PHP sinks

Severity: Critical

- MUST NOT `unserialize` user input, `eval` request data, or `include` user-controlled paths.

---

## 4) Audit checklist (Laravel panel)

- [ ] Internal routes behind `auth`
- [ ] Passwords: `hashed` cast + `HASH_DRIVER=argon` (this project)
- [ ] User PK is UUID, not bigint
- [ ] Form Requests + `validated()` on writes
- [ ] `$fillable` tight; no `$request->all()` into Eloquent
- [ ] No raw SQL interpolation
- [ ] CSRF still on web/Inertia mutations
- [ ] `.env` gitignored; `APP_DEBUG` off in prod
- [ ] No password/PII in logs
- [ ] Reservation writes serialized if the feature exists

---

## 5) References

```text
- Laravel hashing (Bcrypt/Argon, HASH_DRIVER): https://laravel.com/docs/hashing
- Eloquent mutators (`hashed` cast): https://laravel.com/docs/eloquent-mutators
- Eloquent mass assignment: https://laravel.com/docs/eloquent#mass-assignment
- Validation / Form Requests: https://laravel.com/docs/validation
- CSRF / Blade @csrf: https://laravel.com/docs/csrf
- Authentication: https://laravel.com/docs/authentication
- Inertia Laravel (same-origin, CSRF/session): https://inertiajs.com/
```

[1]: https://laravel.com/docs/hashing "Laravel hashing"
[2]: https://laravel.com/docs/eloquent#mass-assignment "Eloquent mass assignment"
[3]: https://laravel.com/docs/validation "Laravel validation"
[4]: https://laravel.com/docs/eloquent-mutators "hashed cast"
[5]: https://laravel.com/docs/sanctum#csrf-protection "CSRF cookie pattern"
