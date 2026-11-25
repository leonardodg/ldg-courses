> **Situação:** executado · **Início:** 2026-09-03
> **Origem:** `~/.claude/plans/optimized-napping-pike.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** commits `ca1a22db404` e `9f7eccf46ab` na `dev`, mais o *fast-forward* que destravou a worktree `fix-theme-ldg`. Sessão `session_01Av7hXEHNDCxd9FQKuUod4x`.

# Destravar o stack fix-theme-ldg e fechar a armadilha no `cf new`

## Contexto

O `cf new fix/theme-ldg --new-stack` subiu o ambiente, mas o `upgrade.php` do final
morreu em `Cannot downgrade format_ldg from 2026090304 to 2026090301`.

O motivo é que o `cf new` resolve **duas origens diferentes** e ninguém garante que
combinem:

| | vem de | estado |
|---|---|---|
| **código** | `--from`, default `origin/dev` = `3c5c09ccf37` | format_ldg `2026090301`, theme_ldg `2026090215` |
| **banco** (`--seed clone`) | dump do stack offset 0 (`format-course-ldg`, servindo `fix/format-ldg-navegador` = `89412c48373`) | format_ldg `2026090304`, theme_ldg `2026090221` |

Confirmado no banco do stack novo:

```
format_ldg   2026090304      (código na worktree: 2026090301)
theme_ldg    2026090221      (código na worktree: 2026090215)
```

São dois downgrades, não um — o upgrade só aborta no primeiro. O commit que falta é
`89412c48373` *"format_ldg - quatro bugs que so o navegador mostrou"*, único commit
entre `fix/theme-ldg` e `fix/format-ldg-navegador`, e ele mexe justamente em
`theme/ldg/scss/` além do format.

O `cf` já documenta esse risco em `cmd_use` ("trocar entre branches com versoes
diferentes de plugin pede upgrade.php de ida e de volta"), mas o `cmd_new` não checa
nada — e falha tarde, com worktree criada, dump restaurado e stack no ar.

Objetivo: (1) deixar `fix-theme-ldg` utilizável sem perder o curso de teste;
(2) fazer o `cf new` falhar **antes** de criar qualquer coisa quando a base ficaria
atrás do banco.

---

## Parte 1 — Destravar a worktree `fix-theme-ldg`

`fix/theme-ldg` não tem commit próprio (está exatamente em `origin/dev`), não tem
branch remota e a árvore está limpa: é um fast-forward, nada se perde.

1. Conferir antes de mexer, em `/home/leodg/localhost/gitworktree-bare-moodle/fix-theme-ldg`:
   - `git status --porcelain` vazio;
   - `git rev-list --count origin/dev..HEAD` = 0.
2. `git -C fix-theme-ldg merge --ff-only fix/format-ldg-navegador`
   → `fix/theme-ldg` passa de `3c5c09ccf37` para `89412c48373`.
3. De dentro de `fix-theme-ldg/`: `cf cli upgrade.php --non-interactive`
   (agora as versões batem; deve passar sem downgrade) e `cf cli purge_caches.php`.

Consequência a registrar: `fix/theme-ldg` passa a conter trabalho de
`fix/format-ldg-navegador`, que ainda não está na `dev` (`origin/dev` está no merge do
PR #65). O PR da theme deve sair **depois** que a navegador entrar, ou apontar para ela.
A branch continua com upstream `origin/dev`, então o `git status` vai dizer "ahead by 1" —
é esperado.

## Parte 2 — Guarda no `cf new`

Arquivo: `.devcontainer/bin/cf` (o que responde no PATH é o da worktree `dev`, via
`~/.local/bin/cf -> .devcontainer/bin/cf -> ../../dev/.devcontainer/bin/cf`).

### 2a. Helper novo, ao lado de `seed_clone`

`plugins_a_frente <base_ref> <seed_ref>` — lista os plugins cuja versão no `seed_ref`
é **maior** que no `base_ref`:

- `git diff --name-only "$base_ref" "$seed_ref" -- '*/version.php'` para pegar só os
  arquivos que mudaram (poucos; é barato);
- de cada lado, `git show <ref>:<arquivo>` e extrai `$plugin->version` e
  `$plugin->component` com `sed`;
- imprime uma linha por plugin em que `seed > base`; silêncio quando está tudo bem.

Comentário no estilo do arquivo explicando *por que* existe (as duas origens da tabela
acima), não o que o código faz.

### 2b. Chamada em `cmd_new`

Logo depois da checagem de `.devcontainer/` no `$from` (por volta da linha 676, no
bloco "a base tem ambiente?") e **antes** do `git worktree add` — o script já segue a
regra "falha AQUI, antes de criar qualquer coisa".

- Vale para os dois caminhos: sem `--new-stack` o banco é literalmente o do principal
  (o `cmd_use` no fim roda upgrade contra ele), e com `--new-stack --seed clone` é uma
  cópia. Só `--new-stack --seed fresh` escapa; `share` é o caso mais exposto de todos.
- `seed_ref` = `git -C "$src_path" rev-parse HEAD` (worktree offset 0, a mesma que o
  `seed_clone` usa como origem).
- Se `plugins_a_frente "$from" "$seed_ref"` devolver algo → `die` listando os plugins e
  as duas saídas:
  `cf new <nome> --from <branch da origem>` (o caso comum) ou, com `--new-stack`,
  `--seed fresh` para nascer com banco vazio.
- Se `git -C "$src_path" diff --quiet -- '*/version.php'` falhar, `warn`: a comparação
  olha o HEAD da origem e não enxerga bump de versão ainda não commitado.

O helper fica genérico de propósito — o `cmd_use` sofre do mesmo problema ao alternar
para uma branch mais antiga que o banco compartilhado, e pode reusá-lo depois.

### 2c. Ajuda

Uma linha em `usage()`, no bloco do `cf new`, dizendo que a base precisa conter a
branch servida pelo stack de origem, senão o `cf` recusa e sugere o `--from`.

### Onde commitar

O `cf` que roda é o da worktree `dev`, então a mudança precisa chegar lá para valer.
A `dev` local está **0 à frente / 45 atrás** de `origin/dev` (o conteúdo do `cf` é
idêntico nas worktrees, sem risco de conflito neste arquivo):

1. `git -C dev merge --ff-only origin/dev`;
2. editar e commitar em `dev`, no estilo do histórico do arquivo
   (`fix(cf): ...`, como `842a748544c` e `c515fdb96d9`);
3. **sem push** — fica para você mandar.

---

## Verificação

**Parte 1** (o que prova que acabou):

```bash
# versões do código
grep -h 'plugin->version' fix-theme-ldg/public/course/format/ldg/version.php \
                          fix-theme-ldg/public/theme/ldg/version.php
# versões do banco
docker exec -i courses-free-fix-theme-ldg-db-1 mariadb -uroot -p"$(cat fix-theme-ldg/.devcontainer/secrets/db_root_password)" \
  moodle -e "select plugin,value from mdl_config_plugins where plugin in ('format_ldg','theme_ldg') and name='version';"
```

Os dois lados têm que dar `2026090304` e `2026090221`, e o `cf cli upgrade.php` tem
que terminar sem `cannotdowngrade`. Depois, o de sempre: abrir
`https://localhost:8483`, entrar num curso em formato LDG e conferir a aula embutida —
é o que só o navegador mostra.

**Parte 2** (dá para testar sem gastar 1 GB, porque o guarda morre antes de criar nada):

```bash
cf new teste/guarda --from origin/dev      # tem que MORRER listando format_ldg e theme_ldg
git worktree list                          # inalterada
git branch --list 'teste/*'                # vazia
```

Controle negativo, com uma base que contém o HEAD da origem:

```bash
cf new teste/guarda --from fix/format-ldg-navegador --no-code
```

não pode morrer no guarda (interromper antes de deixar criar, ou rodar e depois
`cf rm teste-guarda --force`).
