# ADR-003: React e PHP no mesmo projeto Laravel via Inertia.js 2

- **Data**: 2026-09-16
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, frontend, inertia, laravel

## Contexto e declaração do problema

O desafio exige React integrado ao Laravel por Inertia.js 2 (RNF03), não um frontend separado consumindo API. Um arranjo “monorepo” com `apps/web` + `apps/api`, ou um SPA React isolado, criaria duas entregas, um contrato HTTP próprio e um README partido — o contrário de RNF07 (auth do ecossistema Laravel), RNF08 (validação no servidor com erros na UI React) e RNF12 (subir o projeto só com o README). Este ADR fixa a forma do repositório.

## Motivadores da decisão

- Cumprir RNF03 com Inertia.js 2, não com SPA + API.
- Uma entrega, um `.env`, um README (RNF12).
- Reusar sessão e autenticação do Laravel (RNF07), sem JWT nem CORS de UI.
- Validar no servidor e devolver erros às páginas React (RNF08, RNF14).
- Não inventar fronteira de API que o enunciado não pede (ADR-001).

## Opções consideradas

- Projeto único Laravel: PHP e React no mesmo repositório, UI via Inertia
- SPA React separado consumindo uma API Laravel
- Monorepo com dois pacotes (`apps/web` + `apps/api`) no mesmo Git

## Resultado da decisão

Opção escolhida: **"Projeto único Laravel com React via Inertia.js 2"**, porque o desafio pede integração Inertia, não um frontend desacoplado.

`Painel-administrativo` é um único app Laravel. Backend em `app/`, `routes/web.php`, `database/`. UI React em `resources/js/` (`Pages/`, `Components/`, `app.jsx`), estilos em `resources/css/`. `composer.json`, `package.json`, `vite.config.js` e `artisan` convivem na raiz. Não há API pública como contrato da UI: controladores Inertia devolvem páginas e props. Isso é um monólito com frontend colocalizado, não um monorepo de múltiplos pacotes.

### Consequências positivas

- Stack do enunciado (RNF03) sem camada REST inventada.
- Auth e sessão compartilhada: o mesmo guard protege rotas e páginas.
- Um clone, `composer` + `npm`, um Vite — alinhado a RNF12.
- Form Requests e erros de validação fluem direto para o React (RNF08).

### Consequências negativas

- Frontend e backend versionam e implantam juntos; não dá para publicar só a UI.
- `resources/js` fica acoplado a rotas Laravel; extrair um SPA depois exige reescrever o contrato.
- Quem espera um monorepo com workspaces (`apps/*`) não encontra essa árvore — e não deve criá-la aqui.

## Prós e contras das opções

### Projeto único Laravel + Inertia ✅ Escolhida

- ✅ Atende RNF03, RNF07, RNF08 e RNF12 sem inventar API.
- ✅ Estrutura idiomática do Laravel 12 (Vite na raiz, páginas em `resources/js`).
- ❌ Acopla o ciclo de vida de PHP e React.

### SPA React + API Laravel

- ✅ Frontend independente, contrato HTTP explícito.
- ❌ Não é “React com Inertia.js 2”.
- ❌ Exige auth de API, CORS e dois READMEs — fora do ADR-001.

### Monorepo com dois pacotes

- ✅ Um Git com fronteira clara entre UI e API.
- ❌ Continua sendo dois apps; o desafio pede um.
- ❌ Workspaces e pipelines extras sem ganho para a avaliação.

## Links

- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [Contexto do projeto](../context.md)
