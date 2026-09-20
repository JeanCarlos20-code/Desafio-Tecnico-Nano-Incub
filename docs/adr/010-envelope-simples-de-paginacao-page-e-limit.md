# ADR-010: Envelope simples de paginação com page e limit

- **Data**: 2026-09-20
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, pagination, inertia, listings

## Contexto e declaração do problema

`GET /rooms` e `GET /reservations` já paginavam com `?page` e tamanho fixo 15. A prop Inertia da listagem era o array do `LengthAwarePaginator` (`per_page`, `current_page`, `last_page`, `next_page_url`, `prev_page_url`, `links`). O cliente não escolhia o tamanho da página. O contrato era maior do que o envelope pedido: `{ data, page, limit, total }`.

## Motivadores da decisão

- Aceitar `?page` e `?limit` nas duas listagens grandes.
- Devolver só quatro chaves na prop `rooms` / `reservations`.
- Impedir `SELECT` sem teto com um máximo de `limit`.
- Manter o transporte Inertia. Não criar API JSON à parte.
- Não paginar catálogos pequenos (`filterRooms`, selects de criação).

## Opções consideradas

- Envelope simples `{ data, page, limit, total }` nas props Inertia existentes, com `?page` e `?limit`
- Manter `LengthAwarePaginator::toArray()` e só acrescentar `limit`
- Nova API JSON ao lado do Inertia

## Resultado da decisão

Opção escolhida: **"Envelope simples `{ data, page, limit, total }` nas props Inertia existentes, com `?page` e `?limit`"**, porque esse é o contrato pedido e o painel já é Inertia.

`page` omisso vale **1** (mínimo 1). `limit` omisso vale **20** (mínimo 1, máximo **100**). `IndexRoomRequest` e `IndexReservationRequest` recusam `page` / `limit` inválidos com `Informe uma página válida.` e `Informe um limite válido.` e não chamam o use case de listagem. Os controllers passam `limit` ao `ListRooms` / `ListReservations` e montam o envelope. Página além da última devolve `data` vazio, ecoa `page` / `limit` e mantém `total`. O React calcula a última página com `ceil(total / limit)` e monta Anterior/Próxima só com filtros conhecidos, `page` e `limit`. Troca de filtro envia `page=1` e conserva o `limit` atual.

Este ADR não reescreve o corpo de decisão dos ADR-001 a ADR-009.

### Consequências positivas

- O cliente lê um contrato de quatro chaves, sem URLs do paginator Laravel.
- `limit` explícito evita página fixa de 15 e limita o tamanho do `SELECT`.
- Validação fica no FormRequest, no mesmo padrão de `status` / filtros.

### Consequências negativas

- O React passa a montar as URLs de Anterior/Próxima. Os testes Feature deixam de assertar `*_page_url`.
- Quem esperava `per_page` / `links` na prop precisa ler o envelope novo.
- Não há botões numerados nem scroll infinito.

## Prós e contras das opções

### Envelope simples com page e limit ✅ Escolhida

- ✅ Combina com o exemplo do pedido.
- ✅ Remove o paginator do adapter HTTP das duas listagens grandes.
- ❌ O frontend calcula próxima/anterior.

### Manter LengthAwarePaginator e só acrescentar limit

- ✅ Menos mudança no React.
- ❌ O payload continua no formato Laravel. O pedido pede quatro chaves e este ADR.

### Nova API JSON ao lado do Inertia

- ✅ Contrato REST isolado.
- ❌ Superfície extra. O painel não tem cliente JSON. O [ADR-003](003-react-e-php-no-mesmo-projeto-laravel.md) recusa REST público como contrato da UI.

## Links

- [ADR-003: React e PHP no mesmo projeto Laravel](003-react-e-php-no-mesmo-projeto-laravel.md)
- [Listagem de salas](../screens/screen-rooms-list.md)
- [Listagem de reservas](../screens/screen-reservations-list.md)
