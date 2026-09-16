# Create User Specification

## Problem Statement

A migration `create_users_table` já define o schema de `users` (UUID, name, email único, password, remember_token, timestamps). O modelo e a factory ainda carregam `email_verified_at`, coluna que não existe. Sem uma operação de criação alinhada a essa tabela, persistir administrador ou cadastro grava o formato errado.

## Goals

- [x] Persistência de usuário usa exatamente as colunas de `create_users_table`
- [x] Password nunca é gravada em texto puro
- [x] Email duplicado e campos obrigatórios ausentes são rejeitados sem gravar linha

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------ |
| Tela HTTP / Inertia de cadastro | RNF07 manda starter kit oficial; Breeze/Inertia ainda não estão no projeto |
| Login, sessão e middleware de rotas internas | RF01 e RF02; feature de autenticação |
| Verificação de e-mail | Coluna `email_verified_at` não existe na migration |
| Edição, exclusão e listagem de usuários | Não pedido; schema só cobre persistir criação |
| Papéis além de administrador | ADR-001 fecha o escopo em RF01–RF19 |
| Seeder RF19 | Feature própria; esta entrega só deixa a criação utilizável |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here - nothing is left silently unclear.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Superfície da criação | Action de aplicação `CreateUser`, sem rota HTTP | Pedido aponta o schema da tabela; não há starter kit de auth instalado | n |
| Geração de id | Trait `HasUuids` (UUIDv7 do Laravel 12) | A migration usa `$table->uuid('id')->primary()` | y |
| Campos gravados | Só `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at` | Igual a `create_users_table` | y |
| Password | Cast `hashed` do modelo; regra `Password::defaults()` (mínimo 8) | RNF10; default do Laravel 12 | n |
| `remember_token` na criação | Permanece nulo se o caller não informar | Coluna existe para sessão futura, não para o cadastro | n |
| Email duplicado | `ValidationException` na chave `email`; unique no banco | Validação no servidor (RNF08) + constraint da migration | n |
| Campos ausentes / email inválido | `ValidationException` na chave do campo | Form Request não se aplica sem HTTP; `Validator` na Action | n |
| Autorização da Action | Sem check de auth na Action | Usada por seeder e, depois, por controller autenticado | n |
| Concorrência no email | Unique index `users.email` | A migration já declara `unique()` | n |
| Dimensões restantes (rate limit, TTL, observabilidade, falha externa) | N/A | Sem HTTP, sem serviço externo, sem expiração de conta neste escopo | n |

**Open questions:** none - all resolved or logged above.

---

## User Stories

### P1: Criar usuário no schema de `users` ⭐ MVP

**User Story**: As a aplicação, I want persistir um usuário com name, email e password no formato da tabela `users` so that o cadastro e o seeder gravem o mesmo contrato.

**Why P1**: Sem isso, factory e modelo divergem da migration e a criação quebra ou grava coluna inexistente.

**Acceptance Criteria** (each line is one EARS pattern):

1. WHEN name, email e password válidos são fornecidos THEN o sistema SHALL persistir uma linha em `users` com esses valores de name e email
2. WHEN o usuário é persistido THEN o sistema SHALL atribuir um `id` UUID
3. WHEN o usuário é persistido THEN o sistema SHALL armazenar `password` em hash diferente do texto puro e verificável com o texto original
4. The system SHALL persistir somente as colunas `id`, `name`, `email`, `password`, `remember_token`, `created_at` e `updated_at`
5. IF `name`, `email` ou `password` estiver ausente THEN o sistema SHALL lançar `ValidationException` na chave do campo ausente sem persistir registro
6. IF o email já existir em `users` THEN o sistema SHALL lançar `ValidationException` na chave `email` sem persistir registro adicional
7. IF `password` tiver menos de 8 caracteres THEN o sistema SHALL lançar `ValidationException` na chave `password` sem persistir registro
8. IF `email` não for um endereço válido THEN o sistema SHALL lançar `ValidationException` na chave `email` sem persistir registro
9. WHEN `remember_token` não é informado na criação THEN o sistema SHALL persistir `remember_token` nulo

**Independent Test**: Chamar `CreateUser` com dados válidos e ler a linha em `users`; repetir com email duplicado e password curta e confirmar que a tabela não ganha linha.

---

## Edge Cases

- IF name, email ou password estiver ausente THEN o sistema SHALL rejeitar sem persistir
- IF email duplicado THEN o sistema SHALL rejeitar sem persistir registro adicional
- IF password tiver 7 caracteres THEN o sistema SHALL rejeitar; 8 caracteres válidos SHALL persistir
- IF o input incluir `email_verified_at` THEN o sistema SHALL ignorar esse campo e persistir só colunas da tabela

---

## Requirement Traceability

Each requirement gets a unique ID for tracking across design, tasks, and validation.

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| USER-01 | P1: persistir name e email | Execute | Verified |
| USER-02 | P1: id UUID | Execute | Verified |
| USER-03 | P1: password hasheada | Execute | Verified |
| USER-04 | P1: somente colunas da tabela | Execute | Verified |
| USER-05 | P1: campos obrigatórios | Execute | Verified |
| USER-06 | P1: email único | Execute | Verified |
| USER-07 | P1: password mínimo 8 | Execute | Verified |
| USER-08 | P1: email válido | Execute | Verified |
| USER-09 | P1: remember_token nulo | Execute | Verified |

**ID format:** `USER-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 9 total, 9 mapped via Execute (Tasks skipped), 0 unmapped

---

## Success Criteria

How we know the feature is successful:

- [x] `CreateUser` grava um usuário alinhado a `create_users_table`
- [x] Password persistida não é texto puro
- [x] Email duplicado e input inválido não criam linha
