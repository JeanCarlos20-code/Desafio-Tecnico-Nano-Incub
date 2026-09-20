# ADR-009: Edição parcial de reserva (título e responsável)

- **Data**: 2026-09-20
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, reservations, occupancy, laravel

## Contexto e declaração do problema

O [ADR-005](005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md) deixou edição de reserva de fora. Um administrador não conseguia corrigir título ou responsável sem cancelar e recriar, o que reabre RF13–RF18 e RNF09. O pedido foi explícito: alterar só o que não mexe em ocupação; data e hora ficam travadas. A frase “Altere essa reunião” na redução de capacidade da sala (tarefa 0020) não autoriza esta tela a baixar `participants`.

## Motivadores da decisão

- Corrigir metadados de uma reserva ativa sem reabrir ocupação.
- Manter `starts_at`, `ends_at`, `room_id`, `participants` e `cancelled_at` imutáveis neste fluxo.
- Recusar no FormRequest as chaves de ocupação se um payload as enviar.
- Não reescrever o corpo de decisão do ADR-005 nem o catálogo RF do [ADR-001](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md).
- Não exigir overlap, duração, capacidade, sala inativa nem `FOR UPDATE` no update.

## Opções consideradas

- Edição parcial de título e responsável, ocupação imutável, chaves de ocupação `prohibited`
- Edição completa da reserva reusando RF13–RF18 e lock da sala
- Ignorar chaves extras só com `validated()`

## Resultado da decisão

Opção escolhida: **"Edição parcial de título e responsável, ocupação imutável, chaves de ocupação `prohibited`"**, porque o recorte do usuário trava data e hora e o ADR-005 continua dono da ocupação.

`GET /reservations/{reservation}/edit` e `PUT /reservations/{reservation}` ficam no grupo `auth`. `UpdateReservation` grava só `title` e `responsible` via `ReservationRepository::updateTitleAndResponsible`. Reserva ausente ou com `cancelled_at` preenchido responde 404 (`ReservationNotFound`). O FormRequest exige título e responsável (mesmas mensagens do store) e marca `starts_at`, `ends_at`, `room_id`, `participants` e `cancelled_at` como `prohibited`. A página Inertia `Reservation/Edit` mostra ocupação desabilitada e envia só os dois campos. A listagem ganha `Editar` ao lado de `Cancelar` nas linhas ativas.

Este ADR substitui só o recorte “não editar reserva” do ADR-005. O contrato de ocupação, o cancelamento e o lock de criação permanecem. Redução de capacidade da sala continua sem baixar `participants` por esta tela: o caminho continua sendo cancelar e recriar.

### Consequências positivas

- Typo em título ou responsável não exige recriar a reserva.
- Payload forjado que tenta mover a reunião é recusado, não ignorado em silêncio.
- RF13–RF18 e RNF09 não reabrem neste fluxo.

### Consequências negativas

- “Altere essa reunião” na redução de capacidade ainda não resolve participantes aqui.
- Reserva passada ou em andamento pode mudar metadados; as regras de horário continuam só na criação.
- Duas edições de título ao mesmo tempo: a última gravação vence; não há lock.

## Prós e contras das opções

### Edição parcial com ocupação imutável ✅ Escolhida

- ✅ Atende o recorte pedido sem reabrir ocupação.
- ✅ Recusa chaves de ocupação no servidor.
- ❌ Não serve para reduzir participantes quando a capacidade da sala cai.

### Edição completa com RF13–RF18

- ✅ Produto mais rico.
- ❌ Reabre overlap, duração, passado, capacidade, sala inativa e lock.
- ❌ O pedido e o ADR-005 recusam esse recorte.

### Ignorar chaves extras só com `validated()`

- ✅ FormRequest mais curto.
- ❌ Um payload forjado parece aceito enquanto a ocupação fica quieta.

## Links

- Substitui: [ADR-005](005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md) (somente o recorte “não editar reserva”)
- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [ADR-008: Identidade autoincremento para rooms e reservations](008-identidade-autoincremento-rooms-e-reservations.md)
