# ADR-006: Serializar ciclo da sala com reservas novas e existentes

- **Data**: 2026-09-19
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, reservations, rooms, concurrency, laravel

## Contexto e declaração do problema

O ADR-005 recusa reserva nova em sala inativa (RF17) e serializa duas criações com lock na linha de `rooms` (RNF09). O ADR-004 inativa e exclui a sala sem dizer o que acontece com as reservas já gravadas; a listagem de salas ainda sugeria bloquear a exclusão. A tela de edição ([screen-room-form](../screens/screen-room-form.md)) já pede escolha explícita ao desativar. Sem um dono, a implementação pode cancelar em silêncio, órfã reserva ativa em sala apagada, ou gravar reunião nova no meio da inativação. Este ADR fecha o cruzamento: reservas **novas** e **já existentes**, inclusive sob corrida.

## Motivadores da decisão

- Não cancelar reunião existente em silêncio ao inativar (tela de edição).
- Impedir reserva nova em sala inativa ou excluída, também sob corrida (RF17 + RNF09).
- Excluir sala não pode deixar reunião ativa apontando para um registro soft-deleted.
- Reusar o lock da sala do ADR-005 e o `cancelled_at` do contrato de Reservation.
- Reserva cancelada some da listagem (mesmo efeito de apagada); o registro permanece só para auditoria e overlap.
- Listagem de salas mostra ativa e inativa; o administrador filtra `Todas` / `Ativas` / `Inativas`.
- Inativar ou excluir com cancelamento de reuniões é uma única transação.
- Provar a corrida no MySQL 8 com dois processos reais (`docs/test/integration.md`).

## Opções consideradas

- Inativar com escolha (manter ou cancelar futuras); excluir cancela ativas e avisa
- Bloquear a exclusão enquanto existir qualquer reserva (sugestão da listagem de salas)
- Sempre cancelar ao inativar, sem diálogo
- Deixar reservas existentes como lacuna do ADR-001

## Resultado da decisão

Opção escolhida: **"inativar com escolha; excluir cancela ativas e avisa"**, porque a tela de edição já exige a decisão e a exclusão é irreversível: não há como “manter” reunião numa sala que some.

Inativar, excluir e criar reserva na mesma sala compartilham o lock:

1. iniciam uma transação;
2. bloqueiam a sala com `SELECT … FOR UPDATE`;
3. aplicam a regra deste ADR;
4. finalizam a transação.

Quem cria reserva depois do lock vê o estado gravado: sala inativa → `InactiveRoom` (`Não é possível reservar uma sala inativa.`); sala ausente ou soft-deleted → `OccupancyRoomNotFound` (`Sala não encontrada.`). Nenhuma reserva nova é persistida nesses dois casos. Salas diferentes não se bloqueiam.

### Inativar sala (`is_active = false`)

Novas reservas ficam bloqueadas. Reservas existentes **não** mudam em silêncio. Se houver reserva **ativa futura**, o save abre o diálogo da tela de edição, com o grupo:

```text
O que deseja fazer com as reuniões programadas?
```

| Opção (rádio) | Padrão | Efeito |
| --- | --- | --- |
| `Manter reuniões programadas` | Sim | Sala inativa; ativas futuras continuam ativas e aparecem na listagem; a sala não recebe reunião nova |
| `Cancelar reuniões programadas` | Não | Sala inativa; ativas futuras ganham `cancelled_at` (mesmo cancelamento do RF11/RF12); intervalo livre; passadas, em andamento e já canceladas não mudam |

Sem reserva ativa futura, inativa direto, sem diálogo. A opção só vale depois de confirmar `Desativar`.

Inativar e o efeito nas reservas (manter ou cancelar) correm na **mesma transação**, com o lock da sala: commit dos dois lados ou rollback dos dois. Falha no cancelamento não deixa a sala inativa; falha na sala não deixa reunião cancelada.

### Excluir sala (soft delete)

Não há escolha. A exclusão cancela **todas** as reservas ativas daquela sala (`cancelled_at`) e em seguida aplica o soft delete, na **mesma transação** e com o mesmo lock. Os registros de reserva permanecem no banco; a sala some da listagem padrão (soft delete). Reserva cancelada some da listagem de reservas.

