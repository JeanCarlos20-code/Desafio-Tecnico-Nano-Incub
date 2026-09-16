# Arquitetura do backend Go

O backend do Projeto Encontrar Trabalhadores é um **monólito modular em Go**.

Todos os módulos fazem parte da mesma aplicação e são implantados juntos. Cada módulo representa uma área do negócio e deve manter seus limites internos.

## Árvore principal

```text
backend/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── modules/
│   │   └── {module}/
│   │       ├── core/
│   │       │   ├── entity/
│   │       │   ├── repository/
│   │       │   ├── usecase/
│   │       │   └── errors/
│   │       │
│   │       └── infra/
│   │           ├── database/
│   │           │   └── queries/
│   │           ├── validation/
│   │           ├── handler/
│   │           └── route/
│   │
│   ├── shared/
│   └── bootstrap/
│
├── pkg/
├── sql/
│   ├── migrations/
│   └── queries/
│       └── {module}/
├── docs/
│   ├── adr/
│   └── modules/
│       └── {module}/
├── harness/
├── tests/
├── go.mod
└── Dockerfile
```

Módulos atuais:

```text
internal/modules/
├── auth/
├── location/
├── file/
├── service_category/
├── service_request/
└── worker/
```

Crie módulos e pastas apenas quando houver uma responsabilidade concreta. Não crie estruturas vazias antecipadamente.

## Direção das dependências

A direção obrigatória é:

```text
infra → core
```

O `core` nunca pode importar a `infra`.

Fluxo esperado de uma operação:

```text
Route
  ↓
Handler
  ↓
Use case
  ↓
Interface de repositório
  ↓
Implementação do repositório
  ↓
sqlc
  ↓
pgx
  ↓
PostgreSQL
```

## Core

O `core` contém regras e conceitos do negócio.

```text
core/
├── entity/
├── repository/
├── usecase/
└── errors/
```

### Entity

Contém entidades e regras relacionadas ao próprio conceito do domínio.

Entidades não devem conhecer:

```text
HTTP
JSON
PostgreSQL
pgx
sqlc
validator
infraestrutura
```

Entidades não devem ser apenas cópias das tabelas do banco.

### Repository

Contém interfaces necessárias para o core acessar persistência ou integrações.

Utilize preferencialmente uma interface por agregado ou conceito principal.

Não crie uma interface para cada rota ou caso de uso.

As interfaces não devem expor estruturas do sqlc, pgx ou detalhes do PostgreSQL.

### Use case

Contém as operações do módulo.

Exemplo:

```text
create_service_request.go
get_service_request.go
cancel_service_request.go
```

Casos de uso são estruturas concretas e utilizam preferencialmente um método:

```go
Execute(ctx context.Context, input Input)
```

O caso de uso coordena:

```text
regras de negócio
entidades
repositórios
autorizações do domínio
transações
```

Não crie interfaces para casos de uso sem necessidade real.

### Errors

Contém erros reconhecíveis do domínio.

Erros do core não devem conhecer códigos HTTP.

## Infra

A `infra` conecta o módulo a tecnologias externas.

```text
infra/
├── database/
├── validation/
├── handler/
└── route/
```

### Database

Contém:

```text
implementação dos repositórios
consultas SQL
uso do sqlc
mapeamento entre banco e entidades
```

O código gerado pelo sqlc é um detalhe da infraestrutura e não pode ser utilizado diretamente no core.

O acesso ao banco utiliza:

```text
PostgreSQL
PostGIS
pgx/v5
pgxpool
sqlc
```

Não utilize ORM.

### Validation

Contém DTOs de entrada HTTP e validações estruturais.

A validação HTTP pode verificar formatos, campos obrigatórios, tamanhos, UUIDs e valores permitidos.

Regras de negócio permanecem no core.

### Handler

O handler deve:

```text
ler a requisição
validar a entrada
chamar o caso de uso
mapear o resultado
responder em HTTP
```

O handler não deve:

```text
executar SQL
acessar o banco diretamente
implementar regras de negócio
alterar entidades sem caso de uso
```

DTOs simples de resposta podem permanecer no arquivo do handler.

Não crie interfaces para handlers.

### Route

Contém o registro das rotas HTTP.

Cada módulo deve possuir um arquivo `routes.go` para agregar suas rotas.

Rotas não devem conter regras de negócio.

Não crie interfaces para rotas.

## Convenção por operação

Quando fizer sentido, utilize o mesmo nome da operação nas diferentes partes do módulo:

