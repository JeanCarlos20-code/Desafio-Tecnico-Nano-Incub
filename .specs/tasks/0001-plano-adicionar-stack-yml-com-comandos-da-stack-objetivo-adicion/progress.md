# Progress — 0001

- Task criada para: # Plano — adicionar `stack.yml` com comandos da stack

## Objetivo

Adicionar `harness/stack.yml` para descrever a stack do projeto e os comandos disponíveis por componente.

O core do harness deve continuar neutro:
- entende componentes, linguagens, frameworks, testing e commands;
- não possui lógica específica de PHP, Laravel, Go, Angular etc.

## Exemplo de `harness/stack.yml`

```yaml
project:
  name: desafio-salas
  type: fullstack

components:
  - name: backend
    root: backend
    type: backend

    languages:
      - php

    frameworks:
      - laravel

    data_access:
      - eloquent

    testing:
      - pest

    commands:
      unit:
        command: php artisan test --testsuite=Unit

      integration:
        command: php artisan test --testsuite=Feature

      test:
        command: php artisan test

      lint:
        command: ./vendor/bin/pint --test

      build:
        command: php artisan optimize

  - name: frontend
    root: frontend
    type: frontend

    languages:
      - typescript

    frameworks:
      - angular

    testing:
      - vitest
      - playwright

    commands:
      unit:
        command: npm test

      integration:
        command: npm run test:integration

      e2e:
        command: npm run e2e

      test:
        command: npm test

      lint:
        command: npm run lint

      build:
        command: npm run build

infrastructure:
  databases:
    - postgresql

  caches: []
  queues: []
  messaging: []
```

Implementação
Criar harness/stack.yml.
Criar modelos tipados:
Project
Component
Command
Infrastructure
ProjectStack
Não usar Any.
Criar StackLoader para:
localizar harness/stack.yml;
carregar YAML;
validar estrutura;
retornar ProjectStack.
commands deve ser genérico.
A chave pode ser unit, integration, e2e, test, lint, build ou qualquer outro identificador.
O core não deve conhecer esses nomes de forma hardcoded.
Cada comando deve rodar usando o root do componente como cwd.

Exemplo:

component: backend
root: backend
command: test

→ executa `php artisan test` dentro de `backend/`
Integrar ProjectStack ao ContextPacket do Planner.
O Planner deve receber um resumo compacto contendo:
projeto;
componentes;
roots;
linguagens;
frameworks;
data access;
testing;
commands disponíveis;
infraestrutura.
O Planner pode referenciar IDs dos comandos em tasks.md.

Exemplo:

Backend gates:
- unit
- integration
- lint
- build

Frontend gates:
- unit
- lint
- build
Adicionar ao harness.yml:
verify:
  required:
    - test
    - lint
    - build
No verify:
identificar os componentes afetados;
procurar os comandos exigidos em verify.required;
executar apenas nos componentes afetados;
registrar resultados no validation.md.
Se um comando obrigatório não existir para um componente, retornar erro claro.
Não inferir comandos pela linguagem/framework.
Não alterar o fluxo de:
worktree;
plan;
HITL;
execute;
review;
repair;
commit/merge.
Testes

Adicionar testes para:

stack válida;
múltiplos componentes;
infraestrutura vazia;
comando customizado;
execução usando o root correto;
comando obrigatório inexistente;
YAML inválido;
arquivo inexistente;
stack disponível no Planner;
ausência de lógica específica de tecnologia no core.
Regra

stack.yml define:

o que existe no projeto;
como os comandos são executados.

harness.yml define:

quais comandos são obrigatórios para o workflow.

O erro anterior foi só visual mesmo. Esse aqui está com os blocos fechados certinho.

faça esse plano, e evite o uso de any no python, vc deverá fazer na pasta harness e lembre-se o harness no python n deve ter a linguagem hardcoded o yml da stack serve para mandar isso

<!-- event:worker-note-1789671229761350901 -->
- [2026-09-17 15:53:49 -0300] [PLAN] Investigação: core neutro sem stack.yml; CheckRunner usa gates do tasks.md no cwd da worktree; harness/ untracked (worktree sem o Python)

<!-- event:worker-note-1789671377449969261 -->
- [2026-09-17 15:56:17 -0300] [PLAN] Artefatos context/spec/tasks escritos; validate_spec e validate_tasks verdes; ArtifactService aceita o plano

<!-- event:plan-ready-0 -->
- [2026-09-17 15:56:21 -0300] Planejamento/spec/testes prontos para aprovação humana.

<!-- event:plan-approved -->
- [2026-09-17 15:59:27 -0300] Plano aprovado pelo usuário; implementação liberada.

<!-- event:worker-note-1789671726313670210 -->
- [2026-09-17 16:02:06 -0300] [EXECUTE] harness/ copiado para a worktree; tasks.md válido; início T1

<!-- event:worker-note-1789672125055916549 -->
- [2026-09-17 16:08:45 -0300] [EXECUTE] T1-T4 verdes; início T5 stack.yml + e2e

<!-- event:worker-note-1789672198371784754 -->
- [2026-09-17 16:09:58 -0300] [EXECUTE] T1-T5 implementados; pytest 40 passed localmente; complete-phase execute

<!-- event:execute-complete -->
- [2026-09-17 16:10:08 -0300] Implementação inicial concluída; iniciando checks determinísticos.

<!-- event:checks-0-0 -->
- [2026-09-17 16:10:21 -0300] Checks obrigatórios verdes.

<!-- event:worker-note-1789672506959832866 -->
- [2026-09-17 16:15:06 -0300] [REVIEW] Tracks architecture/security/smells/tests em execução (round-1).

<!-- event:worker-note-1789672537720363990 -->
- [2026-09-17 16:15:37 -0300] [REVIEW] consolidated.json válido: REJECTED (ARCH-001 blocker, ARCH-002/TEST-001 high).

<!-- event:review-1 -->
- [2026-09-17 16:15:38 -0300] Review round 1: REJECTED; blockers/high: 3.

<!-- event:worker-note-1789672684671743388 -->
- [2026-09-17 16:18:04 -0300] [REPAIR] repair iniciado: ARCH-001/002 e TEST-001 (stack.yml na raiz primária + ignored no matching)

<!-- event:worker-note-1789672939892724495 -->
- [2026-09-17 16:22:19 -0300] [REPAIR] ARCH-001/002 e TEST-001 corrigidos; pytest local 47 passed

<!-- event:repair-1 -->
- [2026-09-17 16:22:23 -0300] Repair round 1 concluído; checks serão executados novamente.

<!-- event:checks-1-1 -->
- [2026-09-17 16:22:42 -0300] Checks obrigatórios verdes.

<!-- event:worker-note-1789673103986045872 -->
- [2026-09-17 16:25:03 -0300] [REVIEW] Tracks architecture/security/smells/tests: revalidei ARCH-001, ARCH-002 e TEST-001; todos resolvidos; nenhum blocker/high novo.

<!-- event:review-2 -->
- [2026-09-17 16:25:06 -0300] Review round 2: APPROVED; blockers/high: 0.

<!-- event:commit-approved -->
- [2026-09-17 16:26:53 -0300] Commit e integração autorizados pelo usuário.
