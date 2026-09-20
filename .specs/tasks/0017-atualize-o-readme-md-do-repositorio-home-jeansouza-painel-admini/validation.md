# Validation

## Acceptance Criteria

Human repair changed only the run step. Open `http://127.0.0.1:8000` and sign in with a table account. Do not require a later `/login` hop. GET `/` and GET `/login` both open login. Spec AC-7 / README-07 and T2 now state that rule.

| AC | Spec outcome | Evidence | Status |
| -- | ------------ | -------- | ------ |
| README-01 | `docker compose up -d` on `compose.yml`; engine MySQL 8 / `mysql:8.0` | `README.md:26` names `compose.yml` and `mysql:8.0`; `README.md:31` is `docker compose up -d` | Met |
| README-02 | Start Compose and wait until healthy before migrate or `composer run setup` | `README.md:34` | Met |
| README-03 | Copy `.env.example` to `.env`; `php artisan key:generate` for `APP_KEY`; no key value | `README.md:38-42` | Met |
| README-04 | Exactly these required names, no others as required: `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `README.md:46-54` lists those seven names only | Met |
| README-05 | No env values, passwords, or Compose `MYSQL_*` secrets | Env section lists names only; no `=` assignments; no `MYSQL_*` | Met |
| README-06 | `php artisan migrate` after MySQL 8 is up; `db:seed` is not required | `README.md:58-66` | Met |
| README-07 | `composer run dev` (`serve`, `queue:listen`, `pail`, `npm run dev`); `http://127.0.0.1:8000`; open that URL and sign in; no later `/login` hop | `README.md:81-86` | Met |
| README-08 | `composer run setup` optional; Docker MySQL 8 must be up first | `README.md:70-74` | Met |
| README-09 | Portuguese prose; keep `/login` intro and Gertrudes / Marcelo / Emerson table | `README.md:3-11` | Met |

The `/login` introduction stays on `README.md:3`. The run step on `README.md:86` is `Abra http://127.0.0.1:8000 e entre com uma das contas da tabela acima.` It does not say `entre em /login`.

SMELL-001 (medium) was not repaired. Repair scope forbids opening medium findings.

## Test Results

`harness.tests` is empty at unit, integration, and e2e. That matches `docs/test/unit.md`, `docs/test/integration.md`, and `docs/test/e2e.md`: README prose is not an Application use case, FormRequest, Laravel HTTP+MySQL flow, or browser workflow.

No punctual tests were added. Existing product tests were not edited, skipped, or weakened.

## Required Gates

Repair changed documentation and 0017 spec/tasks only. Local product `test`, `lint`, and `build` commands were not re-run here. Final harness gates run after this phase. This report does not declare those harness gates green.

## Review Result

Round 2 verdict was `APPROVED`. No structured blockers or high findings. No required check was red. Human feedback asked to drop the extra `/login` hop from the run step and to update spec/tasks if they still required `entre em /login` there.

## Final Status

README run step opens `http://127.0.0.1:8000` and signs in with a table account. Spec AC-7 and T2 match that rule. Scope stayed `README.md` plus this task's spec/tasks/validation. `.env.example`, `compose.yml`, and product/test code were not changed. No commit.

<!-- harness-checks:start -->
## Harness deterministic checks

- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
<!-- harness-checks:end -->
