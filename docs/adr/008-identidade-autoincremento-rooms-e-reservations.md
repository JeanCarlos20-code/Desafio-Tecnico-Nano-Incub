# ADR-008: Identidade autoincremento para rooms e reservations

- **Data**: 2026-09-20
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, identity, rooms, reservations, laravel

## Contexto e declaração do problema

O desafio Nano Incub pede colunas de listagem com **ID** visível (RF03, RF08). O [ADR-004](004-modulo-rooms-ciclo-de-vida-minimo.md) e o [ADR-005](005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md) gravavam `rooms.id` e `reservations.id` como UUID v7 (`HasUuids`, `foreignUuid`). Não há banco de produção. O usuário autorizou trocar só a identidade de salas e reservas para bigint autoincremento, sem reabrir o contrato de usuário do [ADR-002](002-usuario-minimo-uuidv7-argon2-auth-laravel.md).

## Motivadores da decisão

- Mostrar o id persistido nas tabelas de salas e reservas, no formato do enunciado.
- Evitar UUID longo na coluna `ID` da listagem.
- Manter `users.id` como UUID v7 (`HasUuids`).
- Não reescrever o corpo de decisão do ADR-002, do ADR-004 nem do ADR-005.
- Não haver dados de produção: editar as migrations de criação, sem migration de conversão.

## Opções consideradas

- Autoincremento (`$table->id()` / `foreignId`) só em rooms e reservations
- Manter UUID v7 em rooms e reservations e só exibir o id na UI
- Migration de alteração de uuid para bigint

## Resultado da decisão

Opção escolhida: **"Autoincremento só em rooms e reservations"**, porque o enunciado pede ID visível e o usuário autorizou bigint sem banco de produção.

`rooms.id` passa a ser unsigned bigint autoincrement (`$table->id()`). `reservations.id` usa o mesmo padrão. `reservations.room_id` passa a ser `foreignId` para `rooms.id`. Os modelos `Room` e `Reservation` deixam o trait `HasUuids`. A migration de catálogo demo não gera UUID para essas linhas.

Este ADR substitui só o recorte de identidade UUID / `HasUuids` / `foreignUuid` do ADR-004 e do ADR-005. Soft delete em salas, `cancelled_at` em reservas, colunas de negócio, lock `FOR UPDATE` na sala (ADR-006) e UUID v7 em `users` permanecem.

O Domain continua com `id` e `roomId` como `string` (decimal do bigint). Infra faz o cast `(string) $model->getKey()`.

### Consequências positivas

- IDs curtos e incrementais na listagem, alinhados ao enunciado.
- FK de reserva usa o tipo nativo do Laravel para bigint.
- Identidade de usuário (ADR-002) intacta.

### Consequências negativas

- IDs de sala e reserva ficam previsíveis na URL. As rotas continuam atrás de `auth`; UUID não é autorização.
- ADR-004 e ADR-005 descrevem UUID no corpo histórico; o Status aponta para este ADR.
- Trocar o tipo no Domain para `int` reescreveria os fakes de Application; fica de fora.

## Prós e contras das opções

### Autoincremento só em rooms e reservations ✅ Escolhida

- ✅ Coluna `ID` da listagem mostra um inteiro curto.
- ✅ Sem migration de conversão: não há produção.
- ✅ Usuários permanecem UUID v7.
- ❌ IDs enumeráveis nas rotas de sala e reserva (mitigado por autenticação).

### Manter UUID v7 e só exibir o id

- ✅ Sem mudança de schema.
- ❌ A coluna `ID` mostraria UUID; o usuário rejeitou isso.

### Migration de alteração uuid → bigint

- ✅ Correto se existisse produção.
- ❌ Não há dados de produção; o usuário pediu editar as create migrations.

## Links

- Substitui: [ADR-004](004-modulo-rooms-ciclo-de-vida-minimo.md) (somente o recorte de identidade UUID / `HasUuids` / `foreignUuid`)
- Substitui: [ADR-005](005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md) (somente o recorte de identidade UUID / `HasUuids` / `foreignUuid`)
- [ADR-002: Usuário mínimo com UUID v7, soft delete e hash Argon nativo do Laravel](002-usuario-minimo-uuidv7-argon2-auth-laravel.md) — identidade de usuário inalterada
- [ADR-006: Serializar ciclo da sala com reservas novas e existentes](006-serializar-ciclo-da-sala-com-reservas-novas-e-existentes.md)
- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
