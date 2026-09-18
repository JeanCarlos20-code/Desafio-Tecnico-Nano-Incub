# ADR-002: Usuário mínimo com UUID v7, soft delete e hash Argon nativo do Laravel

- **Data**: 2026-09-16
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, security, identity, laravel

## Contexto e declaração do problema

O painel precisa persistir administradores (RF01, RF19) sem inventar um sistema de identidade. O skeleton do Laravel 12 e o Breeze trazem colunas extras (`email_verified_at`, incremento numérico) e hash bcrypt por padrão. RNF07 e o [contexto do projeto](../context.md) exigem autenticação do ecossistema Laravel; RNF10 exige senha hasheada. Este ADR fixa o contrato do usuário e os mecanismos de id, hash, timestamps e login.

## Motivadores da decisão

- Cumprir RNF07 (não recriar login) e RNF10 (senha nunca em texto puro).
- Schema só com o necessário para cadastro, auditoria e exclusão lógica.
- Gerar ids únicos e ordenáveis no tempo, sem autoincremento do MySQL.
- Usar o hasher Argon que o Laravel já entrega — sem algoritmo, parâmetros ou classe próprios.
- Não acrescentar verificação de e-mail, papéis ou tokens próprios (ADR-001).

## Opções consideradas

- Usuário mínimo + UUID v7 + timestamps/soft delete + driver `argon` do Laravel + auth nativa
- Schema padrão do Breeze/skeleton (bigint, `email_verified_at`, bcrypt, sem `deleted_at`)
- Identidade própria (JWT, papéis, hasher customizado, exclusão física)

## Resultado da decisão

Opção escolhida: **"Usuário mínimo + UUID v7 + timestamps/soft delete + driver `argon` do Laravel + auth nativa"**, porque cobre o desafio com o que o framework já faz.

A tabela `users` tem **id**, **name**, **email**, **password**, **created_at**, **updated_at** e **deleted_at**. O `id` é gerado pelo trait `HasUuids` (`Str::uuid7()`). Timestamps e exclusão lógica usam `$table->timestamps()`, `$table->softDeletes()` e o trait `SoftDeletes`. A senha usa o driver **`argon`** do Laravel (`HASH_DRIVER=argon`, Argon2i) com os defaults de `config/hashing.php` do framework (`memory`, `threads`, `time`); o modelo só aplica o cast `hashed`. Login, sessão, `remember_token` e guard Eloquent ficam a cargo do Laravel.

`remember_token` existe para o runtime de sessão; não é campo de criação.

### Consequências positivas

- Contrato de persistência curto: criação, auditoria e exclusão lógica sem colunas de produto extra.
- Auth idiomática (RNF07, RNF14): `Authenticatable`, sessão, `Hash::check`.
- UUID v7 evita ids previsíveis e permanece ordenável para índice/PK.
- Soft delete permite desfazer exclusão e o Eloquent já exclui o usuário apagado do login.
- Hash Argon sem inventar hasher: só o driver suportado e os parâmetros que o Laravel já declara.

### Consequências negativas

- UUID como string ocupa mais espaço que bigint e muda o tipo das FKs (`foreignUuid`).
- O default do framework é bcrypt; é preciso definir `HASH_DRIVER=argon` (valor suportado, sem inventar outro).
- Argon é mais lento e mais exigente em memória que bcrypt.
- O unique de `email` continua valendo para linhas com `deleted_at`; o mesmo e-mail não pode ser recadastrado enquanto o registro soft-deleted existir.
- Sem `email_verified_at` e sem papéis, confirmação de e-mail e RBAC ficam de fora até um ADR futuro.

## Prós e contras das opções

### Usuário mínimo + UUID v7 + soft delete + argon Laravel ✅ Escolhida

- ✅ Campos alinhados ao desafio; auth e hash sem código próprio.
- ✅ UUID v7, `SoftDeletes` e driver `argon` já existem no Laravel 12.
- ❌ Exige `HASH_DRIVER=argon`; o default publicado é bcrypt.
- ❌ Unique de e-mail + soft delete impede reuso do e-mail até hard delete ou mudança de regra.

### Schema padrão Breeze/skeleton

- ✅ Zero desvio do starter kit.
- ❌ Inclui `email_verified_at` e bigint; não tem `deleted_at`.
- ❌ Bcrypt é o default, mas não é a escolha deste projeto.

### Identidade própria (JWT, papéis, hasher customizado)

- ✅ Mais próximo de um produto completo.
- ❌ Viola RNF07 e o ADR-001.
- ❌ Duplica sessão e hashing que o Laravel já resolve.

## Links

- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [Contexto do projeto](../context.md)
- Spec: `.specs/features/create-user/spec.md`
