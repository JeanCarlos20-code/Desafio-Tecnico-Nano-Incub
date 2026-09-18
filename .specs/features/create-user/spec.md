# Create User Specification

## Problem Statement

O módulo User já persiste o contrato do ADR-002, mas falta a camada HTTP do `docs/tree.md` (controller, Form Request, página Inertia). A validação de input ainda está no use case; a decisão do produto é validar no Form Request do controller.

## Goals

- [x] Criar usuário persiste exatamente o contrato do ADR-002
- [x] Password é hasheada com o driver `argon` do Laravel, nunca em texto puro
- [x] POST /users rejeita input inválido e email duplicado sem gravar linha extra, via Form Request
- [x] GET /users/create renderiza a página Inertia `User/Create`
- [x] Use case `CreateUser` não valida input; só persiste

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------ |
| Login, sessão e middleware `auth` | RF01 e RF02; login ainda não existe |
| `Pages/User/Index.jsx` e `Edit.jsx` | Listagem e edição não são criação |
| Hooks | Tree só cria Hook com comportamento React reutilizável |
| Verificação de e-mail | ADR-002 exclui `email_verified_at` |
| Papéis além de administrador | ADR-001 |
| Seeder RF19 | Feature própria |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here - nothing is left silently unclear.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Onde validar | Form Request do controller (`StoreUserRequest`) | Decisão do usuário; pasta `Requests/` do tree; controller fino | y |
| Use case | Só chama o repositório | Sem `InvalidUserInput` / `DuplicateEmail` | y |
| Email duplicado (soft-deleted) | `Rule::unique('users', 'email')` na tabela | Unique do banco inclui `deleted_at`; não usar unique no Model (ignora trash) | y |
| HTTP | `GET /users/create`, `POST /users` | Inertia, não API JSON | y |
| Auth na rota | Sem `auth` | Login ainda não existe | n |
| Password mínima | 8 caracteres (`Password::defaults()`) | Default Laravel | n |
| Redirect pós-sucesso | `users.create` | Não há Index | n |
| Dimensões restantes (rate limit, TTL, observabilidade, falha externa) | N/A | Sem serviço externo; sem expiração | y |

**Open questions:** none - all resolved or logged above.

---

## User Stories

### P1: Criar usuário no módulo User ⭐ MVP

**User Story**: As a aplicação, I want criar um administrador com name, email e password no contrato do ADR-002 so that o seeder e o POST HTTP gravem o mesmo agregado User.

**Why P1**: Persistência alinhada ao ADR-002.

**Acceptance Criteria** (each line is one EARS pattern):

1. WHEN name, email e password válidos são fornecidos ao use case THEN o sistema SHALL persistir uma linha em `users` com esses valores de name e email
2. WHEN o usuário é persistido THEN o sistema SHALL atribuir um `id` UUID
3. WHEN o usuário é persistido THEN o sistema SHALL armazenar `password` em hash Argon2i diferente do texto puro e verificável com o texto original
4. The system SHALL persistir somente as colunas `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at` e `deleted_at`
5. WHEN `remember_token` não é informado na criação THEN o sistema SHALL persistir `remember_token` nulo
6. WHEN o usuário é persistido THEN o sistema SHALL persistir `deleted_at` nulo

**Independent Test**: Chamar o use case com dados válidos e ler a linha em `users`.

---

### P2: HTTP de criação com validação no Form Request

**User Story**: As an administrador, I want um formulário Inertia e um POST validado no controller so that eu cadastre usuário sem regra de input no use case.

**Why P2**: Completa `tree.md` Infra/Http e `resources/js`; decisão de validação na camada HTTP.

**Acceptance Criteria**:

1. WHEN `GET /users/create` THEN o sistema SHALL responder 200 com a página Inertia `User/Create`
2. WHEN `POST /users` com name, email e password válidos THEN o sistema SHALL persistir a linha e redirecionar para `users.create`
3. IF `POST /users` omitir `name`, `email` ou `password` THEN o sistema SHALL responder com erro de validação na chave do campo sem persistir registro
4. IF `POST /users` enviar email já existente em `users`, inclusive com `deleted_at` preenchido, THEN o sistema SHALL responder com erro de validação na chave `email` sem persistir registro adicional
5. IF `POST /users` enviar `password` com menos de 8 caracteres THEN o sistema SHALL responder com erro de validação na chave `password` sem persistir registro
6. IF `POST /users` enviar `email` inválido THEN o sistema SHALL responder com erro de validação na chave `email` sem persistir registro
7. The system SHALL validar name, email, password e unicidade no Form Request do controller, não no use case `CreateUser`

**Independent Test**: GET da página Inertia; POST válido persiste; POST inválido e email duplicado (ativo e soft-deleted) não persistem.

---

## Edge Cases

- IF name, email ou password estiver ausente no POST THEN o sistema SHALL rejeitar sem persistir
- IF email duplicado no POST THEN o sistema SHALL rejeitar sem persistir registro adicional
- IF o email pertencer a usuário soft-deleted THEN o sistema SHALL rejeitar como duplicado no POST
- IF password tiver 7 caracteres no POST THEN o sistema SHALL rejeitar; 8 caracteres válidos SHALL persistir
- IF o input incluir `email_verified_at` THEN o sistema SHALL ignorar esse campo e persistir só colunas do ADR-002

---

## Requirement Traceability

Each requirement gets a unique ID for tracking across design, tasks, and validation.

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| USER-01 | P1: persistir name e email | T6 | Verified |
| USER-02 | P1: id UUID | T5 | Verified |
| USER-03 | P1: password Argon2i | T2, T5, T6 | Verified |
| USER-04 | P1: somente colunas do ADR-002 | T1, T3 | Verified |
| USER-05 | P2: campos obrigatórios no POST | T8 | Verified |
| USER-06 | P2: email único no POST (inclui soft-deleted) | T8 | Verified |
| USER-07 | P2: password mínimo 8 no POST | T8 | Verified |
| USER-08 | P2: email válido no POST | T8 | Verified |
| USER-09 | P1: remember_token nulo | T6 | Verified |
| USER-10 | P1: deleted_at nulo na criação | T1, T6 | Verified |
| USER-11 | P2: GET Inertia User/Create | T8, T9 | Verified |
| USER-12 | P2: POST válido persiste e redireciona | T8 | Verified |
| USER-13 | P2: validação no Form Request, não no use case | T7, T8 | Verified |

**ID format:** `USER-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 13 total, 13 mapped to tasks, 0 unmapped

---

## Success Criteria

How we know the feature is successful:

- [x] `CreateUser` grava um usuário alinhado ao ADR-002
- [x] Password persistida é Argon2i, não texto puro
- [x] POST /users valida no Form Request e não persiste input inválido
- [x] GET /users/create abre a página Inertia `User/Create`
- [x] Use case não contém regras de required/email/password/unique
