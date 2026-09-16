# Revisão de código PHP/Laravel

Ao revisar código, procure problemas reais de correção, segurança, integridade dos dados, arquitetura, manutenção e testes.

Não sugira mudanças apenas por preferência pessoal.

Papéis: docs/harness definem critérios; o planner propõe testes com justificativa; o humano aprova; o executor implementa o aprovado; o **reviewer é a segunda barreira** e realiza uma interpretação independente.

Princípio: a IA propõe; o harness impõe invariantes; o humano aprova mudanças relevantes.

Além do código de produção, revise **testes** e **código inventado pela IA**, como abstrações sem uso, helpers genéricos, código morto e preparação para funcionalidades que a tarefa não pediu.

## Prioridade da revisão

Analise nesta ordem:

1. Correção da regra de negócio.
2. Segurança.
3. Integridade dos dados.
4. Respeito à arquitetura e aos documentos do projeto.
5. Tratamento de erros.
6. Testes.
7. Legibilidade.
8. Desempenho.

Não priorize estilo enquanto existirem problemas funcionais, de segurança ou de integridade.

## O que deve ser verificado

### Responsabilidade única

Cada classe, método ou componente deve possuir responsabilidade clara.

Avalie código que simultaneamente:

- valida entrada;
- executa regra de negócio;
- acessa banco;
- controla transação;
- monta resposta;
- executa múltiplos efeitos colaterais.

Não divida métodos pequenos apenas para reduzir quantidade de linhas.

Extraia código quando existir responsabilidade ou conceito identificável.

### Tamanho e complexidade

Identifique:

- métodos extensos;
- muitos níveis de `if`;
- fluxos difíceis de acompanhar;
- parâmetros demais;
- classes com responsabilidades não relacionadas;
- serviços genéricos que fazem operações diferentes;
- condicionais complexas repetidas.

Prefira retornos antecipados quando tornarem o fluxo mais claro.

Evite abstrações criadas apenas para esconder poucas linhas simples.

### Nomes

Nomes devem explicar intenção e responsabilidade.

Evite nomes excessivamente genéricos como:

```text
data
item
obj
helper
utils
manager
service
process
handle
doSomething
```

Nomes como `Service`, `Manager` ou `Helper` não são proibidos isoladamente, mas devem representar uma responsabilidade concreta.

Use nomes coerentes com o domínio e com a linguagem do projeto.

### Regras de negócio

As regras de negócio devem respeitar os documentos existentes do projeto.

Verifique se controllers, requests, models ou outras estruturas estão tomando decisões que deveriam pertencer ao fluxo responsável pela regra.

Não replique neste documento as regras específicas do domínio.

Consulte os documentos responsáveis pelo comportamento funcional ao determinar se uma implementação está correta.

### Arquitetura

Respeite a organização definida em `docs/architecture.md`.

Não imponha Repository, Service, Action, DTO, Value Object ou qualquer outra camada apenas por preferência arquitetural.

Ao mesmo tempo, sinalize código que:

- concentre responsabilidades excessivas;
- crie dependências inadequadas;
- duplique regras;
- esconda fluxos importantes;
- contradiga decisões documentadas.

Laravel idiomático deve ser preferido quando resolver adequadamente o problema.

### Tipagem

Use tipagem explícita quando o contrato for conhecido.

Sinalize:

- `mixed` sem necessidade concreta;
- parâmetros sem tipo quando o tipo é conhecido;
- retornos sem tipo quando o contrato é conhecido;
- arrays genéricos atravessando múltiplas partes da aplicação sem contrato claro;
- `array<string, mixed>` utilizado para esconder estruturas conhecidas;
- `stdClass` representando dados com formato conhecido;
- propriedades dinâmicas;
- casts espalhados para corrigir contratos mal definidos;
- métodos com retornos incompatíveis sem justificativa.

`mixed` não é proibido.

É aceitável em pontos realmente dinâmicos ou exigidos pelo framework.

Não use `mixed` apenas para evitar definir um contrato conhecido.

### Erros e exceções

Todo erro relevante deve ser tratado conscientemente.

Identifique:

- exceções ignoradas;
- `catch` vazio;
- erros transformados silenciosamente em sucesso;
- captura genérica de `Throwable` ou `Exception` sem necessidade;
- perda da causa original;
- mensagens técnicas expostas ao cliente;
- tratamento inconsistente do mesmo tipo de erro;
- exceptions utilizadas como fluxo normal sem justificativa.

Não capture exceções apenas para lançá-las novamente sem adicionar comportamento ou contexto útil.

### Banco e transações

Verifique:

- queries sem filtros necessários;
- ausência de constraints importantes;
- problemas de concorrência;
- operações relacionadas fora da mesma transação;
- transações muito extensas;
- alterações parcialmente persistidas;
- N+1;
- consultas repetidas desnecessariamente;
- relacionamentos incorretos;
- mass updates ou deletes sem proteção adequada;
- migrations inconsistentes;
- queries manuais quando Eloquent ou Query Builder resolveriam de forma mais clara.

