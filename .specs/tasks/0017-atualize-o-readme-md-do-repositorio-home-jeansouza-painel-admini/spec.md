# Specification

## Context

RNF12 requires a newcomer to prepare and run the panel from the README alone. The current `README.md` only describes `/login` and the three default administrators created by `php artisan migrate`. It does not say how to start MySQL 8 in Docker, which `.env` names must be set, how to migrate, or how to run the whole Laravel + Vite process.

Pedido do usuário (original): documentar como executar a aplicação localmente — Docker do banco, MySQL 8, variáveis de ambiente (só nomes), migrations, e como rodar a aplicação toda. README em português. Sem valores de secrets.

## Problem

A clone of this repository cannot be started from the README. Missing steps force guesswork about Compose, `.env`, and `composer` scripts.

## Problem Statement

`README.md` SHALL document, in Portuguese, a complete local-run path: Docker MySQL 8, required environment variable names without values, Laravel migrations, and the command that runs the full application — while keeping the existing login and default-administrator information.

## Goal

- A reader who has PHP 8.2+, Composer, Node.js/npm, and Docker can start MySQL 8 and the app using only `README.md`.
- Environment documentation names variables only; it SHALL NOT print secrets or Compose passwords.
- Existing default-administrator login documentation remains.

## User Stories

### P1: Start MySQL 8 from the README ⭐ MVP

**User Story**: As a developer, I want the README to tell me how to run the database in Docker as MySQL 8 so that I can migrate against the project engine.

**Why P1**: User request; RNF05 / RNF12.

**Acceptance Criteria**:

1. WHEN a reader opens the Docker section THEN `README.md` SHALL instruct `docker compose up -d` using the repo `compose.yml` and SHALL state that the database engine is MySQL 8 (Compose image `mysql:8.0`).
2. IF MySQL 8 is not running THEN `README.md` SHALL tell the reader to start Compose and wait until the service is healthy before running migrations or `composer run setup`.

**Independent Test**: Read README; the Docker section names `compose.yml`, `docker compose up -d`, MySQL 8 / `mysql:8.0`, and the wait-before-migrate rule.

### P1: Know which env names to set ⭐ MVP

**User Story**: As a developer, I want the README to list the environment variable names I must fill so that I can copy `.env.example` without guessing or leaking secrets.

**Why P1**: User request; RNF11 / RNF12.

**Acceptance Criteria**:

1. WHEN documenting environment setup THEN `README.md` SHALL tell the reader to copy `.env.example` to `.env` and run `php artisan key:generate` for `APP_KEY`, and SHALL NOT paste a key value.
2. The README SHALL list exactly these required names (and no others as required): `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. IF the README mentions environment variables THEN it SHALL NOT print values, passwords, or Compose `MYSQL_*` secrets.

**Independent Test**: README lists only those names; no `=` assignments or example passwords appear for env vars.

### P1: Migrate and run the whole app ⭐ MVP

**User Story**: As a developer, I want the README to show migrations and the full-stack run command so that I can open `http://127.0.0.1:8000` and sign in locally.

**Why P1**: User request; RNF06 / RNF12.

**Acceptance Criteria**:

1. WHEN documenting migrations THEN `README.md` SHALL instruct `php artisan migrate` after MySQL 8 is up, and SHALL NOT instruct `php artisan db:seed` as a required step.
2. WHEN documenting how to run the whole application THEN `README.md` SHALL instruct `composer run dev` (the script that starts `php artisan serve`, `queue:listen`, `pail`, and `npm run dev`) and SHALL mention the default HTTP URL `http://127.0.0.1:8000`. The run step SHALL tell the reader to open that URL and sign in with an account from the default-administrator table, and SHALL NOT require navigating to `/login` after opening the URL.
3. WHERE `composer run setup` is mentioned the README SHALL present it as an optional shortcut that still requires Docker MySQL 8 to be up first.
4. The README SHALL remain in Portuguese and SHALL keep the existing `/login` introduction and the three default administrator accounts table.

**Independent Test**: README contains `php artisan migrate`, `composer run dev`, `http://127.0.0.1:8000`, optional `composer run setup`, Portuguese prose, and the Gertrudes / Marcelo / Emerson table. The run step opens that URL and signs in; it does not add a later `/login` hop.

## Acceptance Criteria

