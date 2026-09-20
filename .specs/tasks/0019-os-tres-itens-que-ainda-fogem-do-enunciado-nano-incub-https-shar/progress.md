# Progress

## Plan

## Execute

## Review

## Repair

## Integration

- Task created for: Os três itens que ainda fogem do enunciado Nano Incub (https://shared.nanoincub.com.br/p/ccf8f75a-67b7-4aa9-aeb8-db6d09ab161e-desafio-tecnico-dev-salas/index.html) devem ser resolvidos. O usuário AUTORIZA mudar ADRs e migrations: rooms e reservations passam a id autoincremento; UUID v7 permanece SÓ no usuário.

## 1. Listagens com coluna ID + troca de identidade

Enunciado:
- Salas: ID, Nome, Capacidade, Situação, Data de criação
- Reservas: ID, Sala, Responsável, Título, Início, Fim, Participantes, Situação

Hoje as UIs ESCONDEM o ID (decisão de screen/task antiga, contra o enunciado):
- `resources/js/Pages/Room/Index.test.jsx` — "does not display the room id"
- `resources/js/Pages/Reservation/Index.test.jsx` — `queryByRole('columnheader', { name: 'ID' })` not in document
- `docs/screens/screen-rooms-list.md` diz para NÃO mostrar o id
- `docs/screens/screen-reservations-list.md` JÁ pede coluna ID

Decisão do usuário: `id` de `rooms` e `reservations` vira bigint unsigned autoincrement (`$table->id()`). `reservations.room_id` vira `foreignId` (não mais `foreignUuid`). Usuário (`users.id`) CONTINUA UUID v7 (ADR-002 intocado na decisão de identidade).

ADRs (skill create-adr): ADRs são imutáveis — NÃO reescrever a decisão do ADR-004/005. Criar ADR novo (008) MADR em português, data 2026-09-20, supersede da fatia UUID de rooms/reservations. Em ADR-004 e ADR-005, só atualizar Status/supersede da identidade; o resto do contrato (colunas de negócio, soft delete em rooms, cancelled_at em reservations) permanece. ADR-001 e ADR-002 não mudam o texto de decisão (users UUID).

Schema: como o projeto ainda não tem produção, PREFERIR editar as migrations de create (`2026_09_18_120000_create_rooms_table.php`, `2026_09_18_180000_create_reservations_table.php`) para `$table->id()` / `foreignId`, e a migration de demo `2026_09_20_000000_insert_demo_rooms_and_reservations.php` para não gerar UUID. Remover `HasUuids` dos models Room e Reservation. Atualizar factories, Domain entities se tiparem id como string, rotas/model binding, testes de schema que assertam UUID v7 nibble `7`.

UI: coluna `ID` nas tabelas Room/Index e Reservation/Index, mostrando o id persistido. Atualizar screens + Vitest (inverter as asserções que proíbem ID). Situação das reservas já existe como Status; conferir se o header precisa dizer Situação ou se Status no screen atende o enunciado (enunciado diz Situação). Salas: enunciado Situação — a UI hoje usa Status; alinhar ao enunciado se for barato, senão documentar no plano.

## 2. README seção 05 do desafio

Além do setup já existente, o README DEVE ter, em português:
- Decisões técnicas e o porquê, incluindo o trade-off de overlap: hoje a checagem é na aplicação (regras RF13–RF18) + persistência com `lockForUpdate` na sala (RNF09 / ADR-006). Explicar por que os dois, não só app e não só unique index.
- O que ficou de fora e o que faria com mais tempo (cadastro público, edição de reserva, calendário, e-mail, etc. — o que o ADR-001 já corta).
- Declaração de uso de IA: Cursor (agentes) usado para planejar/implementar/testar; o autor responde pelo código. Sem inventar outras ferramentas.

Sem secrets. Sem CONTRIBUTING.md.

## 3. Seeder também cria administrador (RF19 literal)

`DatabaseSeeder` / um `UserSeeder` deve garantir pelo menos um (preferir os três já conhecidos: Gertrudes/Marcelo/Emerson, emails teste@mail.com etc., senha Senha123, Argon) via firstOrCreate por email — idempotente com a migration de admins. Atualizar `test_database_seeder_does_not_insert_additional_administrators` se ainda proibir qualquer insert: o seeder PODE “criar” os admins se a migrate não os tiver, mas NÃO deve criar `test@example.com` nem um 4º admin. Após migrate+seed: exatamente 3 users.

## Testes

- Unit: FormRequests inalterados salvo se tipos de id mudarem.
- Integration Feature/MySQL: schema rooms/reservations id incrementing integer; demo catalog e seeder; HTTP que usa room/reservation ids.
- Vitest: coluna ID visível nas duas listagens.
- E2E: N/A sem Playwright.

## Harness

PLAN → gate humano → EXECUTE → REVIEW. Não pular gates. Não commitar até o usuário autorizar.

<!-- event:worker-note-1789927494674243596 -->
- [2026-09-20 15:04:54 -0300] [PLAN] Packet lido; kind=agent phase=plan; investigando identidade, README e seeder

<!-- event:worker-note-1789927638579795145 -->
- [2026-09-20 15:07:18 -0300] [PLAN] Contrato: bigint em rooms/reservations; UUID só em users; README 05; UserSeeder firstOrCreate

<!-- event:plan-ready-0 -->
- [2026-09-20 15:09:14 -0300] Plan, spec, and tests are ready for human approval.

<!-- event:plan-approved -->
- [2026-09-20 15:12:03 -0300] Plan approved by the user; implementation is unblocked.

<!-- event:worker-note-1789927971934631297 -->
- [2026-09-20 15:12:51 -0300] [EXECUTE] Execute started: Phase 1 contract (ADR-008, supersede, screens)

<!-- event:worker-note-1789928249845280737 -->
- [2026-09-20 15:17:29 -0300] [EXECUTE] T4-T8 implemented: bigint ids, list ID/Situação, UserSeeder, README §05

<!-- event:worker-note-1789928287557537030 -->
- [2026-09-20 15:18:07 -0300] [EXECUTE] Worker checks green: Feature 113, Unit 49, Vitest 95, pint+eslint

<!-- event:execute-complete -->
- [2026-09-20 15:18:08 -0300] Initial implementation finished; starting deterministic checks.

<!-- event:checks-0-0 -->
- [2026-09-20 15:18:56 -0300] Required checks are green; starting Review.

<!-- event:worker-note-1789928379509152704 -->
- [2026-09-20 15:19:39 -0300] [REVIEW] Review round 1: políticas carregadas; iniciando tracks architecture/security/smells/tests

<!-- event:worker-note-1789928498578768703 -->
- [2026-09-20 15:21:38 -0300] [REVIEW] Tracks em execução: architecture, security, smells, tests — corpus lido, sem findings blocker/high preliminares

<!-- event:worker-note-1789928523696531061 -->
- [2026-09-20 15:22:03 -0300] [REVIEW] Tracks architecture/security/smells/tests GATE PASS; consolidated APPROVED

<!-- event:review-1 -->
- [2026-09-20 15:22:04 -0300] Review round 1 wrote review-01.md: APPROVED; blockers/high: 0.

<!-- event:commit-approved -->
- [2026-09-20 15:29:33 -0300] Commit and integration authorized by the user.
