🤖 **AI Code Review (S)**

**Summary**

architecture: A trava de data/hora ficou na página Inertia Reservation/Create e no helper ao lado dela. CreateReservation e o fluxo HTTP de store não mudaram: o frontend só reforça UX e a Application continua dona de RF18. security: A trava React é só UX. O POST autenticado ainda passa por FormRequest validated() e CreateReservation recusa starts_at no passado. Sem XSS, segredo no bundle ou validação crítica só no cliente. smells: Helper pequeno e nomeado ao lado da página; Create reusa Field, store e o padrão de foco já existente. Sem any, hook condicional, estado mutado ou abstração especulativa. tests: O comportamento novo está em unit React (helper + Create) com relógio congelado. RF18 de PHP unit e Feature foi preservado sem segundo teste HTTP. E2E não se aplica: não há Playwright e docs/test/e2e.md proíbe repetir a mesma matriz no browser.

**❌ Blockers**

None found.

**⚠️ High**

None found.

**📝 Medium**

None found.

**✅ Positive Findings**

- resources/js/Pages/Reservation/Create.jsx:10 aceita timezone com default UTC e não altera o contrato HTTP.
- resources/js/Pages/Reservation/Create.jsx:64 a guarda isStartInPast interrompe o submit antes de transform/store, sem mover a invariante para o React.
- resources/js/Pages/Reservation/Create.jsx:83 continua postando via store(form), o boundary de Services definido em docs/architecture.md.
- resources/js/Pages/Reservation/minScheduleBounds.js:44 isStartInPast usa a mesma regra starts_at < now e devolve false quando date/time estão vazios.
- app/Modules/Reservation/Application/UseCases/CreateReservation.php:38 a autoridade RF18 (startsAt < clock->now → StartsInPast) permanece na Application.
- app/Modules/Reservation/Infra/Http/Controllers/CreateReservationController.php:24 já envia timezone; o controller não foi alterado.
- resources/js/Pages/Reservation/Index.jsx:249 starts_on da lista segue sem min de hoje.
- resources/js/Pages/Reservation/Edit.jsx:118 o widget de data da edição continua disabled.
- app/Modules/Reservation/Application/UseCases/CreateReservation.php:38 o servidor ainda recusa starts_at < Clock::now(); um POST forjado não depende do min do browser.
- app/Modules/Reservation/Application/Errors/StartsInPast.php:11 a mensagem de erro permanece no backend.
- app/Modules/Reservation/Infra/Http/Controllers/StoreReservationController.php:23 usa $request->validated() e mapeia StartsInPast para withErrors starts_at.
- resources/js/Pages/Reservation/Create.jsx:64 o lock React não substitui autorização nem a regra de escrita.
- resources/js/Pages/Reservation/minScheduleBounds.js:1 PAST_START_MESSAGE é literal estática, renderizada como texto em Field (Create.jsx:269), sem dangerouslySetInnerHTML.
- resources/js/Services/reservations.js:4 store permanece form.post('/reservations') no mesmo-origin Inertia.
- resources/js/Pages/Reservation/minScheduleBounds.js:22 calendarDateInTimeZone / clockTimeInTimeZone / minStartTime / isStartInPast são funções puras e focadas.
- resources/js/Pages/Reservation/minScheduleBounds.js:34 minStartTime devolve string vazia quando a data não é hoje, sem flag booleana extra.
- resources/js/Pages/Reservation/Create.jsx:21 pastStartError é estado local de submit, não Context nem cópia desnecessária de props.
- resources/js/Pages/Reservation/Create.jsx:124 noValidate evita a validação nativa do min roubar o caminho da mensagem portuguesa já exigida pelo spec.
- resources/js/Pages/Reservation/Create.jsx:66 o foco via getElementById segue o padrão já usado em onError (Create.jsx:88).
- resources/js/Pages/Reservation/minScheduleBounds.test.js:13 cobre hoje/HH:MM em UTC e America/Sao_Paulo, inclusive virada de dia.
- resources/js/Pages/Reservation/minScheduleBounds.test.js:24 minStartTime só na data de hoje; vazio no futuro.
- resources/js/Pages/Reservation/minScheduleBounds.test.js:31 isStartInPast: antes de agora é past, igual a agora não é, segundos em now tornam 08:00 past.
- resources/js/Pages/Reservation/Create.test.jsx:74 Date congelado em 2026-09-21T08:00:00.000Z, alinhado ao Independent Test do spec.
- resources/js/Pages/Reservation/Create.test.jsx:206 min da data hoje e min do horário 08:00 com timezone UTC.
- resources/js/Pages/Reservation/Create.test.jsx:213 omite min do horário quando a data é amanhã.
- resources/js/Pages/Reservation/Create.test.jsx:220 submit passado (data ontem e hoje 07:00) mostra a mensagem e não chama form.post/transform.
- resources/js/Pages/Reservation/Create.test.jsx:261 hoje 08:00 ainda posta o transform starts_at/ends_at existente.
- tests/Unit/Reservation/CreateReservationTest.php:48 aceita starts_at igual a Clock::now(); linha 75 ainda recusa antes de agora sem persistir.
- tests/Feature/Reservation/ReservationStoreHttpTest.php:143 POST autenticado com 07:00 (relógio 08:00) devolve a mensagem e não insere a linha Daily.

**Verdict**

✅ APPROVED

**Harness gate**

✅ open — review APPROVED and required checks are green.
