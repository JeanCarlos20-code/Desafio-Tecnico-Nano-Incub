# ADR-001: Delimitar o escopo aos requisitos RF01–RF19 e RNF01–RNF14

- **Data**: 2026-09-16
- **Status**: Aceito
- **Decisores**: Desafio técnico do Painel Administrativo
- **Tags**: architecture, scope, requirements

## Contexto e declaração do problema

O painel administrativo substitui o controle de salas e reservas em planilhas. O desafio publica um conjunto fechado de requisitos funcionais (RF01–RF19) e não funcionais (RNF01–RNF14). Qualquer funcionalidade extra (papéis além de administrador, calendário avançado, API pública, e-mail) ou qualquer corte (pular concorrência, seeder ou validação no servidor) distorce a avaliação. Este ADR registra esse catálogo como a definição de escopo do sistema.

## Motivadores da decisão

- Ser avaliado contra os requisitos explícitos do desafio, e só contra eles.
- Não inventar regra de negócio nem restrição técnica que o enunciado não peça.
- Não omitir item eliminatório ou de entrega (senha hasheada, migrations, README, commits).
- Manter um único documento estável do que o sistema deve e não deve fazer.

## Opções consideradas

- Escopo fechado: implementar exatamente RF01–RF19 e RNF01–RNF14
- Escopo expandido: incluir recursos típicos de produção além do enunciado
- Escopo reduzido: entregar só parte dos requisitos (por exemplo, sem RNF09 ou RF19)

## Resultado da decisão

Opção escolhida: **"Escopo fechado nos requisitos abaixo"**, porque o desafio já decidiu o produto e a stack. Implementação, README e commits devem cobrir este catálogo e nada além dele. Lacunas não listadas (por exemplo, o efeito de excluir sala com reservas) não entram neste ADR; na implementação, usar a solução mais simples e coerente.

### Requisitos funcionais

**Acesso**

- **RF01** — Login de administradores.
- **RF02** — Rotas internas inacessíveis a não autenticados.

**Salas**

- **RF03** — Listar salas com ID, nome, capacidade, situação e data de criação.
- **RF04** — Cadastrar sala.
- **RF05** — Editar sala.
- **RF06** — Excluir sala após confirmação.

**Reservas (fluxo)**

- **RF07** — Criar reserva com sala, responsável, título/finalidade, início, fim e quantidade de participantes.
- **RF08** — Listar reservas com dados principais, da mais próxima para a mais distante.
- **RF09** — Filtrar reservas por sala.
- **RF10** — Filtrar reservas por dia.
- **RF11** — Cancelar reserva mediante confirmação.
- **RF12** — Reserva cancelada libera o intervalo para novas reservas.

**Reservas (regras)**

- **RF13** — Impedir sobreposição de reservas ativas na mesma sala; consecutivas são aceitas.
- **RF14** — Término posterior ao início.
- **RF15** — Duração mínima de 30 minutos e máxima de 4 horas.
- **RF16** — Participantes não ultrapassam a capacidade da sala.
- **RF17** — Sala inativa não recebe nova reserva.
- **RF18** — Horário inicial não pode estar no passado.

**Dados iniciais**

- **RF19** — Seeder com pelo menos um administrador, algumas salas e algumas reservas.

### Requisitos não funcionais

**Stack**

- **RNF01** — PHP 8.2 ou superior.
- **RNF02** — Laravel 11 ou 12.
- **RNF03** — Frontend React com Inertia.js 2.
- **RNF04** — Tailwind CSS.
- **RNF05** — MySQL 8.

**Entrega e configuração**

- **RNF06** — Schema só via migrations Laravel; sem dump SQL como criação do banco.
- **RNF11** — `.env.example` completo; `.env` real fora do versionamento.
- **RNF12** — Preparar e executar o projeto só com o README.
- **RNF13** — Vários commits coerentes; evitar um único commit genérico.

**Qualidade e segurança**

- **RNF07** — Autenticação por starter kit oficial ou Laravel Breeze; não recriar o login.
- **RNF08** — Validação dos formulários no servidor; erros na UI React.
- **RNF09** — Requisições simultâneas não criam reservas conflitantes na mesma sala.
- **RNF10** — Senhas nunca em texto puro (critério eliminatório).
- **RNF14** — Código idiomático em Laravel (Eloquent, Form Requests).

Nota extra (não reescreve o catálogo acima): o [ADR-009](009-edicao-parcial-de-reserva-titulo-e-responsavel.md) registra edição parcial de `title` e `responsible` em reserva ativa. Não existe RF de edição de reserva. Data, hora, sala e participantes continuam imutáveis nesse fluxo. Redução de capacidade da sala ainda não baixa `participants` por essa tela.

### Consequências positivas

- Escopo verificável: cada entrega mapeia para um RF ou RNF.
- Evita inflar o produto com ideias de “sistema real”.
- Critérios eliminatórios (RNF10, stack, migrations, auth) ficam visíveis.

### Consequências negativas

- Pedidos fora desta lista são recusados mesmo se forem úteis.
- Ambiguidades do enunciado não são resolvidas aqui; a implementação escolhe o caminho mais simples.
- O documento não descreve tabelas, telas nem estratégia de lock — só o que é obrigatório cumprir.

## Prós e contras das opções

### Escopo fechado (RF01–RF19 e RNF01–RNF14) ✅ Escolhida

- ✅ Cobre autenticação, salas, reservas, stack, entrega e concorrência.
- ✅ Impede feature extra e impede cortar regra exigida.
- ❌ Não detalha desenho interno; isso fica no código e no README.

### Escopo expandido

- ✅ Mais próximo de um produto completo.
- ❌ Sai do desafio e da avaliação.
- ❌ Mistura requisito obrigatório com invenção.

### Escopo reduzido

- ✅ Entrega mais rápida.
- ❌ Falha RF/RNF omitidos (em especial RNF09, RNF10, RF13–RF18, RF19).

## Links

- [Contexto do projeto](../context.md)
