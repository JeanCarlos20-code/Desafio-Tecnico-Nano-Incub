# Desafio-Tecnico-Nano-Incub

Administradores entram em `/login` com e-mail e senha. As rotas do painel, incluindo `/reservations`, exigem uma sessão autenticada.

Após `php artisan migrate`, o banco contém três administradores padrão:

| Nome | E-mail | Senha |
| --- | --- | --- |
| Gertrudes | teste@mail.com | Senha123 |
| Marcelo | teste2@mail.com | Senha123 |
| Emerson | teste3@mail.com | Senha123 |

## Pré-requisitos

Para preparar e executar o painel neste repositório, você precisa de:

- PHP 8.2+
- Composer
- Node.js e npm
- Docker Engine com Compose v2 (`docker compose`, sem hífen)

A aplicação Laravel e o Vite rodam no host. Só o MySQL sobe no Docker.

## Banco de dados (MySQL 8 no Docker)

O arquivo `compose.yml` na raiz do repositório sobe o motor MySQL 8 (imagem `mysql:8.0`). Não use o comando antigo `docker-compose`.

Na raiz do repositório:

```bash
docker compose up -d
```

Espere o serviço ficar saudável (`healthy`) antes de rodar migrations ou `composer run setup`. Se o MySQL 8 ainda não aceitar conexões, `php artisan migrate` e `composer run setup` falham até o Compose ficar pronto.

## Variáveis de ambiente

Copie `.env.example` para `.env`. Depois gere `APP_KEY` com:

```bash
php artisan key:generate
```

Não cole o valor da chave no README nem em commits.

Nomes obrigatórios (preencha no `.env`; este documento lista só os nomes):

- `APP_KEY`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Se o `.env` já existir, `composer run setup` não o sobrescreve (a cópia ocorre só quando o arquivo falta). `APP_KEY` ainda precisa existir.

## Migrations

Com o MySQL 8 no Docker saudável:

```bash
php artisan migrate
```

Os administradores padrão entram pelo migrate. `php artisan db:seed` não é um passo obrigatório.

Na primeira vez no host, instale as dependências com `composer install` e `npm install` antes dos comandos Artisan e Vite.

## Atalho opcional

`composer run setup` é um atalho opcional. Ele instala dependências, copia `.env.example` para `.env` só se o `.env` não existir, gera a chave, roda as migrations e faz o build do frontend.

Use o atalho somente depois que o MySQL 8 no Docker estiver saudável. Sem o banco aceitando conexões, o setup falha.

## Rodar a aplicação toda

Com o banco no ar e o `.env` preenchido:

```bash
composer run dev
```

Esse script sobe `php artisan serve`, `php artisan queue:listen`, `php artisan pail` e `npm run dev`.

Abra `http://127.0.0.1:8000` e entre com uma das contas da tabela acima.