```text
core/usecase/create_service_request.go
infra/database/create_service_request.go
infra/validation/create_service_request.go
infra/handler/create_service_request.go
infra/route/create_service_request.go
```

Nem toda operação precisa possuir arquivos em todas as pastas.

## Shared

`internal/shared` contém apenas recursos técnicos compartilhados, como:

```text
conexão PostgreSQL
gerenciamento de transações
respostas HTTP
middlewares
validator
logs
métricas
```

Não coloque conceitos de negócio em `shared`.

## Bootstrap

`internal/bootstrap` contém a composição manual das dependências.

```text
Repository → Use case → Handler → Route
```

Não utilize Google Wire nesta etapa.

## Observabilidade HTTP

O log de acesso é responsabilidade do **middleware HTTP**, não de cada caso de uso ou handler.

Toda requisição registra:

```text
method
route
status
duration
request_id
error code (quando houver)
```

Status `200` / `401` / `404` / `409` / `500` (e os demais) ficam cobertos automaticamente. `5xx` deve ser rastreável por `request_id` e código de erro, sem PII.

Por padrão **não** adicione log interno em use case, handler ou rota. Só com necessidade concreta:

- segurança;
- diagnóstico;
- integração externa;
- operação assíncrona;
- falha não explicada pelo log HTTP.

Log `Debug` em todo caso de uso **não é obrigatório**. A IA não decide “onde precisa log” caso a caso: segue este padrão.

## Regras obrigatórias

Ao gerar ou modificar código:

1. Identifique o módulo responsável.
2. Respeite `infra → core`.
3. Mantenha regras de negócio no core.
4. Não utilize sqlc ou pgx dentro do core.
5. Não acesse o banco diretamente pelo handler ou caso de uso.
6. Não crie interfaces sem benefício concreto.
7. Não coloque domínio em `shared`.
8. Não crie pastas vazias.
9. Componha dependências no `bootstrap`.
10. Não transforme os módulos em microserviços.
11. Não altere a arquitetura sem uma decisão explícita.
12. Observabilidade HTTP é do middleware: `method`, `route`, `status`, `duration`, `request_id` e código de erro quando houver. Não invente log de acesso no caso de uso, handler ou rota.
13. Por padrão não adicione log interno. Só segurança, diagnóstico, integração externa, operação assíncrona ou falha não explicada pelo HTTP. Log `Debug` em todo use case não é obrigatório.
14. Nunca registre senhas, tokens, cookies, hashes sensíveis ou PII desnecessária em logs.

## JSON: referências por `id`

Quando um campo da API apontar para outra entidade, use objeto com `id` — não campo plano `*_id`.

Singular (1 referência):

```json
"role": { "id": 2 }
"category": { "id": 1 }
"address": { "id": "uuid-do-endereco" }
```

Coleção (0..N referências):

```json
"roles": [{ "id": 1 }, { "id": 2 }]
"files": [{ "id": "uuid-do-arquivo" }]
```

Regras:

1. Preferir o nome da entidade (`role`, `category`, `files`) em vez de `role_id` / `category_id` / `file_ids`.
2. Singular → objeto `{ "id": ... }`.
3. Plural → array de objetos `[{ "id": ... }]`.
4. Em respostas, o mesmo objeto pode trazer campos extras quando o recurso for expandido (ex.: `roles` com `id` e `name`).
5. Identidade do recurso principal da resposta pode permanecer no root (`id`, ou `user_id`/`session_id` já estabelecidos), mas novas referências cruzadas devem seguir o padrão acima.

## JSON: listagens paginadas

Toda rota HTTP de **listagem** (coleção) deve ser paginada. Não retorne array solto na raiz.

Query params:

- `page` — página 1-based (default `1`)
- `limit` — itens por página (default `20`, máximo `100`)

Resposta:

```json
{
  "items": [ ... ],
  "page": 1,
  "limit": 20,
  "total": 100
}
```

Regras:

1. Use `internal/shared/pagination` no handler (`FromQuery`) para validar `page`/`limit`.
2. Paginação é no **banco** (`LIMIT`/`OFFSET` + `COUNT` com os mesmos filtros da listagem) — nunca carregue a coleção inteira em memória só para fatiar.
3. `total` é a contagem total que casa com os filtros (não o tamanho de `items` da página).
4. Parâmetros inválidos → `400` com `{"error":"invalid_pagination"}`.

Para detalhes e justificativas, consulte a ADR de arquitetura do backend.
