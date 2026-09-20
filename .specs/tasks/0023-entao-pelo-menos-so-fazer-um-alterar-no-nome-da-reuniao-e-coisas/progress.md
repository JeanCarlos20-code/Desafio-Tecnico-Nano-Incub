# Progress

## Plan

## Execute

## Review

## Repair

## Integration

- Task created for: então pelo menos só fazer um alterar no nome da reunião e coisas surpefluas, data e hora não são alteraveis
Recorte claro: editar só o que não mexe em ocupação (título e responsável); data e hora travadas. Vou abrir isso no Harness. basciamente é alterar nome da reunião e outros que não envolva data e hora

Recorte OBRIGATÓRIO (não ampliar)
Editável:
- title (nome/finalidade da reunião)
- responsible (responsável)

NÃO editável (travado na UI e recusado no servidor se vier no payload):
- starts_at, ends_at (data e hora)
- room_id, participants (ocupação — não são “superfluos”; mudá-los reabre RF13–RF18)
- cancelled_at

Só reserva ativa (cancelled_at null). Cancelada: 404 ou erro claro; não edita.

Não implementar overlap/duração/lock no update (esses campos não mudam). Sem edição de data/hora “para herdar RF13–RF18”.

O que entregar
- Rotas autenticadas: GET /reservations/{reservation}/edit, PUT /reservations/{reservation}
- Use case UpdateReservation (title + responsible), FormRequest, controller fino, módulo Reservation
- Tela Inertia no estilo do create/rooms edit; campos de sala/horário/participantes somente leitura (mostrar o valor atual, disabled)
- Ação na listagem de reservas (link Editar) além do Cancelar
- ADR novo (009) MADR PT: supersede só a fatia “não editar reserva” do ADR-005; ocupação continua imutável no update. ADR-001: se precisar, só nota de extra (o desafio não pede edição); NÃO reescrever a decisão do catálogo RF. Skill create-adr: não editar decisão antiga.
- README: edição parcial existe (título/responsável); data/hora/sala/participantes não. “Altere essa reunião” da redução de capacidade AINDA não reduz participantes por esta tela (precisa cancelar+recriar ou reduzir só o título). Deixar isso explícito.
- screen nova ou extensão de screen-reservation-create / list

Testes (obrigatório)
Unit
- UpdateReservation persiste title/responsible e não toca starts_at/ends_at/room_id/participants
- Recusa reserva cancelada
- Recusa reserva inexistente
- FormRequest: title/responsible required; payload com starts_at/room_id/participants é ignorado ou rejeitado (escolher uma e testar)

Integration Feature/MySQL
- PUT autenticado atualiza só title/responsible; row de ocupação inalterada
- Guest 302
- Cancelada 404
- GET edit Inertia com campos travados
- Listagem tem link de editar

Vitest
- Form de edit: inputs title/responsible; data/hora/sala/participantes disabled ou não submetidos
- Index: link Editar

E2E: N/A sem Playwright.

Harness
PLAN → gate humano (colar summary na íntegra: plano + testes pontuais + comandos). Não pular gates. Não commitar. Não mexer em schema (já tem as colunas).

Idioma: português.

<!-- event:worker-note-1789934676307811924 -->
- [2026-09-20 17:04:36 -0300] [PLAN] Packet lido; iniciando Specify + Tasks no recorte title/responsible

<!-- event:worker-note-1789934799966426133 -->
- [2026-09-20 17:06:39 -0300] [PLAN] Recorte: title+responsible; ocupação imutável; ADR-009; e2e N/A

<!-- event:worker-note-1789934897222866670 -->
- [2026-09-20 17:08:17 -0300] [PLAN] Artefatos validados; complete-phase plan

<!-- event:plan-ready-0 -->
- [2026-09-20 17:08:17 -0300] Plan, spec, and tests are ready for human approval.

<!-- event:plan-approved -->
- [2026-09-20 17:15:03 -0300] Plan approved by the user; implementation is unblocked.

<!-- event:worker-note-1789935426005054805 -->
- [2026-09-20 17:17:06 -0300] [EXECUTE] T1-T2: adding UpdateReservation port write and use-case unit tests

<!-- event:worker-note-1789935594647205820 -->
- [2026-09-20 17:19:54 -0300] [EXECUTE] T3-T6: HTTP, FormRequest, Edit page, and list Editar action are in place

<!-- event:worker-note-1789935660509929371 -->
- [2026-09-20 17:21:00 -0300] [EXECUTE] T7 docs recorded; local checks green; ready for harness gates

<!-- event:execute-complete -->
- [2026-09-20 17:21:03 -0300] Initial implementation finished; starting deterministic checks.

<!-- event:checks-0-0 -->
- [2026-09-20 17:21:40 -0300] Required checks are green; starting Review.

<!-- event:worker-note-1789935741088698559 -->
- [2026-09-20 17:22:21 -0300] [REVIEW] Review round 1: políticas carregadas; inspecionando corpus dirty

<!-- event:worker-note-1789935833287748332 -->
- [2026-09-20 17:23:53 -0300] [REVIEW] Tracks architecture, security, smells e tests em execução no corpus dirty

<!-- event:worker-note-1789935851669434587 -->
- [2026-09-20 17:24:11 -0300] [REVIEW] Tracks limpos; consolidated APPROVED/complete

<!-- event:review-1 -->
- [2026-09-20 17:24:12 -0300] Review round 1 wrote review-01.md: APPROVED; blockers/high: 0.

<!-- event:commit-approved -->
- [2026-09-20 17:32:28 -0300] Commit and integration authorized by the user.