1. WHEN a reader opens the Docker section THEN `README.md` SHALL instruct `docker compose up -d` using the repo `compose.yml` and SHALL state that the database engine is MySQL 8 (Compose image `mysql:8.0`).
2. IF MySQL 8 is not running THEN `README.md` SHALL tell the reader to start Compose and wait until the service is healthy before running migrations or `composer run setup`.
3. WHEN documenting environment setup THEN `README.md` SHALL tell the reader to copy `.env.example` to `.env` and run `php artisan key:generate` for `APP_KEY`, and SHALL NOT paste a key value.
4. The README SHALL list exactly these required names (and no others as required): `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
5. IF the README mentions environment variables THEN it SHALL NOT print values, passwords, or Compose `MYSQL_*` secrets.
6. WHEN documenting migrations THEN `README.md` SHALL instruct `php artisan migrate` after MySQL 8 is up, and SHALL NOT instruct `php artisan db:seed` as a required step.
7. WHEN documenting how to run the whole application THEN `README.md` SHALL instruct `composer run dev` (the script that starts `php artisan serve`, `queue:listen`, `pail`, and `npm run dev`) and SHALL mention the default HTTP URL `http://127.0.0.1:8000`. The run step SHALL tell the reader to open that URL and sign in with an account from the default-administrator table, and SHALL NOT require navigating to `/login` after opening the URL.
8. WHERE `composer run setup` is mentioned the README SHALL present it as an optional shortcut that still requires Docker MySQL 8 to be up first.
9. The README SHALL remain in Portuguese and SHALL keep the existing `/login` introduction and the three default administrator accounts table.

## Edge Cases

- IF the reader runs `composer run setup` or `php artisan migrate` before Compose is healthy THEN those commands SHALL be documented as failing until MySQL 8 accepts connections.
- IF `.env` already exists THEN `composer run setup` SHALL be documented as not overwriting it (script copies only when `.env` is missing); `APP_KEY` still needs to exist.
- IF the reader looks for `docker-compose` (hyphen) THEN README SHALL use Compose V2 `docker compose` against `compose.yml`.
- IF the reader expects a PHP/Vite container THEN README SHALL state that only MySQL runs in Docker; the app runs on the host.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Editing `.env.example`, `compose.yml`, or product/test code | User asked for README; example file already matches Compose names |
| Documenting `.env.testing` / PHPUnit / Playwright | Not a local-run request; no Playwright runner |
| `php artisan db:seed` as a required step | `DatabaseSeeder` is empty; admins come from migrate |
| Dockerizing PHP, queue, or Vite | `compose.yml` has only `mysql` |
| Printing env or Compose secret values | User forbid values |
| Changing default-admin emails/passwords | Already shipped; keep the table |
| New CONTRIBUTING.md or extra how-to files | RNF12 is the README |
| Inventing Node/PHP patch versions | Repo pins PHP `^8.2` only |

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Language of README | Portuguese | User request | n |
| Files to change | `README.md` only | User scope; `.env.example` is consistent | n |
| Required env names | The seven names in AC-4 | Human repair: list only `APP_KEY` and `DB_*`; do not list `APP_URL`, `HASH_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` as required | n |
| Env values in README | Names only; generate `APP_KEY` | User forbid values | n |
| Full-app command | `composer run dev` | Existing `composer.json` `dev` script | n |
| Setup shortcut | Optional `composer run setup` after Docker is healthy | Same file; migrate needs MySQL | n |
| Keep login + admin table | Yes | Already documents post-migrate login | n |
| Punctual tests | None at unit / integration / e2e | Docs-only; see test strategy docs | n |

**Open questions:** none — all resolved or logged above.

## Considered Approaches

1. **Rewrite `README.md` in Portuguese with local-run sections; keep login/admin table** — selected. Matches the user request and RNF12. No extra files.
2. **Add `CONTRIBUTING.md` or `docs/setup.md`** — rejected. RNF12 and the user ask for the repository README.
3. **Dockerize the whole stack** — rejected. Out of scope; Compose only provides MySQL 8.
4. **Add a PHP/Vitest test that parses README headings** — rejected. That is not a unit, integration, or e2e behavior under `docs/test/*`.

## Selected Approach

Update `README.md` only. Add Portuguese sections for prerequisites, Docker MySQL 8, environment variable **names**, migrations, optional `composer run setup`, and `composer run dev`. Keep the existing login paragraph and default-administrator table. Print no env values.

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| README-01 | P1: Start MySQL 8 | Execute | Implemented |
| README-02 | P1: Start MySQL 8 | Execute | Implemented |
| README-03 | P1: Env names | Execute | Implemented |
| README-04 | P1: Env names | Execute | Implemented |
| README-05 | P1: Env names | Execute | Implemented |
| README-06 | P1: Migrate and run | Execute | Implemented |
| README-07 | P1: Migrate and run | Execute | Implemented |
| README-08 | P1: Migrate and run | Execute | Implemented |
| README-09 | P1: Migrate and run | Execute | Implemented |

**Coverage:** 9 total, 9 mapped to tasks, 0 unmapped.

## Success Criteria

- [x] A reader can follow `README.md` from clone to `composer run dev` without other docs.
- [x] MySQL 8 via Docker is explicit.
- [x] Required env names are listed; no values appear.
- [x] Login and the three default accounts remain.
