# ADR-004: Módulo Rooms com contrato mínimo, UUID v7 e ciclo de vida da sala

- **Data**: 2026-09-18
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, rooms, identity, scope, laravel

## Contexto e declaração do problema

O desafio exige CRUD de salas (RF03–RF06) e, depois, reservas que leem a sala (RF07, RF09, RF16, RF17, RF19). Sem um dono explícito, o ciclo de vida da sala se mistura com reservas ou ganha campos que o enunciado não pede. Este ADR delimita o módulo **Rooms**: o que ele persiste, o que ele faz e o que fica de fora.

## Motivadores da decisão

- Cobrir exatamente o CRUD de salas (RF03–RF06), sem inventar produto (ADR-001).
- Validar entrada no servidor (RNF08, RNF14).
- Expor situação ativa/inativa para RF17 e capacidade para RF16.
- Fornecer dados da sala a outros fluxos, em especial reservas, sem transferir a elas o cadastro.
- Reusar o contrato de persistência já fixado em usuários: identidade UUID v7, auditoria e exclusão lógica.
- Gerar `id` único e ordenável no tempo, sem autoincremento do MySQL, para FKs de reservas (`foreignUuid`).

## Opções consideradas

- Módulo Rooms próprio, com contrato mínimo, UUID v7 e ciclo de vida da sala
- Salas embutidas no módulo de reservas (lookup sem dono próprio)
- Sala “rica” (local, foto, equipamentos, andares, disponibilidade avançada)

## Resultado da decisão

Opção escolhida: **"Módulo Rooms próprio, com contrato mínimo, UUID v7 e ciclo de vida da sala"**, porque o desafio pede um CRUD de sala e as reservas só consomem esse contrato.

A tabela `rooms` tem **id**, **name**, **capacity**, **is_active**, **created_at**, **updated_at** e **deleted_at**. O **id** é UUID v7, no mesmo padrão do ADR-002: trait `HasUuids` (`Str::uuid7()`), sem bigint autoincrement. Timestamps e exclusão lógica usam `$table->timestamps()`, `$table->softDeletes()` e o trait `SoftDeletes`. **is_active** é a *situação* do RF03 e o sinal de RF17 (sala inativa não recebe nova reserva).

O módulo é responsável só pelo ciclo de vida da sala, no recorte do desafio:

- listar salas (RF03: id, nome, capacidade, situação, data de criação);
- cadastrar sala (RF04);
- editar sala (RF05);
- excluir sala após confirmação (RF06);
- validar os dados de entrada;
- controlar se a sala está ativa ou inativa;
- fornecer os dados da sala a outros fluxos (reservas).

Não entram neste módulo calendário, sobreposição, duração, participantes nem seeder de reservas. O efeito de excluir sala que ainda tem reservas continua sendo lacuna do ADR-001: a implementação escolhe o caminho mais simples e coerente, sem elevar isso a regra nova aqui.

### Consequências positivas

- Um dono claro para RF03–RF06; reservas consultam, não cadastram sala.
- Contrato curto e estável: capacidade e `is_active` bastam para RF16 e RF17.
- Mesmo padrão de UUID v7, timestamps e soft delete do ADR-002; FKs de reservas usam `foreignUuid`.
- Escopo verificável: campo ou fluxo extra de sala fica fora até um ADR futuro.

### Consequências negativas

- `is_active` e `deleted_at` convivem: inativa continua listável; soft-deleted some do Eloquent padrão.
- Reservas passam a depender deste contrato; mudar capacidade ou situação afeta regras alheias.
- Soft delete impede reaproveitar o mesmo `id`; reuso de nome, se houver unique, precisa de regra explícita depois.
- Quem espera ficha completa de sala (recursos, planta, fotos) não a encontra — e não deve criá-la aqui.

## Prós e contras das opções

### Módulo Rooms próprio com contrato mínimo e UUID v7 ✅ Escolhida

- ✅ Cobre RF03–RF06 e alimenta RF16, RF17 e RF19 sem inflar o schema.
- ✅ Separa ciclo de vida da sala das regras de reserva (RF13–RF18).
- ✅ UUID v7 alinhado ao ADR-002; ids não previsíveis e ordenáveis para índice/PK.
- ❌ Dois estados de “não disponível” (`is_active` vs `deleted_at`) exigem disciplina na listagem e no consumo.
- ❌ UUID como string ocupa mais espaço que bigint e muda o tipo das FKs (`foreignUuid`).

### Salas embutidas no módulo de reservas

- ✅ Menos tipos e menos fronteira.
- ❌ Mistura cadastro de sala com overlap, duração e cancelamento.
- ❌ Impede listar/editar/excluir sala sem puxar o fluxo de reserva.

### Sala “rica”

- ✅ Mais próximo de um produto completo.
- ❌ Viola o ADR-001 (campos e telas que o enunciado não pede).
- ❌ Aumenta validação, UI e seeder sem ponto no desafio.

## Links

- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [ADR-002: Usuário mínimo com UUID v7, soft delete e hash Argon nativo do Laravel](002-usuario-minimo-uuidv7-argon2-auth-laravel.md)
- [Contexto do projeto](../context.md)
- [Tela de listagem de salas](../screens/screen-rooms-list.md)
