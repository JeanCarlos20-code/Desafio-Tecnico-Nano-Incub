# Desafio-Tecnico-Nano-Incub

Administradores entram em `/login` com e-mail e senha. As rotas do painel, incluindo `/reservations`, exigem uma sessão autenticada.

Após `php artisan migrate`, o banco contém três administradores padrão:

| Nome      | E-mail          | Senha    |
| --------- | --------------- | -------- |
| Gertrudes | teste@mail.com  | Senha123 |
| Marcelo   | teste2@mail.com | Senha123 |
| Emerson   | teste3@mail.com | Senha123 |

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

Nomes obrigatórios (preencha no `.env`; este documento lista só os nomes, exceto `HASH_DRIVER`):

- `APP_KEY`
- `HASH_DRIVER` — obrigatório com o valor `argon`. Sem essa variável o Laravel assume bcrypt e o `/login` falha (`This password does not use the Bcrypt algorithm`), porque as senhas gravadas são Argon2.
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Se o `.env` já existir, `composer run setup` não o sobrescreve (a cópia ocorre só quando o arquivo falta). `APP_KEY` e `HASH_DRIVER=argon` ainda precisam existir.

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

Criei três tabelas principais: `users`, `rooms` e `reservations`.

**`users`**

| Coluna           | Tipo MySQL               | Observação     |
| ---------------- | ------------------------ | -------------- |
| `id`             | `char(36)`               | PK, UUID v7    |
| `name`           | `varchar(255)`           |                |
| `email`          | `varchar(255)`           | unique         |
| `password`       | `varchar(255)`           | hash Argon2    |
| `remember_token` | `varchar(100)`, nullable | sessão Laravel |
| `created_at`     | `timestamp`, nullable    |                |
| `updated_at`     | `timestamp`, nullable    |                |
| `deleted_at`     | `timestamp`, nullable    | soft delete    |

**`rooms`**

| Coluna       | Tipo MySQL                | Observação        |
| ------------ | ------------------------- | ----------------- |
| `id`         | `bigint unsigned`         | PK, autoincrement |
| `name`       | `varchar(255)`            |                   |
| `capacity`   | `int unsigned`            |                   |
| `is_active`  | `tinyint(1)`, default `1` | situação da sala  |
| `created_at` | `timestamp`, nullable     |                   |
| `updated_at` | `timestamp`, nullable     |                   |
| `deleted_at` | `timestamp`, nullable     | soft delete       |

**`reservations`**

| Coluna         | Tipo MySQL            | Observação                 |
| -------------- | --------------------- | -------------------------- |
| `id`           | `bigint unsigned`     | PK, autoincrement          |
| `room_id`      | `bigint unsigned`     | FK para `rooms.id`         |
| `responsible`  | `varchar(255)`        | texto, sem FK para `users` |
| `title`        | `varchar(255)`        |                            |
| `starts_at`    | `datetime`            |                            |
| `ends_at`      | `datetime`            |                            |
| `participants` | `int unsigned`        |                            |
| `cancelled_at` | `datetime`, nullable  | `null` = ativa             |
| `created_at`   | `timestamp`, nullable |                            |
| `updated_at`   | `timestamp`, nullable |                            |

Para `users`, optei pelo UUIDv7 por ser um identificador não sequencial ideal para exposição externa, mantendo a ordenação temporal ao combinar timestamp e aleatoriedade. Para o armazenamento de senhas, selecionei o Argon2i por ser um algoritmo baseado em custo de memória (memory-hard). Essa abordagem mitiga ataques de canal lateral e encarece significativamente tentativas de força bruta em massa com o uso de GPUs ou ASICs.

Foi escolhido o bigint unsigned nos id da chave primária pois é o padrão do laravel além de aguentar número bem maior que o int normal, tanto que em participants foi usado o int ao invés do bigint, e o unsigned é porque nenhum dos dados podem ser negativos

Já `rooms` e `reservations` usam IDs numéricos incrementais, por serem mais simples e suficientes para o escopo do projeto. Em um sistema distribuído ou com necessidade maior de IDs externos, UUIDv7 poderia ser considerado também.

Em reservations, decidi manter o responsável como texto livre. O desafio não deixa claro se o responsável pela reunião precisa necessariamente ser um usuário cadastrado no sistema. Como o painel administrativo também pode ser usado para registrar reuniões lideradas por pessoas externas ao sistema, preferi não criar um vínculo obrigatório com users.

Na arquitetura, optei por uma abordagem hexagonal para isolar as regras de negócio do Laravel e da infraestrutura. O domínio/application fica responsável pelas regras, enquanto a infraestrutura integra Laravel, Eloquent/MySQL, autenticação, Inertia e demais detalhes externos.

No frontend, também separei a camada que faz comunicação com a aplicação dos componentes React, evitando misturar acesso a dados com lógica de interface.

Ao inativar uma sala que possui reuniões futuras, o usuário pode escolher entre manter ou cancelar essas reservas, sendo avisado das consequências. Na exclusão de uma sala, as reservas futuras são canceladas automaticamente, já que não faria sentido manter reuniões vinculadas a uma sala removida.

Para exclusão, utilizei Soft Delete, preenchendo `deleted_at` em vez de remover fisicamente o registro, preservando o histórico.

Não implementei alteração de data, horário das reservas e sala para evitar complexidade fora do escopo. Caso seja necessário alterar esse período ou a sala, a reserva pode ser cancelada e criada novamente.

Além do filtro por dia solicitado, adicionei a possibilidade de filtrar reservas por intervalo de datas, mantendo um período padrão selecionado na tela.

Para evitar reservas concorrentes no mesmo horário, utilizei transação com `SELECT ... FOR UPDATE`, bloqueando a sala durante a validação e criação da reserva. Assim, operações simultâneas sobre a mesma sala são serializadas.

A mesma estratégia é usada quando uma reserva é criada ao mesmo tempo em que outra pessoa inativa a sala. A operação que conseguir o lock primeiro é concluída e a seguinte revalida o estado atualizado antes de continuar.

Na alteração da capacidade de uma sala, verifico as reservas futuras e ativas. Se existir alguma reserva com quantidade de participantes maior que a nova capacidade, a alteração é bloqueada e o usuário é informado de que essas reservas precisam ser ajustadas antes.

## O que ficou de fora

Edição da data, horário ou sala de uma reserva, pois exigiria adaptar novamente as regras de conflito e concorrência.

Suporte a multi-tenant, onde usuários de organizações diferentes não poderiam visualizar ou administrar as mesmas salas e reservas.

Recuperação de reservas canceladas durante a inativação de uma sala. Em uma evolução do sistema, ao reativar a sala seria possível verificar quais reservas foram canceladas nesse processo e permitir ao usuário escolher quais deseja restaurar, validando novamente disponibilidade e conflitos.

## Uso de IA

Usei IA como acelerador durante o desenvolvimento.

As regras de negócio e decisões arquiteturais foram definidas por mim. A IA foi utilizada principalmente para auxiliar na implementação, planejamento, testes, revisão de código e documentação, sempre seguindo as regras previamente definidas.

Também utilizei um workflow próprio para controlar o uso da IA, onde consigo revisar o plano antes da implementação, definir quais testes devem ser executados, limitar alterações ao escopo da tarefa e separar trabalhos independentes em diferentes Git worktrees.
