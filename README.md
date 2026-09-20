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
php artisan db:seed
```

O `php artisan migrate` já cria os três administradores, as três salas e as três reservas de demonstração. O `php artisan db:seed` (RF19) garante os mesmos dados e não duplica salas ou reservas que já existirem.

Na primeira vez no host, instale as dependências com `composer install` e `npm install` antes dos comandos Artisan e Vite.

## Atalho opcional

`composer run setup` é um atalho opcional. Ele instala dependências, copia `.env.example` para `.env` só se o `.env` não existir, gera a chave, roda as migrations e faz o build do frontend.

Use o atalho somente depois que o MySQL 8 no Docker estiver saudável. Sem o banco aceitando conexões, o setup falha.

O setup não executa o seed. Depois dele, o catálogo de demonstração já veio do migrate. O `php artisan db:seed` continua disponível e não duplica esses dados.

## Rodar a aplicação toda

Com o banco no ar e o `.env` preenchido:

```bash
composer run dev
```

Esse script sobe `php artisan serve`, `php artisan queue:listen`, `php artisan pail` e `npm run dev`.

Abra `http://127.0.0.1:8000` e entre com uma das contas da tabela acima.

## Decisões técnicas

O overlap de reservas ativas (RF13–RF18) vive na Application: consecutivas passam, cancelada libera o intervalo, duração, capacidade e sala inativa ficam no use case. A persistência serializa criações, inativações e exclusões da mesma sala com `SELECT … FOR UPDATE` na linha de `rooms` (RNF09, ADR-006). Só a Application não impede duas requisições simultâneas de gravar o mesmo intervalo. Só um índice único (ou exclusão no banco) não expressa consecutivas, reuso após cancelamento, duração, capacidade nem sala inativa. Os dois lados juntos fecham o enunciado sem inventar restrição extra.

`rooms.id` e `reservations.id` são bigint autoincremento (`$table->id()`, `foreignId`). A listagem mostra esse ID e a coluna de situação como `Situação`. `users.id` permanece UUID v7 (`HasUuids`, ADR-002).

## O que ficou de fora

O ADR-001 cortou cadastro público, calendário, e-mail e papéis extras. Edição completa de reserva (data, hora, sala, participantes) continua de fora: só título e responsável mudam em reserva ativa (ADR-009). Ocupação fica travada. Redução de capacidade da sala ainda não baixa `participants` por essa tela; o caminho continua sendo cancelar e recriar. Com mais tempo, esses itens seriam os primeiros a voltar, sem reabrir o recorte do desafio.

## Uso de IA

Cursor (agents) foi usado para planejar, implementar e testar. O autor é responsável pelo código.