Transações devem proteger a unidade real de consistência da operação.

Não crie transações sem necessidade.

### Segurança

Procure:

- senhas, tokens ou segredos em logs;
- credenciais hardcoded;
- `.env` versionado;
- SQL construído com concatenação ou interpolação insegura;
- ausência de autenticação;
- ausência de autorização quando necessária;
- confiança em dados enviados pelo cliente;
- dados sensíveis em respostas;
- mass assignment inseguro;
- exposição de stack trace ou detalhes internos;
- validação insuficiente de entrada.

Autenticação não substitui autorização.

### Laravel

Verifique o uso adequado de recursos do framework, quando aplicáveis:

- Eloquent;
- Form Requests;
- Validation Rules;
- migrations;
- seeders;
- middleware;
- autenticação;
- autorização;
- transactions;
- Query Builder;
- route model binding.

Não recrie manualmente funcionalidades que o Laravel já fornece adequadamente.

Não obrigue o uso de um recurso do Laravel quando uma solução mais simples já existente no projeto for suficiente.

### Testes

Siga `docs/tests.md`.

Verifique:

- testes para comportamento novo ou alterado;
- regras relevantes protegidas no nível correto;
- testes de integração quando infraestrutura real fizer parte do comportamento;
- testes HTTP quando o contrato externo for alterado;
- cenários de sucesso e erro relevantes;
- testes existentes não enfraquecidos;
- comportamento concorrente testado quando necessário.

Os testes devem ser:

- claros;
- determinísticos;
- independentes;
- relevantes;
- focados em comportamento;
- sem repetição desnecessária.

Evite testes que:

- existam somente para aumentar cobertura;
- validem getters/setters triviais;
- testem comportamento interno do Laravel;
- repitam a implementação dentro do teste;
- testem métodos privados diretamente;
- dependam apenas da sequência interna de chamadas;
- utilizem mocks sem comportamento relevante;
- dupliquem cenários já cobertos;
- utilizem `sleep()` quando houver alternativa determinística.

Não altere um teste correto apenas para fazê-lo aceitar uma implementação incorreta.

### Código inventado pela IA

Sinalize como smell quando a implementação introduzir sem necessidade:

- abstração sem uso;
- interface sem benefício concreto;
- helper genérico;
- service genérico;
- código morto;
- generalização sem uso real;
- camada criada apenas por costume;
- preparação para funcionalidades futuras;
- biblioteca que a tarefa não exige.

## Code smells PHP

Sinalize quando houver impacto real:

- `mixed` onde um tipo conhecido poderia ser utilizado;
- parâmetros ou retornos sem tipo;
- `array<string, mixed>` usado como contrato permanente;
- arrays profundamente aninhados sem estrutura clara;
- `stdClass` para estruturas conhecidas;
- propriedades dinâmicas;
- uso excessivo de casts;
- comparação frouxa (`==`, `!=`) quando coerção puder alterar comportamento;
- type juggling implícito;
- operador `@` para suprimir erros;
- `catch (\Throwable)` ou `catch (\Exception)` engolindo falhas;
- métodos mágicos próprios sem necessidade;
- estado global ou estático mutável;
- vários parâmetros booleanos difíceis de interpretar;
- função cujo comportamento muda radicalmente conforme parâmetros;
- funções genéricas como `process`, `handle` ou `execute` sem responsabilidade clara.

Recursos dinâmicos do PHP não são automaticamente problemas.

Classifique pelo impacto, não pela existência isolada do recurso.

## Code smells Laravel

Sinalize:

- `$request->all()` enviado diretamente para persistência;
- `create()` ou `fill()` com dados não controlados;
- `$guarded = []` sem justificativa;
- controller concentrando muitas responsabilidades;
- Model acumulando responsabilidades apenas para esvaziar controller;
- queries dentro de loops;
- N+1;
- `Model::all()` em fluxo potencialmente grande sem necessidade;
- `DB::raw()` com entrada externa;
- SQL manual quando Query Builder/Eloquent seria mais seguro e claro;
- validação duplicada sem necessidade;
- mesma query ou regra copiada em vários lugares;
- helpers globais próprios para regra de negócio;
- Facades utilizadas de forma que criem estado oculto ou dificuldade real de teste;
- events, listeners ou observers escondendo um fluxo simples sem benefício;
- lógica relevante escondida em model events;
- acesso indiscriminado a configuração ou environment diretamente no domínio;
- chamadas a `env()` fora dos arquivos de configuração;
- uso excessivo de `optional()` ou null-safe apenas para esconder estados inválidos;
- `first()` usado onde ausência deveria ser tratada explicitamente;
- `firstOrFail()` usado apenas para transformar qualquer ausência em comportamento genérico sem considerar o contrato esperado.

Não trate recursos idiomáticos do Laravel como smells apenas por existirem.

## O que bloqueia merge (❌ Blocker)

Os itens abaixo reprovam a review quando aplicáveis:

| Critério                               | Exemplos em PHP/Laravel                                             |
| -------------------------------------- | ------------------------------------------------------------------- |
| Falha grave de regra de negócio        | comportamento contradiz requisito obrigatório documentado           |
| SQL injection                          | concatenação ou interpolação de entrada externa em SQL              |
| Credencial hardcoded                   | senha, token, API key ou segredo commitado                          |
| `.env` real versionado                 | credenciais e configuração sensível presentes no repositório        |
| Senha armazenada de forma insegura     | senha em texto puro ou mecanismo inadequado                         |
| Rota sensível sem proteção             | ausência de autenticação ou autorização necessária                  |
| Operação destrutiva insegura           | `DELETE`/`UPDATE` sem filtro adequado                               |
| Corrupção ou inconsistência previsível | operação crítica não protegida adequadamente                        |
| Teste removido para esconder regressão | teste correto alterado ou apagado para aceitar comportamento errado |

Blocker deve representar risco real, não preferência de implementação.

## O que gera aviso forte (⚠️ Alta)

A review reprova quando houver problema de alta severidade não corrigido.

Exemplos:

| Critério                                   | Exemplo                                                 |
| ------------------------------------------ | ------------------------------------------------------- |
| Regra importante sem teste                 | comportamento alterado sem proteção adequada            |
| Erro engolido                              | `catch` vazio ou retorno genérico escondendo falha      |
| Query N+1 relevante                        | consulta individual executada repetidamente em listagem |
| Validação obrigatória ausente              | entrada chega ao fluxo sem proteção necessária          |
| Transação inadequada                       | operação pode ficar parcialmente persistida             |
| Concorrência não protegida                 | duas operações simultâneas podem violar invariantes     |
| Duplicação de regra                        | mesma decisão de negócio implementada em vários lugares |
| Complexidade excessiva                     | fluxo difícil de compreender e manter sem necessidade   |
| Contrato alterado sem teste correspondente | mudança externa sem proteção adequada                   |

## Práticas baníveis

Se encontrar qualquer item abaixo, aponte na review e classifique conforme o impacto.

### PHP

- operador `@` escondendo erro relevante;
- `mixed` usado para esconder contrato conhecido em código crítico;
- propriedades dinâmicas como mecanismo normal de domínio;
- estado global mutável;
- exception silenciosamente ignorada;
- `catch` vazio;
- SQL concatenado com entrada externa;
- segredo hardcoded;
- comparação frouxa quando houver risco concreto de coerção incorreta;
- métodos mágicos adicionados apenas para esconder contratos;
- código dependente de efeitos colaterais ocultos.

### Laravel

- `$request->all()` persistido diretamente sem controle;
- mass assignment inseguro;
- `$guarded = []` indiscriminado;
- regra importante somente no frontend;
- rota administrativa sem autenticação;
- autorização ignorada quando necessária;
- `env()` fora de arquivos de configuração;
- query SQL construída inseguramente;
- migration destrutiva sem proteção ou justificativa;
- observer/event escondendo regra crítica sem necessidade;
- helper global genérico usado como depósito de regras;
- código relevante colocado em `AppServiceProvider` sem relação com bootstrap/configuração.

### Testes

- apagar ou enfraquecer teste para esconder regressão;
- teste artificial criado apenas para cobertura;
- testar comportamento interno do framework;
- mockar a própria regra de negócio;
- depender apenas de sequência de mocks;
- teste não determinístico;
- `sleep()` para sincronização quando houver alternativa adequada;
- substituir teste de integração necessário por unitário artificial.

## Práticas ruins adicionais

Sinalize quando encontrar:

- funções ou métodos com várias responsabilidades;
- abstrações prematuras;
- duplicação extensa;
- comentários explicando código confuso em vez de melhorar o código;
- otimização sem evidência;
- mudanças fora do escopo;
- reescrita completa quando uma alteração pequena seria suficiente;
- design pattern usado apenas por costume;
- camada nova sem problema concreto para resolver;
- código preparado para um futuro que a tarefa não pediu.

## Como apresentar a revisão

Classifique cada apontamento como:

```text
❌ Blocker  — não merge
⚠️ Alta     — reprova; corrigir agora
📝 Média    — ressalva; code smell / manutenção
```

Use **O que bloqueia merge**, **O que gera aviso forte** e **Práticas baníveis** como checklist.

Blocker e Alta reprovam a review.

Para cada problema, informe:

- arquivo ou trecho afetado;
- problema encontrado;
- impacto;
- correção recomendada.

Não invente problemas para preencher a revisão.

Não afirme que o código possui uma falha sem conseguir explicar seu impacto.

Não transforme code smell em Blocker sem consequência concreta.

Não exija grande refatoração quando uma correção simples resolver o problema.

Não reprove uma solução apenas porque outra arquitetura seria de sua preferência.

Quando não encontrar problemas relevantes, informe claramente que nenhum problema importante foi identificado e mencione apenas riscos que não puderam ser verificados.
