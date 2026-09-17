# Sync

`harness sync` é independente do LangGraph de execução. O wrapper `./harness/run sync` continua válido.

Fonte canônica:

- `harness/agents/*.md`
- `harness/skills/*/`
- `harness/instructions.md`
- `harness/mcp/servers.yaml`
- `harness/targets/*.yaml`

Tokens MCP vêm de `.env.mcp` na raiz do repositório (não versionado; copie `.env.mcp.example`). O sync interpola esses valores nos JSON/TOML gerados. Sem o arquivo, os targets com `env_syntax` continuam com placeholder (`${env:VAR}`) e os servers opcionais (postgres, snyk) só entram se a variável já existir no ambiente do processo.

Ele gera os arquivos nativos definidos pelos targets, copia skills e materializa MCP. `.harness-manifest.json` registra fingerprint/hashes para `check-sync`.

O sync não cria task, worktree ou checkpoint e não altera o estado de uma execução.
