# Context strategy

## Memórias separadas

1. Conversation memory: não atravessa fronteira de fase.
2. Task memory: `.specs/tasks/NNNN-slug/*.md` em **inglês** (headings e corpo gerado pelo harness). Packets, CLI e instruções de agente podem permanecer em português.
3. Repository knowledge: `docs/` + código, consultados sob demanda.
4. Runtime state: `.git/harness/` (checkpoint/action/packets/review JSON); não entra no commit.

## PLAN

PLAN paga o custo maior de investigação. Recebe apenas um índice de `docs/`, seleciona arquivos relevantes e investiga código. `context.md` é o handoff comprimido.

## EXECUTE

Começa por context/spec/tasks. Só relê código citado ou dependências concretas. Não refaz discovery amplo.

## REVIEW

Começa por spec/tasks/status/diff/checks. Carrega políticas de architecture/security/smells/tests e contexto adjacente somente para provar findings.

## REPAIR

Recebe blockers/high, checks vermelhos e feedback humano. Não recebe o histórico conversacional das outras fases.

`progress.md` nunca é adicionado automaticamente a um ContextPacket.