Se existir qualquer reserva da sala, o diálogo de exclusão **avisa** que as reuniões ativas serão canceladas. Sem aviso, a exclusão não segue. Isso substitui a sugestão da listagem de bloquear o `DELETE` enquanto houver reserva.

### Listagem de reservas

Cancelada é, na lista, o mesmo que apagada: `ListReservations` só devolve linhas com `cancelled_at` nulo. Vale para cancelamento avulso (RF11), para `Cancelar reuniões programadas` na inativação e para o cancelamento automático da exclusão. O registro não ganha `deleted_at`; some da tela e deixa de ocupar o horário (RF12). Depois de cancelar, a linha some — não vira badge `Cancelada`.

### Listagem de salas

Ativa e inativa aparecem. Soft-deleted não. O filtro da listagem é obrigatório e vai na URL:

| Rótulo | Query | Efeito |
| --- | --- | --- |
| `Todas` | sem `status`, ou `status=all` | ativas e inativas |
| `Ativas` | `status=active` | só `is_active = true` |
| `Inativas` | `status=inactive` | só `is_active = false` |

Padrão: `Todas`. Trocar o filtro reseta a página para 1. A filtragem é no servidor.

### Testes de concorrência

Integração, dois processos PHP + barreira, MySQL 8 — o mesmo recorte do overlap:

- `POST /reservations` vs inativação da mesma sala: se a criação obtém o lock com a sala já inativa, falha com sala inativa e não grava linha;
- `POST /reservations` vs exclusão da mesma sala: se a criação obtém o lock com a sala já excluída, falha com sala não encontrada e não grava linha.

Teste sequencial ou fake de repositório não prova este contrato.

### Consequências positivas

- A lacuna “o que acontece com reservas quando a sala muda” deixa de ser improvisação.
- RF17 vale sob corrida; exclusão não deixa reunião ativa órfã.
- O diálogo de inativação e o aviso de exclusão têm dono no backend, não só no React.

### Consequências negativas

- `UpdateRoom` e `DeleteRoom` passam a transacionar e lockar a sala; a contenção daquela linha sobe.
- Rooms conhece o efeito em reservas (manter / cancelar futuras / cancelar ativas na exclusão); Reservation continua dono de `cancelled_at`.
- A listagem de salas deixa de bloquear exclusão por existência de reserva — o aviso + cancelamento automático é a regra.
- A tela de reservas deixa de mostrar histórico cancelado; quem quiser auditoria lê o banco, não a lista.

## Prós e contras das opções

### Inativar com escolha; excluir cancela e avisa ✅ Escolhida

- ✅ Cobre a tela de edição (dois rádios) e a exclusão irreversível sem orphan ativo.
- ✅ Fecha a corrida com o mesmo `FOR UPDATE` do ADR-005.
- ✅ Histórico de reserva permanece (`cancelled_at`), alinhado ao RF11/RF12.
- ❌ Rooms e Reservation compartilham a transação de inativar/excluir.

### Bloquear exclusão se houver reserva

- ✅ Preserva histórico sem tocar em `cancelled_at`.
- ❌ Impede apagar sala “suja” para sempre; a tela de exclusão ficaria num beco sem o aviso pedido aqui.

### Sempre cancelar ao inativar

- ✅ Sem diálogo.
- ❌ Viola a tela: reunião futura seria desmarcada sem a escolha `Manter reuniões programadas`.

### Deixar como lacuna do ADR-001

- ✅ Menos contrato agora.
- ❌ Cada implementação inventa um desenlace; a corrida e o delete ficam indefinidos.

## Links

- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [ADR-004: Módulo Rooms com contrato mínimo, UUID v7 e ciclo de vida da sala](004-modulo-rooms-ciclo-de-vida-minimo.md)
- [ADR-005: Módulo Reservation com contrato mínimo, ocupação e cancelamento](005-modulo-reservation-contrato-minimo-ocupacao-e-cancelamento.md)
- [Tela de formulário de sala](../screens/screen-room-form.md)
- [Tela de listagem de salas](../screens/screen-rooms-list.md)
- [Tela de listagem de reservas](../screens/screen-reservations-list.md)
- [Contexto do projeto](../context.md)
