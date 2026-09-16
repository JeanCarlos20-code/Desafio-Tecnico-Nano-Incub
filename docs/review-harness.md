# Review do Harness Python

Revise `harness/src/` procurando problemas reais de correção, segurança, arquitetura, acoplamento, neutralidade e testes.

Não sugira mudanças apenas por preferência pessoal.

O objetivo é manter o core:

```text
correto
simples
tipado
determinístico
testável
seguro
reutilizável entre stacks
```

---

## ❌ O que bloqueia o merge

Qualquer item abaixo reprova a review e deve ser corrigido.

### Testes

- lógica nova sem teste unitário;
- nova `policy` sem teste de aceitação e rejeição;
- nova transição da state machine sem testar caminho permitido e proibido;
- teste existente removido, enfraquecido ou alterado apenas para fazer implementação incorreta passar;
- teste obrigatório não executado sendo tratado como sucesso.

### Tipagem

Bloqueie:

- `Any` propagado para o domínio interno do harness;
- `Any` usado para evitar tipagem de um contrato conhecido;
- `dict[str, Any]`, `list[Any]` ou estruturas equivalentes atravessando várias camadas sem validação;
- `typing.cast()` usado apenas para esconder contrato mal tipado;
- valor externo usado sem validação adequada;
- estruturas conhecidas representadas por dicionários genéricos quando deveriam possuir um contrato claro.

`Any` é permitido em fronteiras inevitavelmente dinâmicas, por exemplo:

```text
YAML
JSON
resposta MCP
biblioteca externa sem tipagem adequada
```

desde que o valor seja validado e convertido o mais cedo possível para tipos internos conhecidos.

Não faça mudanças mecânicas como:

```python
list[Any]
```

para:

```python
list[object]
```

apenas para eliminar `Any`.

A troca só é útil quando `object` força uma validação/narrowing real antes do valor ser utilizado.

Exemplo correto:

```python
def parse_database(value: object) -> DatabaseProfile:
    if not isinstance(value, dict):
        raise ValueError("database must be an object")

    ...
```

Use conforme necessário:

```python
isinstance()
TypeGuard
dataclass
TypedDict
Protocol
modelo/parser específico
```

O objetivo é **tipagem real**, não apenas satisfazer o reviewer.

### Segurança

- SQL construído por concatenação, `.format()`, `%` ou f-string com valor externo;
- credencial, senha, token ou connection string sensível hardcoded;
- `shell=True` sem necessidade concreta;
- possibilidade de command injection;
- path traversal ou escrita fora da raiz/allowlist permitida;
- operação de escrita por MCP configurado como read-only;
- `DELETE` ou `UPDATE` sem filtro adequado ou com risco evidente de perda de dados;
- segredo sendo escrito em logs ou arquivos gerados.

### Gates e state machine

- possibilidade de pular aprovação humana;
- execução iniciada antes da aprovação exigida;
- transição impossível aceita;
- review reprovada chegando a `DONE`;
- artifact obrigatório ignorado;
- estado aprovado sendo sobrescrito silenciosamente.

### Neutralidade

`harness/src/` deve continuar reutilizável em stacks diferentes.

Bloqueie hardcodes como:

```python
if language == "go":
    ...

if framework == "flutter":
    ...
```

quando a decisão deveria estar em:

```text
stack.yaml
adapter
skill
prompt
configuração
```

Exceção: componentes cuja responsabilidade explícita seja detectar ou integrar tecnologias específicas.

### RAG

- ausência de isolamento entre projetos por `project_id`;
- mistura do banco RAG com o schema da aplicação;
- resultado de outro projeto podendo aparecer na busca atual;
- falha de RAG sendo silenciosamente apresentada como pesquisa executada.

---

## ⚠️ O que gera aviso forte

Fortemente recomendado corrigir antes do merge:

- código duplicado representando a mesma regra;
- função com aproximadamente 50+ linhas fazendo responsabilidades diferentes;
- `except` vazio;
- erro engolido silenciosamente;
- `except Exception` amplo sem justificativa;
- query com padrão N+1;
- quebra de contrato público sem tratamento adequado;
- dependência circular;
- configuração duplicada ou espalhada;
- módulo assumindo responsabilidade de outro;
- código específico de stack entrando no core;
- abstração criada sem necessidade concreta;
- recurso aberto sem encerramento adequado;
- subprocess executado sem verificar `returncode`;
- nova funcionalidade importante sem teste suficiente.

Mais importante que quantidade de linhas é responsabilidade.

Uma função com 60 linhas fazendo uma única tarefa clara pode estar correta. Uma função com 30 linhas validando, executando subprocess, alterando estado e persistindo arquivos provavelmente está fazendo demais.

---

## Arquitetura

Preserve responsabilidades claras:

```text
orchestrator
→ fluxo e state machine

policies
→ invariantes determinísticas

verifier
→ execução e verificação

rag
→ indexação e retrieval

profile/config
→ configuração

adapters
→ particularidades externas

CLI
→ entrada fina para as capacidades
```

Sinalize acoplamentos como:

```text
policy fazendo I/O
orchestrator executando trabalho do verifier
RAG decidindo fluxo do Planner
CLI contendo regra complexa
adapter contendo regra central do harness
```

---

## Práticas Python

Prefira:

- type hints úteis;
- `dataclass`, `TypedDict`, `Protocol` ou modelos nomeados quando houver contrato real;
- `pathlib` para paths;
- context managers para recursos;
- exceções específicas;
- early return quando reduzir complexidade;
- funções separadas por responsabilidade, não apenas por quantidade de linhas.

Evite:

```text
utils.py como depósito genérico
estado global mutável
side effects durante import
imports circulares
BaseService genérico
factory sem necessidade real
plugin system "para o futuro"
código morto
abstração especulativa
```

Pergunta útil:

> Se remover esta abstração, algum caso concreto atualmente suportado deixa de funcionar?

Se não, questione sua necessidade.

---

## Testes

Teste comportamento observável.

Exemplos:

```text
policy nova
→ válido + inválido

state transition nova
→ permitida + proibida

config/parser novo
→ entrada válida + inválida

RAG PostgreSQL/pgvector
→ integração real quando necessária

subprocess
→ sucesso + exit code de erro
```

Falha de infraestrutura deve continuar distinta de falha do código:

```text
NOT_EXECUTED != PASSED
```

Não criar testes apenas para aumentar cobertura.

---

## Severidade

```text
❌ Blocker
→ reprova o merge

⚠️ Alta
→ reprova a review e deve ser corrigido

📝 Média
→ ressalva de manutenção/legibilidade
```

Para cada finding informe:

- arquivo ou trecho;
- problema;
- impacto;
- correção recomendada.

Não invente problemas para preencher a revisão.

Não peça grande refatoração quando uma correção localizada resolver.

Código diferente da preferência do reviewer não significa código errado.

---

## Resultado

```text
Blocker presente
→ REPROVADO

Alta presente
→ REPROVADO

somente Média
→ APROVADO COM RESSALVAS

nenhum problema relevante
→ APROVADO
```
