# ADR-005: Módulo Reservation com contrato mínimo, ocupação e cancelamento

- **Data**: 2026-09-18
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, reservations, occupancy, concurrency, laravel

## Contexto e declaração do problema

O desafio exige o fluxo de reservas (RF07–RF12), as regras de ocupação (RF13–RF18) e a concorrência (RNF09). O módulo Rooms (ADR-004) já é o dono do cadastro da sala; reservas só leem capacidade, situação e `id`. Sem um dono explícito, overlap, duração e cancelamento se misturam com salas ou ganham calendário, recorrência e e-mail que o enunciado não pede. Este ADR delimita o módulo **Reservation**: o que ele persiste, o que ele faz e o que fica de fora.

## Motivadores da decisão

- Cobrir exatamente RF07–RF18, sem inventar produto (ADR-001).
- Validar entrada no servidor (RNF08, RNF14).
- Impedir sobreposição de reservas **ativas** na mesma sala e aceitar consecutivas (RF13).
- Garantir que duas requisições simultâneas não criem conflito na mesma sala (RNF09).
- Consumir o contrato de Rooms (`foreignUuid`, capacidade, `is_active`) sem cadastrar sala aqui.
- Reusar identidade UUID v7 e timestamps já fixados em usuários e salas.

## Opções consideradas

- Módulo Reservation próprio, com contrato mínimo, regras de ocupação e cancelamento
- Reservas embutidas no módulo Rooms (mesmo agregado da sala)
- Reserva “rica” (recorrência, calendário, lista de espera, notificação, edição)

## Resultado da decisão

Opção escolhida: **"Módulo Reservation próprio, com contrato mínimo, regras de ocupação e cancelamento"**, porque o desafio pede um fluxo de reserva distinto do CRUD de sala, e as regras RF13–RF18 e RNF09 só fazem sentido com um dono único.

A tabela `reservations` tem **id**, **room_id**, **responsible**, **title**, **starts_at**, **ends_at**, **participants**, **cancelled_at**, **created_at** e **updated_at**. O **id** é UUID v7, no mesmo padrão do ADR-002 e do ADR-004: trait `HasUuids` (`Str::uuid7()`), sem bigint autoincrement. **room_id** é `foreignUuid` para `rooms.id`. Timestamps usam `$table->timestamps()`. **responsible** e **title** são texto da própria reserva (RF07: responsável e título/finalidade), não FK para `users`. **cancelled_at** nulo significa reserva ativa; preenchido significa cancelada (RF11, RF12).

Não há `deleted_at` neste contrato. O enunciado pede **cancelar**, não excluir: soft delete esconderia o histórico no Eloquent padrão e duplicaria o estado de “não ocupa a sala”.

O módulo é responsável só pelo ciclo de ocupação, no recorte do desafio:

- criar reserva com sala, responsável, título/finalidade, início, fim e participantes (RF07);
- listar reservas com dados principais, da mais próxima para a mais distante (RF08);
- filtrar por sala (RF09) e por dia (RF10);
- cancelar mediante confirmação (RF11);
- tratar cancelada como intervalo livre (RF12);
- recusar overlap de ativas na mesma sala e aceitar consecutivas (`ends_at` de uma igual a `starts_at` da outra) (RF13);
- exigir término posterior ao início (RF14);
- exigir duração mínima de 30 minutos e máxima de 4 horas (RF15);
- recusar participantes acima da capacidade da sala (RF16);
- recusar nova reserva em sala inativa (RF17);
- recusar horário inicial no passado (RF18);
- serializar a criação concorrente na mesma sala (RNF09), conforme a seção *Concorrência na criação de reservas*.

Reservas consultam Rooms; não cadastram, editam nem excluem sala. O seeder combinado (RF19) usa este contrato para gravar algumas reservas, mas a orquestração admin + salas + reservas é item de entrega, não regra nova daqui.

