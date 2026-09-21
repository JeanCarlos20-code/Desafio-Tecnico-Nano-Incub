# Progress

## Plan

## Execute

## Review

## Repair

## Integration

- Task created for: Sim. E conferindo o código atual: reunião passada não é removida automaticamente da listagem.

Hoje o comportamento é:

period=all → traz reservas antigas e futuras, desde que não estejam canceladas.
today, tomorrow, week ou intervalo → filtra pelo starts_at.
reserva cancelada → some da listagem porque o repository tem whereNull('cancelled_at').
o período padrão é all.

Então o problema não são as reuniões que já aconteceram. É especificamente o histórico das canceladas.

Plano enxuto para corrigir

Eu faria assim:

Adicionar filtro status na listagem
all
active
cancelled
Remover o whereNull('cancelled_at') fixo do listPage().
Passar status pelo fluxo
IndexReservationRequest
IndexReservationController
ListReservations
ReservationRepository::listPage()
Aplicar no repository
active → whereNull('cancelled_at')
cancelled → whereNotNull('cancelled_at')
all → não filtra por cancelled_at
Corrigir o status enviado ao React
Hoje você força tudo para:
'status' => 'active',
'status_label' => 'Ativa',

Passaria a ser baseado em cancelledAt:

cancelledAt != null → Cancelada
cancelledAt == null → Ativa
Adicionar um select no frontend

Algo no mesmo bloco de filtros:

Status
[ Todas     v ]

Todas
Ativas
Canceladas

e manter status junto dos outros query params.

Testes mínimos
all mostra ativa + cancelada.
active mostra somente ativa.
cancelled mostra somente cancelada.
cancelar uma reserva não faz mais ela desaparecer quando o filtro for all.
reserva cancelada continua liberando o horário.

Eu não adicionaria agora completed/concluída, a menos que você queira deixar mais sofisticado. Para o desafio, Ativa/Cancelada resolve exatamente a falha que vimos sem aumentar o escopo.

Se quisesse adicionar depois, seria um estado derivado:

cancelled_at != null       -> cancelada
ends_at < agora            -> concluída
senão                       -> ativa

Mas eu evitaria mexer nisso agora porque você já enviou o teste. Quanto menor e mais segura a correção, melhor.

<!-- event:worker-note-1790001080773536805 -->
- [2026-09-21 11:31:20 -0300] [PLAN] Root cause: listPage whereNull cancelled_at plus hardcoded Ativa; past meetings already listed on period=all

<!-- event:worker-note-1790001080842308531 -->
- [2026-09-21 11:31:20 -0300] [PLAN] Selected status filter all/active/cancelled on listPage only; occupancy unchanged; no completed state

<!-- event:worker-note-1790001080908597600 -->
- [2026-09-21 11:31:20 -0300] [PLAN] Tests: PHP+React unit and Feature HTTP; e2e N/A (no Playwright). Screen doc must drop hide-on-cancel

<!-- event:worker-note-1790001080976570643 -->
- [2026-09-21 11:31:20 -0300] [PLAN] Artifacts written and validate_spec/validate_tasks passed; complete-phase next

<!-- event:plan-ready-0 -->
- [2026-09-21 11:31:24 -0300] Plan, spec, and tests are ready for human approval.

<!-- event:plan-revision-2484881140193456834 -->
- [2026-09-21 11:37:35 -0300] User requested plan changes.

<!-- event:worker-note-1790001588480780823 -->
- [2026-09-21 11:39:48 -0300] [PLAN] Revising plan: keep period/range; status all|active|cancelled only; drop stale past-meetings radio

<!-- event:worker-note-1790001670679053057 -->
- [2026-09-21 11:41:10 -0300] [PLAN] Code: listPage and hasAny hard-hide cancelled; period radios and starts_on/ends_on stay; no yesterday preset

<!-- event:worker-note-1790001726130774777 -->
- [2026-09-21 11:42:06 -0300] [PLAN] Plan revised: status all|active|cancelled only; period/range unchanged; radio rejected

<!-- event:plan-ready-3375414848424881407 -->
- [2026-09-21 11:42:06 -0300] Plan, spec, and tests are ready for human approval.

