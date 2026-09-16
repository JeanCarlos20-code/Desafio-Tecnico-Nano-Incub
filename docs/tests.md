# Estratégia de testes

Os testes protegem regras de negócio, contratos e comportamentos relevantes.

Cobertura não substitui qualidade.

Princípio: a **IA propõe** decisões interpretativas; o **harness impõe** invariantes; o **humano aprova** mudanças relevantes.

## Papéis

| Papel          | Responsabilidade                                                                    |
| -------------- | ----------------------------------------------------------------------------------- |
| Docs / harness | Definem critérios, matriz, fronteiras, contratos e cobertura.                       |
| Planner        | Aplica a matriz e propõe testes com justificativa. Não inventa classificação.       |
| Humano         | Aprova o plano e pode corrigir a classificação dos testes.                          |
| Executor       | Implementa exatamente o aprovado. Não inventa testes extras ou reclassifica níveis. |
| Reviewer       | Segunda barreira: realiza interpretação independente e identifica lacunas.          |

Não é o mesmo agente que planeja, implementa e dá a palavra final sobre testes.

## Qualidade dos testes

Todo teste deve ser:

- **determinístico:** produz o mesmo resultado nas mesmas condições;
- **conciso:** protege um comportamento principal sem preparação excessiva;
- **relevante:** cobre regra, contrato, erro ou risco real;
- **compreensível:** cenário, ação e resultado esperado são claros;
- **durável:** continua válido se a implementação interna mudar sem alterar o comportamento.

Evite:

- testes criados somente para aumentar cobertura;
- testes artificiais;
- dependência da ordem de execução;
- estado compartilhado entre testes;
- horário ou aleatoriedade sem controle;
- `sleep()` para sincronização;
- mocks fora de fronteiras reais;
- testes de métodos privados;
- testes de getters ou setters triviais;
- testes de comportamento interno do Laravel ou de bibliotecas externas.

Não crie teste apenas porque um arquivo de produção existe.

Antes de criar um teste, identifique qual comportamento relevante ele protege.

## Matriz de decisão

A IA aplica esta tabela.

Não invente classificação e não utilize critérios subjetivos como “fluxo importante”.

| Mudança                               | Unit  | Integration | E2E / Feature |
| ------------------------------------- | ----- | ----------- | ------------- |
| Regra isolada                         | sim   | não         | não           |
| Validação pura                        | sim   | não         | não           |
| Operação + persistência               | sim\* | sim         | não           |
| Persistência / constraint             | não\* | sim         | não           |
| Transação                             | sim\* | sim         | não           |
| Concorrência                          | não\* | sim         | não           |
| Nova rota HTTP                        | sim\* | sim\*       | sim           |
| Mudança de contrato HTTP              | sim\* | sim\*       | sim           |
| Auth / security                       | sim\* | sim         | sim\*         |
| Migration com comportamento relevante | não   | sim         | não           |

`*` = quando aplicável.

Um mesmo comportamento não precisa ser repetido integralmente em todos os níveis.

O nível deve ser escolhido conforme a fronteira que precisa ser comprovada.

## Classificação no plano

Cada cenário de teste proposto pelo planner deve informar:

- nível;
- arquivo;
- ação;
- comportamento protegido;
- casos;
- justificativa;
- regra ou contrato relacionado.

Exemplo:

```json
{
    "level": "integration",
    "file": "tests/Integration/Reservation/CreateReservationTest.php",
    "action": "create",
    "proves": "a operação persiste os dados de forma consistente",
    "cases": ["success", "failure"],
    "reason": "o comportamento atravessa regra e banco de dados",
    "rule": "contrato definido nos documentos do projeto"
}
```

Se o planner não conseguir classificar com segurança entre unitário, integração e E2E/Feature, registre a incerteza para aprovação humana.

Lista vazia é permitida.

Decisão silenciosa não é.

## Testes unitários

Testes unitários validam lógica relevante de forma isolada.

São adequados para:

- regras;
- decisões;
- cálculos;
- transformações relevantes;
- validações independentes de infraestrutura;
- objetos com comportamento próprio;
- autorização isolável.

Não devem acessar:

- banco real;
- rede;
- filesystem real;
- serviços externos.

Não crie unitário artificial para:

- Models passivos;
- controllers sem lógica própria;
- Form Requests que apenas declaram regras triviais do framework;
- routes;
- migrations;
- seeders simples;
- resources;
- configuração;
- bootstrap;
- constantes;
- getters/setters;
- código gerado.

Mocks devem representar fronteiras reais.

Exemplos:

- gateway externo;
- storage;
- relógio;
- integração externa;
- dependência cuja substituição seja realmente necessária.

Não mocke a própria regra que deveria ser testada.

### Organização

Prefira manter a organização de testes já adotada pelo projeto.

Quando não houver padrão anterior, utilize a estrutura convencional do Laravel:

```text
tests/
├── Unit/
├── Feature/
└── Integration/   # somente se o projeto decidir separar explicitamente
```

Não crie diretórios apenas para preencher uma arquitetura de testes.

Se `Feature` já for utilizado pelo projeto para testes que atravessam Laravel + banco + HTTP, preserve essa convenção.

A organização física não altera a classificação conceitual do teste.

## Testes de integração

Testes de integração validam comportamento que depende de infraestrutura real da aplicação.

Podem envolver:

- Eloquent;
- Query Builder;
- banco de dados;
- migrations;
- constraints;
- transações;
- locks;
- relacionamentos;
- concorrência;
- persistência e leitura;
- mais de um componente da aplicação.

O teste deve provar efeito observável.

Não basta apenas verificar que nenhuma exception ocorreu.

Exemplos de efeitos observáveis:

- registro persistido;
- registro não persistido;
- rollback executado;
- constraint respeitada;
- relacionamento correto;
- concorrência preservando uma invariante.

### Quando integração é obrigatória

Integração é necessária quando o comportamento depende de pelo menos uma fronteira real, como:

- regra + banco;
- persistência;
- constraint;
- transação;
- lock;
- concorrência;
- relacionamento;
- comportamento específico do ORM ou banco;
- persistência seguida de leitura.

Não substitua um teste de integração necessário por mocks apenas para tornar o teste mais simples.

### Banco de teste

Utilize o banco e a estratégia de teste definidos pelo projeto.

Não substitua silenciosamente o banco utilizado pela aplicação por outro engine apenas porque é mais simples para testes.

Diferenças de comportamento entre bancos podem invalidar testes de:

- constraints;
- locks;
- transações;
- concorrência;
- tipos;
- SQL específico.

A configuração do ambiente de teste deve permanecer isolada de desenvolvimento e produção.

Nunca:

- conecte testes ao banco de produção;
- utilize credenciais de produção;
- reutilize dados reais;
- considere um teste executado quando a dependência necessária estava indisponível.

### Falha de infraestrutura

Se a infraestrutura necessária para um teste obrigatório não estiver disponível:

```text
NOT_EXECUTED
```

O teste não pode ser informado como `PASSED`.

Uma tarefa que depende desse teste não possui verificação completa enquanto ele não for executado.

## Testes HTTP / Feature / E2E

Testes HTTP protegem o contrato observável da aplicação.

Verifique quando aplicável:

- método;
- rota;
- autenticação;
- autorização;
- status;
- redirect;
- sessão;
- erros de validação;
- dados enviados para a resposta;
- efeito prometido pela operação.

No contexto Laravel, testes `Feature` podem atravessar boa parte da aplicação.

Isso não significa que todo comportamento precise ser validado novamente através de HTTP.

Não replique toda a matriz de unitários e integrações em Feature/E2E.

### Quando avaliar E2E / Feature

Se a tarefa cria ou altera comportamento observável de uma rota, deve existir avaliação de cobertura HTTP.

Crie ou altere um teste somente quando o contrato não estiver adequadamente protegido.

Exemplos:

```text
regra interna sem mudança HTTP
→ provavelmente nenhum novo teste HTTP

nova rota
→ avaliar/criar teste HTTP

mudança de status, redirect, validação ou resposta
→ atualizar ou criar teste HTTP
```

O teste HTTP deve focar no contrato, não em detalhes internos de implementação.

## Laravel e testes

Utilize os recursos de teste fornecidos pelo Laravel quando forem adequados.

Podem incluir:

- factories;
- database assertions;
- HTTP testing;
- authentication helpers;
- exception assertions;
- event / queue / notification fakes;
- filesystem fake;
- time helpers.