Não entram neste módulo edição de reserva, recorrência, calendário avançado, e-mail, lista de espera, check-in, API pública nem papéis além de administrador. Se a listagem inclui canceladas, e o que acontece com reservas já existentes quando a sala fica inativa ou é excluída, continuam lacunas do ADR-001: a implementação escolhe o caminho mais simples e coerente, sem elevar isso a regra nova aqui.

### Consequências positivas

- Um dono claro para RF07–RF18 e RNF09; Rooms permanece só no ciclo de vida da sala.
- Contrato curto e estável: os campos de RF07 mais `cancelled_at` bastam para RF11, RF12 e RF13.
- Mesmo padrão de UUID v7 e `foreignUuid` dos ADRs 002 e 004.
- Escopo verificável: tela, campo ou fluxo extra de reserva fica fora até um ADR futuro.

### Consequências negativas

- `cancelled_at` e a listagem RF08 convivem sem regra explícita de exibir ou ocultar canceladas.
- Reservas dependem do contrato de Rooms; mudar capacidade ou `is_active` afeta RF16 e RF17.
- `SELECT ... FOR UPDATE` na sala serializa criações do mesmo `room_id` e aumenta contenção sob pico nessa sala.
- Quem espera editar reserva, recorrência ou calendário semanal não os encontra — e não deve criá-los aqui.

## Concorrência na criação de reservas

A criação de reservas será protegida por transação no MySQL.

Ao criar uma reserva:

1. inicia uma transação;
2. bloqueia a sala correspondente com `SELECT ... FOR UPDATE`;
3. valida se a sala pode receber a reserva;
4. verifica se existe alguma reserva ativa com sobreposição de horário;
5. se não houver conflito, cria a reserva;
6. finaliza a transação.

O bloqueio é feito na sala, e não apenas nas reservas existentes, porque pode não existir nenhuma reserva para aquele horário ainda.

Dessa forma, duas requisições simultâneas para a mesma sala são processadas de forma serializada: a segunda aguarda a primeira terminar e, ao continuar, já consegue enxergar a reserva criada anteriormente.

Reservas de salas diferentes não bloqueiam umas às outras.

A verificação de conflito usa a condição:

```text
existing.start < new.end
AND
existing.end > new.start
```

Isso impede sobreposições e permite reservas consecutivas, como uma terminando às 10:00 e outra começando às 10:00.

## Prós e contras das opções

### Módulo Reservation próprio com contrato mínimo e ocupação ✅ Escolhida

- ✅ Cobre RF07–RF18 e RNF09 sem inflar o schema nem misturar cadastro de sala.
- ✅ Cancelamento explícito (`cancelled_at`) libera o intervalo sem apagar o registro.
- ✅ UUID v7 alinhado aos ADRs 002 e 004; FKs usam `foreignUuid`.
- ❌ Exige transação + `FOR UPDATE` na sala; um `exists` isolado não cumpre RNF09.
- ❌ Duas tabelas e uma fronteira a mais do que embutir reserva em Rooms.

### Reservas embutidas no módulo Rooms

- ✅ Menos tipos e menos fronteira.
- ❌ Mistura CRUD de sala (RF03–RF06) com overlap, duração e cancelamento.
- ❌ Impede evoluir listagem/filtro de reservas sem puxar o agregado da sala.

### Reserva “rica”

- ✅ Mais próximo de um produto completo.
- ❌ Viola o ADR-001 (recorrência, calendário, e-mail, edição, lista de espera).
- ❌ Aumenta validação, UI, lock e seeder sem ponto no desafio.

## Links

- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [ADR-002: Usuário mínimo com UUID v7, soft delete e hash Argon nativo do Laravel](002-usuario-minimo-uuidv7-argon2-auth-laravel.md)
- [ADR-003: React e PHP no mesmo projeto Laravel via Inertia.js 2](003-react-e-php-no-mesmo-projeto-laravel.md)
- [ADR-004: Módulo Rooms com contrato mínimo, UUID v7 e ciclo de vida da sala](004-modulo-rooms-ciclo-de-vida-minimo.md)
- [Contexto do projeto](../context.md)
