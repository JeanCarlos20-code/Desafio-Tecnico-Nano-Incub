# ADR-007: Adotar Laravel Breeze para o login

- **Data**: 2026-09-20
- **Status**: Aceito
- **Decisores**: Time do Painel Administrativo
- **Tags**: architecture, security, identity, laravel

## Contexto e declaração do problema

RNF07 exige autenticação pelo starter kit oficial ou pelo Laravel Breeze, sem recriar o login. O painel já tinha sessão Laravel e o contrato de usuário do [ADR-002](002-usuario-minimo-uuidv7-argon2-auth-laravel.md) (UUID v7, soft delete, `HASH_DRIVER=argon`, sem `email_verified_at`, modelo no módulo User). O recorte “auth nativa sem kit” desse ADR deixava o POST `/login` num use case próprio (`AuthenticateUser`). Isso não cumpre RNF07. O instalador `breeze:install` também não serve: ele publicaria cadastro, reset, verificação e `Auth/Login`, e poderia sobrescrever `routes/web.php`.

## Motivadores da decisão

- Cumprir RNF07 com código de login do Breeze, não com um use case paralelo.
- Preservar o contrato de usuário do ADR-002.
- Manter a tela `User/Login`, as rotas `/` e `/login`, e o redirecionamento para `/reservations`.
- Não reabrir cadastro, recuperação de senha, verificação de e-mail, JWT ou papéis (ADR-001).
- Não executar `php artisan breeze:install` neste repositório.

## Opções consideradas

- Breeze adaptado: `composer require laravel/breeze --dev` e copiar só os stubs de login
- Starter kit oficial Laravel 12 (Fortify)
- Manter o login customizado (`LoginController` + `AuthenticateUser`)

## Resultado da decisão

Opção escolhida: **"Breeze adaptado"**, porque satisfaz o texto de RNF07 sem abandonar o ADR-002 e sem deixar o instalador apagar rotas e UI já existentes.

O pacote `laravel/breeze` entra como dependência de desenvolvimento. Os stubs `AuthenticatedSessionController` e `LoginRequest` vão para `app/Modules/User/Infra/Http`. `create()` renderiza `User/Login`. `store()` chama `LoginRequest::authenticate()` (`Auth::attempt` com e-mail e senha; remember-me falso), regenera a sessão e redireciona para `reservations.index`. Falha e throttle continuam no campo `credentials`. Cadastro, reset e verificação ficam de fora. O schema e o hasher do ADR-002 não mudam.

Este ADR substitui só o recorte “auth nativa sem kit” do ADR-002. O restante daquele ADR permanece.

### Consequências positivas

- Login idiomático do Breeze, alinhado a RNF07.
- Contrato de usuário (UUID v7, Argon, soft delete) intacto.
- Superfície de auth menor: sem rotas de cadastro, reset ou verificação.

### Consequências negativas

- O instalador oficial do Breeze não pode rodar neste tree; a adaptação dos stubs é manual.
- O use case `AuthenticateUser` deixa de existir; o login passa a viver no FormRequest do kit.

## Prós e contras das opções

### Breeze adaptado ✅ Escolhida

- ✅ Cumpre RNF07 com o código de login do kit.
- ✅ Preserva ADR-002, `User/Login` e as rotas do painel.
- ❌ Exige copiar stubs em vez de `breeze:install`.

### Starter kit oficial Laravel 12 (Fortify)

- ✅ Kit oficial do Laravel 12.
- ❌ Fortify sozinho não atende o texto “starter kit ou Laravel Breeze” deste projeto.
- ❌ Trairia cadastro, reset, verificação e um User padrão fora do ADR-002.

### Manter o login customizado

- ✅ Já funciona com sessão Laravel e Argon.
- ❌ Recria o login ao lado do kit; RNF07 não aceita esse desvio.

## Links

- Substitui: [ADR-002](002-usuario-minimo-uuidv7-argon2-auth-laravel.md) (somente o recorte “auth nativa sem kit”)
- [ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14](001-delimitar-escopo-aos-requisitos-rf-e-rnf.md)
- [ADR-002: Usuário mínimo com UUID v7, soft delete e hash Argon nativo do Laravel](002-usuario-minimo-uuidv7-argon2-auth-laravel.md)
