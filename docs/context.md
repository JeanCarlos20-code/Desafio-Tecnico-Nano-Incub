# Contexto do projeto

O projeto é um sistema administrativo para gerenciamento de salas de reunião e reservas.

O objetivo é substituir o controle realizado por planilhas por uma aplicação centralizada, permitindo que administradores cadastrem salas, criem e acompanhem reservas, cancelem agendamentos e evitem conflitos de horário.

O sistema é desenvolvido como parte de um desafio técnico e não precisa representar uma solução completa de produção. A prioridade é entregar uma aplicação funcional, simples de compreender e que proteja corretamente as regras de negócio exigidas.

## Stack

A aplicação utiliza:

- PHP 8.2 ou superior;
- Laravel 11 ou Laravel 12;
- React integrado ao Laravel por meio do Inertia.js 2;
- Tailwind CSS;
- MySQL 8.

A autenticação administrativa deverá utilizar os recursos fornecidos pelo ecossistema Laravel. Não deverá ser criado um mecanismo próprio de autenticação.

## Escopo principal

O sistema deverá permitir:

- autenticação de administradores;
- cadastro, edição, listagem e exclusão de salas;
- criação e listagem de reservas;
- filtro de reservas por sala e dia;
- cancelamento de reservas;
- validação da capacidade das salas;
- controle de salas ativas e inativas;
- prevenção de conflitos de horário;
- proteção contra conflitos causados por requisições concorrentes.

As regras relacionadas às reservas são a parte mais importante da aplicação.

Uma reserva deverá respeitar, entre outras, as seguintes condições:

- o horário final deve ser posterior ao horário inicial;
- a duração deve estar entre 30 minutos e 4 horas;
- o número de participantes não pode ultrapassar a capacidade da sala;
- salas inativas não podem receber novas reservas;
- reservas não podem começar no passado;
- reservas ativas da mesma sala não podem possuir sobreposição de horário;
- reservas consecutivas são permitidas;
- reservas canceladas deixam de ocupar o intervalo de horário correspondente;
- requisições simultâneas não podem produzir reservas conflitantes.

## Princípios de implementação

O projeto deve evoluir somente conforme a necessidade do desafio.

Ao trabalhar neste projeto:

- respeite os requisitos definidos no desafio;
- não invente regras de negócio que não sejam necessárias;
- quando o desafio deixar uma decisão em aberto, escolha a solução mais simples e coerente e documente a decisão no `README.md`;
- utilize os recursos e padrões do Laravel sempre que eles forem suficientes;
- evite abstrações ou arquiteturas que não tragam benefício direto para o escopo atual;
- mantenha as regras de negócio importantes protegidas no servidor;
- preserve a consistência dos dados, especialmente durante a criação de reservas;
- considere concorrência ao verificar disponibilidade e persistir uma reserva;
- utilize migrations para toda alteração estrutural no banco;
- mantenha validações relevantes no backend, independentemente das validações existentes na interface;
- escreva testes compatíveis com as regras de negócio implementadas;
- faça a menor mudança coerente possível para atender à tarefa atual.

## Decisões técnicas

Os pontos que não estiverem explicitamente definidos pelo desafio podem ser decididos durante a implementação.

Decisões relevantes deverão ser registradas no `README.md`, especialmente quando envolverem:

- modelagem do banco de dados;
- estratégia para impedir reservas concorrentes;
- organização das regras de reserva;
- limitações conhecidas;
- funcionalidades deliberadamente deixadas de fora.

Não implemente funcionalidades futuras apenas porque poderiam ser úteis em um sistema real.

## Prioridades

Ao tomar decisões de implementação, considere a seguinte ordem:

1. funcionamento correto da aplicação;
2. cumprimento das regras de negócio;
3. consistência das reservas em situações concorrentes;
4. clareza e legibilidade do código;
5. uso idiomático dos recursos do Laravel;
6. simplicidade da solução;
7. acabamento visual.

O objetivo não é construir a arquitetura mais sofisticada possível, mas entregar uma solução correta, explicável e adequada ao escopo do desafio.

Para regras técnicas e organização da aplicação, consulte `docs/architecture.md`.

Para comportamento do agente durante alterações, consulte `docs/persona-agent.md`.

Para critérios de revisão de código, consulte `docs/review.md`.