<!-- event:plan-revision-43929505211187819 -->
- [2026-09-21 11:45:20 -0300] User requested plan changes.

<!-- event:worker-note-1790002043721594664 -->
- [2026-09-21 11:47:23 -0300] [PLAN] Default status=active (human); rooms code still defaults to all — leave rooms unchanged

<!-- event:worker-note-1790002143539869797 -->
- [2026-09-21 11:49:03 -0300] [PLAN] Spec+tasks written: status all/active/cancelled, default active, e2e N/A

<!-- event:plan-ready-8515217355576757732 -->
- [2026-09-21 11:49:03 -0300] Plan, spec, and tests are ready for human approval.

<!-- event:plan-approved -->
- [2026-09-21 11:52:08 -0300] Plan approved by the user; implementation is unblocked.

<!-- event:worker-note-1790002360989482403 -->
- [2026-09-21 11:52:40 -0300] [EXECUTE] Execute started: listing status filter T1-T5

<!-- event:worker-note-1790002656002181594 -->
- [2026-09-21 11:57:36 -0300] [EXECUTE] T1-T5 implemented; running required gates

<!-- event:worker-note-1790002740116240136 -->
- [2026-09-21 11:59:00 -0300] [EXECUTE] T1-T5 complete; worker gates green; validation.md written

<!-- event:execute-complete -->
- [2026-09-21 11:59:05 -0300] Initial implementation finished; starting deterministic checks.

<!-- event:checks-0-0 -->
- [2026-09-21 11:59:43 -0300] Required checks are green; starting Review.

<!-- event:worker-note-1790002818962650380 -->
- [2026-09-21 12:00:18 -0300] [REVIEW] Início da review round-1: carregando spec, tasks, políticas e diff da worktree 0028.

<!-- event:worker-note-1790002822764890048 -->
- [2026-09-21 12:00:22 -0300] [REVIEW] Tracks em execução: architecture, security, smells, tests. Corpus = dirty da rodada 1.

<!-- event:worker-note-1790002921626809016 -->
- [2026-09-21 12:02:01 -0300] [REVIEW] Tracks architecture/security/smells/tests: evidência lida; gravando JSON no runtime round-1.

<!-- event:worker-note-1790002938952752313 -->
- [2026-09-21 12:02:18 -0300] [REVIEW] Review round-1 consolidada: APPROVED, 0 blocker/high, 1 medium (SMELL-001). complete-phase em seguida.

<!-- event:review-1 -->
- [2026-09-21 12:02:19 -0300] Review round 1 wrote review-01.md: APPROVED; blockers/high: 0.

<!-- event:code-revision-8673234782932621040 -->
- [2026-09-21 12:06:50 -0300] User requested code changes before commit.

<!-- event:worker-note-1790003420925199069 -->
- [2026-09-21 12:10:20 -0300] [REPAIR] Human feedback: keep status filter; default period today

<!-- event:worker-note-1790003425273588993 -->
- [2026-09-21 12:10:25 -0300] [REPAIR] Controller/UI default period=today; Limpar filtros restores today

<!-- event:repair-1 -->
- [2026-09-21 12:10:48 -0300] Repair finished (repair-1); checks will run again.

<!-- event:checks-1-1 -->
- [2026-09-21 12:11:25 -0300] Required checks are green; starting Review.

<!-- event:worker-note-1790003527514548186 -->
- [2026-09-21 12:12:07 -0300] [REVIEW] Iniciando review round 2: carregando políticas, spec e corpus dirty∪carried

<!-- event:worker-note-1790003531108556951 -->
- [2026-09-21 12:12:11 -0300] [REVIEW] Tracks architecture, security, smells e tests em execução no corpus dirty∪carried

<!-- event:worker-note-1790003579891339631 -->
- [2026-09-21 12:12:59 -0300] [REVIEW] Revalidando SMELL-001 (ainda presente) e buscando blocker/high novos; sem lista nova de mediums

<!-- event:review-2 -->
- [2026-09-21 12:13:30 -0300] Review round 2 wrote review-02.md: APPROVED; blockers/high: 0.

<!-- event:commit-approved -->
- [2026-09-21 12:16:27 -0300] Commit and integration authorized by the user.
