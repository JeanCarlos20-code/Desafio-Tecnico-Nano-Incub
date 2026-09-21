🤖 **AI Code Review (S)**

**Summary**

architecture: O filtro de status da listagem respeita Infra → Application → Domain: o FormRequest valida o enum, o controller adapta HTTP/Inertia, o caso de uso só encaminha e o predicado cancelled_at fica em listPage. Ocupação e rótulos de linha não saíram das camadas documentadas. security: GET /reservations continua atrás de auth; status é allowlist no FormRequest e entra no query builder por comparação, não por SQL interpolado. Ocupação segue ignorando canceladas. A UI esconde ações, mas o cancelamento destrutivo não foi alterado neste diff. smells: A implementação é local e alinhada ao padrão de salas. Há um fallback morto/enganoso em StatusBadge: os dois ramos do ternário são Ativa, o que não distingue linha cancelada se status_label faltar. tests: O comportamento novo está no nível certo: validação e caso de uso em Unit, Eloquent/HTTP em Feature, UI em Vitest. Occupancy permanece no Feature de cancel existente. E2E não foi exigido e o runner Playwright não existe.

**❌ Blockers**

None found.

**⚠️ High**

None found.

**📝 Medium**

- **SMELL-001** — `resources/js/Pages/Reservation/Index.jsx`:605: StatusBadge usa `label || (active ? 'Ativa' : 'Ativa')`: os dois ramos do ternário são iguais, então o fallback nunca mostra Cancelada. Impact: Se uma linha cancelada chegar sem status_label, a badge texto fica Ativa mesmo com estilo slate, distorcendo o contrato visível da listagem. Recommended fix: Usar o rótulo do backend como fonte única ou um fallback `active ? 'Ativa' : 'Cancelada'`.

**✅ Positive Findings**

- IndexReservationRequest valida status no limite HTTP em app/Modules/Reservation/Infra/Http/Requests/IndexReservationRequest.php:24
- Controller fino lê validated status com default active e ecoa filters.status em app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:28
- toListItem deriva status/status_label de cancelledAt no adaptador HTTP em app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:84
- ListReservations encaminha status a listPage sem depender de HTTP/Eloquent em app/Modules/Reservation/Application/UseCases/ListReservations.php:37
- Contrato de Domain ganha status sem Illuminate em app/Modules/Reservation/Domain/Repositories/ReservationRepository.php:30
- Eloquent aplica os três predicados só em listPage em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:53
- hasActiveOverlap permanece whereNull(cancelled_at) em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:37
- A página usa visitIndex do service Inertia em resources/js/Pages/Reservation/Index.jsx:4 e o select Status em resources/js/Pages/Reservation/Index.jsx:232
- Rotas internas de reservas usam middleware auth em routes/web.php:24 e GET /reservations em routes/web.php:27
- status é sometimes + in:all,active,cancelled com mensagem de 422 em app/Modules/Reservation/Infra/Http/Requests/IndexReservationRequest.php:24
- listPage usa when/whereNull/whereNotNull parametrizados em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:53
- Conflito de ocupação continua whereNull(cancelled_at) em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:37
- Controller usa validated() e default active em app/Modules/Reservation/Infra/Http/Controllers/IndexReservationController.php:28
- Título e responsável da linha são JSX interpolado, sem dangerouslySetInnerHTML, em resources/js/Pages/Reservation/Index.jsx:388
- listingHref só monta caminho relativo /reservations em resources/js/Pages/Reservation/Index.jsx:531
- RowActions esconde Editar/Cancelar só na UI em resources/js/Pages/Reservation/Index.jsx:581
- Predicados de listPage estão explícitos e sem SQL cru em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:53
- FakeReservationRepository aplica o mesmo filtro active/cancelled em tests/Unit/Reservation/FakeReservationRepository.php:91
- hasAny passou a exists() sem predicado de cancelamento em app/Modules/Reservation/Infra/Database/Repositories/EloquentReservationRepository.php:82
- statusQuery omite o default active num único helper em resources/js/Pages/Reservation/Index.jsx:534
- FormRequest cobre all/active/cancelled, omissão e 422 em tests/Unit/Reservation/IndexReservationRequestTest.php:11
- ListReservations encaminha status e default active em tests/Unit/Reservation/ListReservationsTest.php:76
- Membership default/active/all/cancelled no fake em tests/Unit/Reservation/ListReservationsTest.php:94
- hasAny verdadeiro só com canceladas em tests/Unit/Reservation/ListReservationsTest.php:120
- Fake aplica where cancelledAt no listPage em tests/Unit/Reservation/FakeReservationRepository.php:91
- Feature omite status como Ativas e ecoa filters.status em tests/Feature/Reservation/ReservationIndexHttpTest.php:58
- period=today&status=cancelled ainda restringe a janela em tests/Feature/Reservation/ReservationIndexHttpTest.php:316
- Cancel standalone/deactivate/delete some na omissão e aparece em status=all em tests/Feature/Reservation/ReservationIndexHttpTest.php:387
- Membership HTTP all/active/cancelled e rótulos em tests/Feature/Reservation/ReservationIndexHttpTest.php:414
- 422 status weekend na rota em tests/Feature/Reservation/ReservationIndexHttpTest.php:476
- Occupancy de intervalo cancelado permanece em tests/Feature/Reservation/ReservationCancelHttpTest.php:23
- Vitest do select Status default Ativas em resources/js/Pages/Reservation/Index.test.jsx:105
- Linha cancelada mostra Cancelada, dash e sem Editar da linha 2 em resources/js/Pages/Reservation/Index.test.jsx:134
- Visita Canceladas preserva room/period/range/limit em resources/js/Pages/Reservation/Index.test.jsx:614
- Paginação mantém status=cancelled|all e omite active em resources/js/Pages/Reservation/Index.test.jsx:692
- Período radios e datas permanecem, sem rádio de passado, em resources/js/Pages/Reservation/Index.test.jsx:733

**Verdict**

✅ APPROVED

**Harness gate**

✅ open — review APPROVED and required checks are green.
