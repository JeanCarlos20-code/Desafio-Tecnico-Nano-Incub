# Create User Context

**Gathered:** 2026-09-16
**Spec:** `.specs/features/create-user/spec.md`
**Status:** Ready for design

---

## Feature Boundary

Completar a criação de usuário com a árvore de `docs/tree.md`: HTTP (controller + Form Request), página Inertia de create, layout, service e `app.jsx`. Validação de input não fica no use case. Index, Edit e Hooks vazios ficam de fora.

---

## Implementation Decisions

### Onde validar

- Validação de name, email, password e unicidade de email vive no Form Request usado pelo controller (`Infra/Http/Requests`).
- O use case `CreateUser` só persiste. Não lança `InvalidUserInput` nem `DuplicateEmail`.
- Unique index no banco continua como rede de concorrência.
- Form Request (não `validate()` solto no método) preenche `tree.md` `Requests/` e mantém o controller fino, como em `docs/architecture.md`.

### Superfície HTTP

- `GET /users/create` renderiza Inertia `User/Create`.
- `POST /users` valida, chama o use case e redireciona para `users.create`.
- Sem middleware `auth` nesta entrega: login (RF01) ainda não existe.

### Front do tree.md

- Criar `Pages/User/Create.jsx`, `Layouts/AppLayout.jsx`, `Services/users.js`, `app.jsx`.
- Não criar `Index.jsx`, `Edit.jsx` nem `Hooks/` vazios.

### Agent's Discretion

- Labels da página em português.
- Redirect de sucesso de volta para o formulário de criação (não há listagem).

### Declined / Undiscussed Gray Areas → Assumptions

- Auth na rota: sem auth até existir login.
- Rate limit: N/A (sem requisito).

---

## Specific References

Validação no controller/HTTP, não no use case. Completar o que falta no `tree.md` para esta tarefa.

---

## Deferred Ideas

- `Pages/User/Index.jsx` e `Edit.jsx` — listagem e edição.
- Middleware `auth` quando RF01 existir.
- Hooks — só quando houver comportamento React reutilizável.