Fakes do Laravel devem ser utilizados quando a intenção é substituir uma fronteira real.

Não use `Event::fake()`, `Queue::fake()`, `Notification::fake()` ou equivalentes automaticamente em todos os testes.

Um fake que impede a execução do comportamento que deveria ser comprovado torna o teste inválido.

## Factories e fixtures

Factories devem facilitar preparação de dados, não esconder o cenário.

Evite factories que:

- criem grande quantidade de dados irrelevantes;
- possuam side effects inesperados;
- criem relacionamentos que o teste não solicitou;
- tornem impossível entender o estado inicial.

Use states quando representarem cenários recorrentes e claros.

Não crie abstrações complexas de fixture para poucos testes simples.

## Tempo

Comportamentos dependentes do relógio devem utilizar controle determinístico de tempo quando necessário.

Prefira os mecanismos de manipulação de tempo fornecidos pelo Laravel ou pela biblioteca utilizada pelo projeto.

Evite depender diretamente do relógio real quando isso puder tornar o teste instável.

Não utilize `sleep()` para esperar mudança de estado quando existir alternativa determinística.

## Concorrência

Quando a correção depende de concorrência, um teste sequencial não é suficiente para provar a proteção.

O teste deve exercer a estratégia real utilizada pela aplicação sempre que tecnicamente viável.

Mocks não provam:

- lock do banco;
- isolamento de transação;
- constraint concorrente;
- comportamento simultâneo real.

Testes de concorrência devem continuar determinísticos e evitar dependência de timing arbitrário.

## Testes existentes são contratos

Testes existentes representam comportamentos e regressões protegidos.

Quando um teste que anteriormente passava falhar depois de uma alteração, a hipótese padrão é:

**a implementação está errada.**

Não é permitido:

- mudar resultado esperado apenas para acompanhar código novo;
- remover cenário;
- reduzir assertions;
- marcar como skipped sem justificativa;
- adicionar mock apenas para esconder a regressão;
- apagar o teste;
- reescrever o cenário apenas para deixá-lo verde.

Quando houver mudança intencional de comportamento:

1. a mudança deve ser identificada;
2. o humano deve aprová-la;
3. somente então os testes correspondentes podem ser atualizados.

Em refatoração, o comportamento esperado permanece igual.

Em correção de bug, prefira criar primeiro um teste capaz de reproduzir a falha.

## Cobertura

Meta do código afetado:

```text
>= 80%
```

Não persiga 100% criando testes artificiais.

Cobertura alta não compensa testes:

- irrelevantes;
- redundantes;
- frágeis;
- acoplados à implementação;
- sem comportamento observável.

Cobertura deve ajudar a identificar lacunas, não determinar sozinha quais testes precisam existir.

Código trivial não precisa receber teste artificial apenas para aumentar a porcentagem.

## Performance

Testes de performance são uma categoria separada.

Não inclua benchmarks, load, stress ou soak tests automaticamente na verificação cotidiana.

Crie somente quando houver necessidade concreta.

Não utilize limites frágeis de milissegundos em testes funcionais.

Problemas evidentes de query ou desempenho devem ser avaliados, mas não transforme todo teste em benchmark.

## Verificação do harness

Em tarefas de implementação, o verify deve executar os gates definidos pelo projeto.

Para Laravel, normalmente devem ser considerados:

```text
php artisan test
```

e, quando configurados no projeto:

```text
formatter
static analysis
lint
build/frontend necessário ao fluxo
```

Exemplos possíveis:

```text
Laravel Pint
PHPStan / Larastan
```

Não adicione uma ferramenta apenas porque ela aparece neste documento.

Somente execute gates que estejam configurados ou tenham sido aprovados para o projeto.

Não declare a tarefa pronta quando um gate obrigatório falhar.

Teste obrigatório não executado por indisponibilidade de dependência deve ser informado como `NOT_EXECUTED`, nunca como aprovado.

## Regra final

Antes de criar ou alterar um teste, responda:

**qual comportamento relevante este teste protege?**

Se a resposta não estiver clara, provavelmente o teste não deveria existir.

Quando código novo quebrar um teste existente correto:

**corrija o código. Não enfraqueça o teste.**
