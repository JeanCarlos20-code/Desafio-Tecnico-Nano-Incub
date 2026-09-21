🤖 **AI Code Review (S)**

**Summary**

architecture: O diff respeita Infra → Application → Domain: listStatus fica na entidade pura, os rótulos portugueses no controller HTTP, e o default omitted de salas fica alinhado no caso de uso, contrato e Eloquent. O React consome status do backend em vez de derivar Passada dos horários formatados. security: Listagens continuam atrás de auth; o filtro status de salas permanece allowlist no Form Request; o badge Passada é texto React escapado; a derivação usa Clock no servidor. Esconder Editar/Cancelar em Passada é UX, não autorização nova — o PATCH de cancelamento não mudou. smells: Mudança local e explícita: um método de entidade com três ramos, mapeamento de rótulo no controller, defaults de repositório/fakes alinhados, e text-center só na coluna Participantes. Sem wrapper, dead code ou mixed escondendo contrato. tests: A proteção está no nível certo: unidade para listStatus e default do ListRooms, Feature HTTP+MySQL para omitted /rooms e rótulos Passada/Ativa/Cancelada com travelTo, Vitest para text-center e omit-when-Ativas. Fixtures Ativa antigas foram retargetadas, não apagadas. E2E não se aplica.

**❌ Blockers**

None found.

**⚠️ High**

None found.

**📝 Medium**

None found.

**✅ Positive Findings**

- app/Modules/Reservation/Domain/Entities/Reservation.php:23 listStatus é PHP puro (cancelled/passed/active) sem Illuminate nem rótulos.
- app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:81 Clock no Infra HTTP mapeia cancelled/passed/active para Cancelada/Passada/Ativa.
- app/Modules/Room/Application/UseCases/ListRooms.php:15 e IndexRoomController.php:21 default omitted status=active, na direção Infra → Application → Domain.
- resources/js/Pages/Reservation/Index.jsx:393 StatusBadge e RowActions usam reservation.status do backend; não recalculam Passada a partir de starts_at/ends_at H:i.
- routes/web.php:24 GET /rooms e GET /reservations permanecem no grupo middleware auth.
- app/Modules/Room/Infra/Http/Requests/IndexRoomRequest.php:20 status continua sometimes+in:all,active,inactive; omitted não entra em validated().
- app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:38 status da linha vem de Clock::now() no servidor, não de input do cliente.
- resources/js/Pages/Reservation/Index.jsx:393 status_label renderizado como filho de texto do badge, sem dangerouslySetInnerHTML.
- resources/js/Pages/Reservation/Index.jsx:581 RowActions oculta ações quando status !== active; cancelamento continua no serviço Inertia autenticado, não como decisão de identidade.
- app/Modules/Reservation/Domain/Entities/Reservation.php:23 listStatus tem ramificação curta e nomes de token fechados.
- app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:94 match de rótulos fica no adaptador HTTP, não espalhado no React.
- resources/js/Pages/Room/Index.jsx:302 statusQuery omite status quando active, o mesmo padrão já usado em reservas.
- tests/Unit/Room/FakeRoomRepository.php:34 e SignalingRoomRepository.php:15 default listPage='active' acompanha o contrato sem lógica extra.
- tests/Unit/Reservation/ReservationListStatusTest.php:11 cancelled vence inclusive com endsAt passado; linha 30 passed; linha 42 active inclui em andamento e endsAt==now.
- tests/Unit/Room/ListRoomsTest.php:19 execute(0,2) sem status encaminha active e ainda cobre all/inactive.
- tests/Feature/Room/RoomIndexHttpTest.php:24 GET omitido ecoa filters.status active, exclui inativa e soft-deleted; linha 93 status=all inclui inativa.
- tests/Feature/Reservation/ReservationIndexHttpTest.php:477 past→Passada, futuro/em andamento→Ativa, cancelled past→Cancelada; Ativas ainda inclui a linha passada.
- tests/Feature/Reservation/ReservationIndexHttpTest.php:272 fixture Ativa do filtro por sala foi movida para 13:00–13:30 (depois do now congelado), não removida.
- resources/js/Pages/Reservation/Index.test.jsx:162 Participantes text-center; linha 177 Passada com badge slate e travessão sem Editar/Cancelar daquela linha.
- resources/js/Pages/Room/Index.test.jsx:360 select default Ativas; linha 367 visita omitindo status; linha 394 paginação omite status em Ativas e inclui status=all em Todas.

**Verdict**

✅ APPROVED

**Harness gate**

✅ open — review APPROVED and required checks are green.
