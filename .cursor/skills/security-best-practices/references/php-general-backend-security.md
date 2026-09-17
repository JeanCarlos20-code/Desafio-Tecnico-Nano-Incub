# PHP General Backend Security Spec (PHP 8.2+)

This document is designed as a **security spec** that supports:

1. **Secure-by-default code generation** for new PHP code.
2. **Security review / vulnerability hunting** in existing PHP code.

When the project uses Laravel, **also load** `php-laravel-web-server-security.md` and prefer Laravel built-ins over the raw-PHP patterns below.

It is written as normative requirements (`MUST`/`SHOULD`/`MAY`) plus audit rules.

---

## 0) Safety, boundaries, and anti-abuse constraints (MUST FOLLOW)

- MUST NOT request, output, log, or commit secrets (passwords, API keys, `.env` values, session IDs, private keys).
- MUST NOT “fix” security by disabling protections (`display_errors` in production, `@` error suppression around auth, `allow_url_include`, turning off CSRF in a cookie-session app).
- MUST provide evidence-based findings: file path + line + snippet.
- MUST treat uncertainty honestly when a control lives in nginx/php-fpm/infra.

---

## 1) Untrusted input

Treat as attacker-controlled unless proven otherwise:

- `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_FILES`, `$_SERVER` (`HTTP_*`, `QUERY_STRING`)
- JSON/body parsed from the client
- path/query parameters
- uploaded filenames and MIME types claimed by the client
- data loaded from the database that originated from users

---

## 2) Rules

### PHP-INJECT-001: Parameterized queries only

Severity: Critical

- MUST use prepared statements / bound parameters (PDO, mysqli prepared, or a framework query builder).
- MUST NOT concatenate untrusted strings into SQL.

Insecure: `"SELECT * FROM users WHERE email = '$email'"`, `"WHERE id = " . $_GET['id']`.

Fix: bound parameters. In Laravel, prefer Eloquent / query builder (see the Laravel spec).

### PHP-EXEC-001: No dynamic code or untrusted unserialize

Severity: Critical

- MUST NOT use `eval`, `assert` with strings, `preg_replace` `/e`, `create_function`, or `unserialize` on untrusted data.
- MUST NOT use `include`/`require` with a path derived from user input.

### PHP-XSS-001: Escape on output

Severity: High

- MUST escape untrusted data for the output context (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` for HTML).
- MUST NOT echo user input into HTML/JS/attributes raw.
- In Blade/Inertia React, prefer framework auto-escaping and never `{!! !!}` / `dangerouslySetInnerHTML` for untrusted content.

### PHP-UPLOAD-001: Untrusted files

Severity: High

- MUST store uploads outside the public web root or behind a download controller.
- MUST NOT trust `$_FILES['x']['type']` or the original filename as a path.
- MUST generate a server-side name; validate size and an allowlist of extensions/content.

### PHP-SECRET-001: Configuration

Severity: High

- MUST keep credentials in environment / secret manager, not in source.
- MUST set `display_errors=Off` in production.
- MUST NOT commit `.env`.

### PHP-HASH-001: Passwords

Severity: Critical

- MUST hash passwords with a slow algorithm (`password_hash` / Argon2 / bcrypt). Never SHA-1/MD5/plain.
- MUST verify with `password_verify` (or the framework hasher). Never compare plaintext to a stored hash with `==`.

### PHP-ID-001: Public identifiers

Severity: Medium

- SHOULD NOT expose auto-increment integers as the only public ID of a guessable resource.
- SHOULD use UUID (prefer time-ordered UUID v7 when the framework provides it) for public IDs.
