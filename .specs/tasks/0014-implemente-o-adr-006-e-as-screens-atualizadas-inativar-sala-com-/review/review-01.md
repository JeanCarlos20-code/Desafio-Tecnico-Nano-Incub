🤖 **AI Code Review (S)**

**Summary**

architecture: Camadas e lock/transação do ADR-006 estão no lugar certo, mas o contrato HTTP 422 da decisão de desativação não atravessa o limite PHP↔React: o count volta no erro e a tela não o aplica. security: Mutações de sala e reserva continuam atrás de auth, validação allowlist e persistência explícita; não há injeção, mass assignment amplo nem XSS no corpus desta rodada. smells: O fluxo de 422 deixou um callback morto em Edit.jsx; o restante do corpus está tipado, sem catch vazio, sem any e sem persistência solta. tests: Use cases, FormRequests, HTTP MySQL e corrida em dois processos cobrem o ADR-006 no nível certo; o Vitest do 422 de desativação foi adaptado com rerender e não protege o contrato real.

**❌ Blockers**

None found.

**⚠️ High**

- **ARCH-001** — `resources/js/Pages/Room/Edit.jsx`:28: O backend devolve future_active_count no 422 para a tela reabrir o diálogo, mas o onError só liga reopenDialog e nunca atualiza o count. RoomForm só abre o diálogo se futureActiveCount (prop da página) for maior que zero. Impact: Se a edição carregou com zero futuras e o servidor passou a exigir keep/cancel, o PUT é rejeitado sem persistir, porém o diálogo não abre e o erro de scheduled_meetings_action não é exibido. O administrador fica sem o contrato da spec AC-004/AC-006. Recommended fix: No onError, ler errors.future_active_count, promover esse inteiro ao estado usado pelo RoomForm e só então reabrir o diálogo. Não depender de um rerender Inertia da prop inicial.
- **TEST-001** — `resources/js/Pages/Room/Edit.test.jsx`:190: O caso de 422 chama onError com future_active_count e em seguida faz rerender de Edit com future_active_count={2}. Isso injeta a prop de página que o Inertia 422 não atualiza; o teste fica verde sem provar que o onError sozinho abre o diálogo. Impact: A recuperação AC-004/AC-006 (devolver o count para o diálogo abrir) pode regressir sem falhar o gate de frontend. O teste foi reescrito para o código incorreto, não para o comportamento observável. Recommended fix: Disparar onError com o payload real e afirmar o diálogo aberto sem rerender com nova prop. Se o count vier só do erro, o componente precisa consumir errors.future_active_count; o teste deve falhar enquanto isso não ocorrer.

**📝 Medium**

- **SMELL-001** — `resources/js/Pages/Room/Edit.jsx`:25: visitOptions aceita onDialogRequired, o onError chama onDialogRequired?.(), mas submit sempre faz visitOptions() sem argumento. O parâmetro é código morto. Impact: Quem lê o Edit acredita que a reabertura do diálogo é injetável; na prática só setReopenDialog corre, e o caminho 422 fica mais difícil de completar sem estado local para o count. Recommended fix: Remover o parâmetro morto ou usá-lo de fato para aplicar o count do 422 e abrir o diálogo.

**✅ Positive Findings**

- UpdateRoom.php:38 trava a sala com lockById dentro de Transaction antes de keep/cancel/update, alinhado a Infra → Application → Domain e ao ADR-006.
- DeleteRoom.php:23 cancela ativas e só então apaga a sala na mesma transação após o lock.
- EloquentReservationRepository.php:52 e :70 restringem listPage/hasAny a cancelled_at nulo; Reservation permanece dono de cancelled_at.
- UpdateRoomController.php:17 usa validated() e só adapta HTTP ao use case, sem regra de negócio extra além do mapeamento do 422.
- routes/web.php:29 agrupa GET/PUT/DELETE de rooms e reservations em middleware auth; guest sem sessão não chega nos controllers novos.
- UpdateRoomRequest.php:22 restringe scheduled_meetings_action a keep|cancel e exige is_active booleano.
- UpdateRoomController.php:17 passa só validated() + boolean('is_active') ao use case, sem $request->all() em Eloquent.
- EloquentRoomRepository.php:69 preenche apenas name, capacity e is_active; o model declara $fillable equivalente em Room.php:19.
- EloquentReservationRepository.php:104 usa selectRaw estático (room_id, count(*)) com whereIn bound, sem interpolar input.
- CreateReservation.php:59 recusa participantes acima da capacidade travada no servidor; o max do React é só UX.
- Room/Index.jsx:249 interpola pending.name em JSX, sem dangerouslySetInnerHTML.
- RoomForm.jsx:57 limita o diálogo a troca Ativa→Inativa com futureActiveCount > 0; sala já inativa não reabre decisão.
- rooms.js:7 concentra put/delete/get de salas; as páginas não espalham URLs de mutação.
- EloquentReservationRepository.php:117 e :125 só atualizam cancelled_at onde ele ainda é null (cancelamento idempotente).
- UpdateRoomTest.php:63 cobre keep/cancel/ausência de ação e escrita zero no use case com fakes, sem MySQL.
- RoomUpdateHttpTest.php:137 prova o 422 HTTP com future_active_count, flash/persistência e MySQL real.
- ReservationRoomLifecycleConcurrencyHttpTest.php:36 sobe dois processos PHP com barreira e lock real; SignalingRoomRepository.php:27 só coordena o momento, o FOR UPDATE continua no Eloquent.
- ReservationIndexHttpTest.php:111 substitui a inclusão de canceladas pela exclusão após cancel avulso, deactivate-cancel e delete-cancel.
- Create.test.jsx:119 impede participantes acima da capacidade no React; a regra de servidor permanece no use case.
- IndexRoomRequestTest.php:11 cobre o contrato all|active|inactive sem bater na rota.

**Verdict**

❌ REJECTED

**Harness gate**

❌ blocked — review REJECTED (blocker/high).
