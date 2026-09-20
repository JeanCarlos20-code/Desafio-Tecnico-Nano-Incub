🤖 **AI Code Review (S)**

**Summary**

architecture: A guarda de redução de capacidade respeita Infra → Application → Domain: o contrato ficou no port de Reservation, a decisão e o erro ficaram no use case de Room, e o controller só adapta HTTP. A regra corre depois de lockById na transação existente, sem camada extra nem invariante só no React. security: O PUT continua atrás de auth, usa validated() e query builder com bindings. A recusa é servidor-autoritativa; a mensagem interpola só o inteiro do count e o React renderiza o texto escapado. Não há mass assignment, SQL interpolado, segredo novo nem autorização só no frontend. smells: A mudança é local e tipada: um método no port, um erro de aplicação e um if no use case. Fake e Eloquent usam o mesmo predicado. Não há catch vazio, mixed escondendo contrato, duplicação de regra no React nem abstração especulativa. tests: A regra de UpdateRoom está isolada com fakes; o PUT em MySQL 8 cobre o mapeamento HTTP, persistência inalterada e precedência sobre o diálogo de desativar; o Vitest cobre o contrato visível de errors.capacity. Os níveis não repetem o mesmo cenário sem fronteira distinta, e E2E Playwright continua fora de escopo.

**❌ Blockers**

None found.

**⚠️ High**

None found.

**📝 Medium**

None found.

**✅ Positive Findings**

- UpdateRoom aplica a recusa depois de lockById e RoomNotFound, ainda dentro da transação, e só então segue o fluxo de desativar/persistir: app/Modules/Room/Application/UseCases/UpdateRoom.php:40
- CapacityReductionBlocked vive em Application/Errors, como exige docs/tree.md: app/Modules/Room/Application/Errors/CapacityReductionBlocked.php:7
- O port de domínio permanece PHP puro e só declara o novo count: app/Modules/Reservation/Domain/Repositories/ReservationRepository.php:40
- A implementação Eloquent fica em Infra/Database e replica o filtro futuro-ativo + participants: app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:100
- O controller permanece fino: validated() + catch do erro de aplicação, sem regra de negócio: app/Modules/Room/Infra/Http/Controllers/UpdateRoomController.php:18
- Room/Edit só exibe errors.capacity no campo já existente; a invariante continua no backend: resources/js/Pages/Room/Components/RoomForm.jsx:124
- rooms.update permanece no grupo auth: routes/web.php:22
- A rota PUT usa o controller existente autenticado: routes/web.php:34
- O controller passa só validated() ao use case, com capacity convertido para int: app/Modules/Room/Infra/Http/Controllers/UpdateRoomController.php:18
- UpdateRoomRequest já exige capacity integer min:1 e não foi afrouxado: app/Modules/Room/Infra/Http/Requests/UpdateRoomRequest.php:21
- A query Eloquent usa where() com bindings, sem concatenar SQL: app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:102
- A mensagem de erro interpola apenas o inteiro conflictingCount gerado no servidor: app/Modules/Room/Application/Errors/CapacityReductionBlocked.php:11
- O campo de erro é JSX {error}, sem dangerouslySetInnerHTML: resources/js/Pages/Room/Components/RoomForm.jsx:271
- A regra de persistência está no use case, não só na UI: app/Modules/Room/Application/UseCases/UpdateRoom.php:46
- CapacityReductionBlocked tem tipo explícito e mensagens fechadas singular/plural: app/Modules/Room/Application/Errors/CapacityReductionBlocked.php:9
- O use case tem um único ponto de decisão, sem aninhamento extra nem efeito colateral antes do throw: app/Modules/Room/Application/UseCases/UpdateRoom.php:46
- FakeReservationRepository replica o mesmo filtro do Eloquent (sala, não cancelada, startsAt > now, participants > capacity): tests/Unit/Reservation/FakeReservationRepository.php:143
- EloquentReservationRepository implementa o mesmo predicado sem raw SQL: app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:100
- O controller só mapeia a mensagem já pronta, sem reescrever a regra: app/Modules/Room/Infra/Http/Controllers/UpdateRoomController.php:30
- Unit cobre bloqueio, write-nothing e participantes/cancelamento intactos com FakeRoom/FakeReservation/FakeClock: tests/Unit/Room/UpdateRoomTest.php:160
- Unit cobre singular quando só uma futura excede (a outra cabe): tests/Unit/Room/UpdateRoomTest.php:187
- Unit cobre plural, persistência quando cabe, aumento/igual, ignorar passado/em-andamento/cancelada/outra sala, precedência sobre desativar e avaliação após lock: tests/Unit/Room/UpdateRoomTest.php:212
- Feature PUT no MySQL prova redirect, mensagem exata e rooms/reservations inalterados: tests/Feature/Room/RoomUpdateHttpTest.php:172
- Feature cobre plural, redução que cabe e conflito+desativar sem action só com erro de capacity: tests/Feature/Room/RoomUpdateHttpTest.php:213
- phpunit.xml força MySQL no suite de integração: phpunit.xml:29
- Vitest renderiza a sentença AC-002 em capacity-error e mantém o formulário de edição: resources/js/Pages/Room/Edit.test.jsx:172

**Verdict**

✅ APPROVED

**Harness gate**

✅ open — review APPROVED and required checks are green.
