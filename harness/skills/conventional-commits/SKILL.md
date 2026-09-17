---
name: conventional-commits
description: "Creates atomic GitHub Conventional Commits as type(module): English message. Types are feat, fix, chore, and test. Splits production and tests per module, and splits oversized file blocks. Use before any git commit in this repo, including the harness commit node, plan frontmatter, and when the user asks to commit."
---

# Conventional Commits

Before `git commit`, follow this skill. The harness commit node also follows it — do not dump the whole worktree into one commit.

## Format

```
type(module): message
```

- `type` is one of `feat`, `fix`, `chore`, `test` — nothing else.
- `module` is the **product module** (example: `User` → `user`). It is **not** a folder or layer (`Application`, `Domain`, `Infra`, `Unit`, `Feature`, `Http`, `Database`).
- Use a folder/area name **only** when the file is outside every product module (`harness`, `docs`, `specs`, `cursor`, shared Laravel kernel).
- `message` is English, imperative, lowercase start, no trailing period.

Examples:

```
feat(user): add create-user use case
test(user): cover create-user use case
fix(user): reject duplicate email on unique index
chore(harness): add stack catalog loader
```

## Split rules

Each commit is **one module** and **one kind**:

| Kind | `type` | What goes in |
| ---- | ------ | ------------ |
| production | `feat` / `fix` / `chore` | implementation for that module only |
| tests | `test` | tests for that same module only |

Never mix:

- two modules in one commit;
- production and tests in one commit;
- harness/docs/specs with product code;
- more than **8 files** in one commit.

If one module still has many files after the rules above, **split that module too**. Prefer grouping by subdirectory (`harness/src`, `harness/skills`, `app/Modules/User/Domain`, …). If files sit in the same folder, chunk them in batches of 8. Keep the same `type(module):` scope; write a distinct English message per chunk (`group_commits.py` reports `area` for this).

`harness.commits` must list **one message per group**, including extra chunks of the same module.

Order: production for a module (split chunks first), then `test` for that module (also split if oversized), then `chore` for docs/specs/cursor/harness leftovers.

## How to name `module`

The scope is the **module**, not the directory you edited.

Inside a module, Domain, Application, Infra, HTTP, Inertia pages, and that module's tests share the same scope:

| Path | Scope |
| ---- | ----- |
| `app/Modules/User/Domain/...` | `user` |
| `app/Modules/User/Application/...` | `user` |
| `app/Modules/User/Infra/...` | `user` |
| `tests/Unit/User/...`, `tests/Feature/User/...` | `user` |
| `resources/js/Pages/User/...` | `user` |

Do **not** write `feat(application):`, `feat(infra):`, `test(unit):`, or `fix(http):` for those files.

Only when the change is **outside** every `app/Modules/<Name>` (and its tests/pages), use the area:

| Path | Scope |
| ---- | ----- |
| `harness/` | `harness` |
| `docs/` | `docs` |
| `.specs/` | `specs` |
| `.cursor/` | `cursor` |
| `app/Http/` (Laravel kernel, not a module) | `http` |
| `database/` | `database` |
| file at repo root (`foo.txt`) | file stem (`foo`) |

## Harness

Planner writes the list in `tasks.md` frontmatter (`harness.commits`). Execute does **not** commit. After the human commit gate, the harness commit node groups dirty paths with this skill and creates one git commit per group.

Validate every message:

```bash
python3 "$SKILL_DIR/scripts/check_commit.py" --message "feat(user): add create-user use case"
```

Preview groups before committing:

```bash
python3 "$SKILL_DIR/scripts/group_commits.py" --root <worktree>
```

`$SKILL_DIR` is the directory that contains this `SKILL.md`.

## Git safety

- Never `git add -A` when more than one group exists.
- Never `--amend`, `--no-verify`, force-push, or skip hooks.
- Never commit `.env`, credentials, or `__pycache__`.
