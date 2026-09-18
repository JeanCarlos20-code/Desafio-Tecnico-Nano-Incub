# STATE

## Decisions

### AD-001
- **Decision**: Backend modules live under `app/Modules/<Module>/{Domain,Application,Infra}` as defined in `docs/tree.md` and `docs/architecture.md`
- **Reason**: Domain stays pure PHP; Laravel stays in Infra; future features (salas, reservas) reuse the same boundary
- **Trade-off**: We leave the Laravel default `app/Models` layout
- **Scope**: all backend modules
- **Date**: 2026-09-16
- **Status**: active

## Handoff

- **Feature**: create-user / `.specs/features/create-user/`
- **Phase / Task**: Execute complete; Verifier PASS
- **Completed**: T1–T9; validation.md PASS (13/13 ACs, sensor 3/3 killed)
- **In-progress**: none
- **Next step**: commits git (adiados pelo usuário)
- **Blockers**: none
- **Uncommitted files**: módulo User HTTP+Inertia, Form Request, testes, `.specs/features/create-user/*`
- **Branch**: local git exists; commits ainda adiados
