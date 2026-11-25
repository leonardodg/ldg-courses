# Preparar o runtime do claude-seo (`/seo setup`)

## Contexto

O plugin `claude-seo` (v2.2.4, recém-instalado) foi invocado com `/seo setup`, que deve
executar `claude-seo setup` para criar o ambiente Python isolado + Chromium. A chamada
falha antes de começar, por dois motivos independentes encontrados na verificação:

1. **Fim de linha CRLF no launcher.** `bin/claude-seo` está com terminadores CRLF, então o
   shebang vira `#!/usr/bin/env bash\r` e o kernel responde
   `env: $'bash\r': No such file or directory`. O launcher precisa de fim de linha LF (Linux).
2. **`python3` padrão sem `ensurepip`.** O launcher escolhe o primeiro Python ≥ 3.10 que
   encontra: `/usr/bin/python3` → 3.14.4. Nessa máquina o pacote `python3-venv` do 3.14 não
   está instalado (`ModuleNotFoundError: No module named 'ensurepip'`), e
   `scripts/runtime.py` faz exatamente `sys.executable -m venv` seguido de `python -m pip
   install -r requirements.txt` — ou seja, o setup morreria na criação do venv.
   `/usr/bin/python3.13` já tem `python3.13-venv` instalado e funciona.

Resultado esperado: `claude-seo doctor` reportando runtime e Chromium prontos, com os
comandos `/seo …` utilizáveis.

## Arquivos e caminhos envolvidos

- `~/.claude/plugins/cache/agricidaniel-claude-seo/claude-seo/2.2.4/bin/claude-seo` — launcher a corrigir.
- `~/.claude/settings.json` — já existe (model, marketplaces, mcpServers); ganha um bloco `env`.
- Destino do runtime (calculado por `_data_dir` em `scripts/runtime.py:110`, modo `plugin-fallback`):
  `~/.local/share/claude-seo/` → `.venv/`, `ms-playwright/`, `runtime-state.json`.

## Passos

1. **Normalizar o launcher para LF**
   `dos2unix ~/.claude/plugins/cache/agricidaniel-claude-seo/claude-seo/2.2.4/bin/claude-seo`
   (o `dos2unix` já está em `/usr/bin`). Só esse arquivo importa para o shebang; os `.py` com
   CRLF são lidos normalmente pelo Python e não precisam ser tocados.
   Validar com `file bin/claude-seo` (esperado: sem "CRLF") e `claude-seo doctor --json`.

2. **Fixar o interpretador em 3.13**
   Exportar `CLAUDE_SEO_PYTHON=/usr/bin/python3.13` — o launcher (linhas 16-22) prioriza essa
   variável e valida que é ≥ 3.10 antes de usar.
   Para persistir entre sessões, acrescentar em `~/.claude/settings.json`:
   ```json
   "env": { "CLAUDE_SEO_PYTHON": "/usr/bin/python3.13" }
   ```
   (bloco novo, ao lado de `model`/`mcpServers`; o restante do arquivo fica intacto).
   Alternativa descartada: `sudo apt install python3-venv` para o 3.14 — exige sudo e o 3.14 é
   novo demais para garantir wheels de `lxml`, `weasyprint` e `playwright`; o 3.13 evita
   compilação.

3. **Rodar o setup**
   `claude-seo setup` — cria `.venv` em staging, instala `requirements.txt` (bs4, lxml,
   playwright, trafilatura, matplotlib, weasyprint, google-api-*) e depois
   `playwright install chromium`. Há ~26 GB livres em `/home`; o venv + Chromium ficam em
   torno de 800 MB–1 GB. Não usar `pip install` global: o próprio SKILL proíbe fallback.

4. **Verificar**
   `claude-seo doctor --json` — deve reportar core e Chromium prontos separadamente.
   Se o exit code do setup for `10`, o core subiu mas o Chromium não; nesse caso repetir
   `claude-seo setup` (é idempotente, faz swap atômico e mantém `.venv.previous` como backup).

## Observações

- A correção do passo 1 vive dentro do cache do plugin: **uma atualização do `claude-seo`
  reescreve o diretório `2.2.4/` (ou cria outro) e o CRLF volta**. Se `/seo` voltar a falhar com
  `env: $'bash\r'`, repetir o `dos2unix`. Vale reportar o bug upstream
  (AgriciDaniel/claude-seo) — o repositório precisa de `.gitattributes` com `bin/claude-seo text eol=lf`.
- `weasyprint` (relatórios PDF de `/seo google report`) precisa de pango/cairo do sistema em
  tempo de execução. Não bloqueia o setup; só aparece se/quando gerarmos PDF.
- Nada aqui toca o repositório do Moodle — as mudanças são em `~/.claude` e `~/.local/share`.

## Verificação final

```bash
file ~/.claude/plugins/cache/agricidaniel-claude-seo/claude-seo/2.2.4/bin/claude-seo   # sem CRLF
claude-seo doctor --json                                                               # runtime + chromium ok
claude-seo run fetch_page.py --help                                                    # script empacotado responde
```
