# `cf` — um comando para o fluxo de worktrees

**Data:** 2026-08-26 · **Agente:** Claude Code (Opus 5)
**Resultado:** entregue — PRs [#50](https://github.com/leonardodg/courses-free/pull/50),
[#51](https://github.com/leonardodg/courses-free/pull/51),
[#52](https://github.com/leonardodg/courses-free/pull/52), todos merjeados em `dev`

---

## Contexto

Abrir uma feature exigia oito passos manuais: `git worktree add`, copiar cinco
conjuntos de arquivos gitignored, editar o `.env`, subir o compose — e mesmo
assim **só um ambiente rodava por vez**, porque `base.yml` fixava
`name: courses-free` e o `.env` fixava as portas.

O pedido original: *"criar uma nova worktree, abrir no VS Code e reconfigurar
todo o ambiente novamente, sem contar que o VS Code trabalha com devcontainer e
vai querer fazer rebuild, porém já tem um docker rodando"*.

## Decisões

| Decisão | Alternativa recusada | Por quê |
|---|---|---|
| **Modelo híbrido**, reaproveitar o stack por padrão | stack próprio por worktree | O caro é o stack, não a worktree. Na maior parte do tempo se trabalha numa feature de cada vez. `--new-stack` cobre o resto. |
| **Isolamento por offset** | nomes livres de stack | O offset gera nome e portas por soma, e o 0 preserva o ambiente que já existia sem nenhuma ação. |
| **`dev` como worktree de repouso** | `main` | `main` não tem `.devcontainer/` e está 108 commits atrás — o stack não sobe nela. O fluxo é `feature → dev → deploy`, sem PR `dev`→`main`. |
| **Symlink em cadeia** para o `cf` | link direto para uma worktree | Ideia do usuário. Worktree é descartável; com a indireção, trocar a origem é mexer em um symlink só. |
| **Multi-arch com dois runners nativos** | `platforms: amd64,arm64` num job | Um job só poria o amd64 sob QEMU — dezenas de minutos num build de Moodle. |
| **Uma tag no Docker Hub** | tag por SHA de commit | 29 tags acumuladas, 16,5 GB, nada consumindo. O digest já é o identificador imutável. |

O default inicial era o oposto do modelo híbrido — subia container novo por
feature. **Foi invertido depois que o usuário testou** e apontou que contradizia
a escolha.

## O que mudou

**Novos**

- `.devcontainer/bin/cf` — o script
- `.devcontainer/compose/local-dev.yml` — extras locais que não podem ir para a VPS
- `.devcontainer/.gitattributes` — força `eol=lf`
- `docs/dev/guia-worktrees.md`

**Alterados**

- `base.yml` — `name: ${STACK_NAME:-courses-free}`, `composer_cache` compartilhado
- `devcontainer.json` — reescrito: sem `features`, sem `build-dev.yml`, com `local-tls.yml`
- `moodle.Dockerfile` — shell-history assado na imagem
- `moodle-entrypoint` — guards de permissão corrigidos
- `.github/workflows/deploy.yml` — matriz multi-arch e job de manifesto

## Descobertas

Nenhuma foi pedida; todas bloqueavam o trabalho.

**O `.env` era invisível para o VS Code.** A extensão Dev Containers monta o
próprio `docker compose -f …` **sem `--env-file`**, e `COMPOSE_ENV_FILE` não a
alcança — o compose procurava o `.env` em `.devcontainer/compose/`. Erro:
`required variable DB_HOST_DATA is missing a value`. **É a razão de abrir o
projeto pelo VS Code nunca ter funcionado direito.** Resolvido com um symlink
relativo.

**Os guards de permissão do entrypoint olhavam só o diretório raiz** do
`moodledata`. Com a raiz correta e um subdiretório `root:root`, o `chown -R`
nunca rodava, e o Moodle respondia 500 com `invaliddatarootpermissions`. O site
**já estava fora do ar quando a sessão começou**.

**`core.autocrlf=true`** no git global escrevia CRLF no working tree. Um build
local geraria `#!/bin/bash\r` no ENTRYPOINT e o container não subiria. Ficava
escondido porque a imagem vinha do CI, em LF.

**A imagem publicada era `linux/arm64` apenas** — a VPS Oracle é Ampere. Em
amd64 o `pull` nunca funcionou, o que mantinha todo ambiente local dependente de
um build local, escondendo o problema do CRLF.

**`cf rm` podia apagar o banco principal.** A linha de registro de uma worktree
apenas *servida* pelo stack principal aponta para o `moodledata` e o `dbdata`
**dele**. Comando que parece local, estrago global.

## Verificação

Oito cenários de CRUD, todos passando.

| # | Ação | Resultado medido |
|---|---|---|
| A1 | `cf new t-a` | principal serve `t-a`; **4 containers**, nenhum novo |
| A2 | `cf new t-b` | principal serve `t-b`; `t-a` vira `-` |
| A3 | `cf use t-a` | volta para `t-a`; ainda 4 containers |
| A4 | `cf rm t-b` (não servida) | remove; principal segue em `t-a`, banco intacto |
| A5 | `cf rm t-a` (servida) | move para `dev`, remove; site em 303 |
| B1 | `cf new t-c --new-stack` | offset 2, portas 8100/8463/3327/9024 |
| B2 | três sites juntos | 8443, 8453, 8463 — todos HTTP 303 |
| B3 | `cf rm t-c` | derruba só o stack dela; principal intocado |

Bancos comprovadamente independentes: marca gravada em um, ausente no outro.

**Abrir uma worktree existente: ~1 s**, container reaproveitado. O que sobra de
build é uma camada de UID de ~726 MB, criada **uma vez por worktree** pelo
`updateRemoteUserUID`.

Pipeline completo verde: multi-arch publicado, `courses.leodg.dev` em HTTP 303.

A própria validação encontrou 5 alertas falsos no `cf doctor` — o offset `-`
tinha sido ensinado ao `cf ls` e ao `cf rm`, e esquecido no `doctor`.

## Em aberto

- **`main` continua sem `.devcontainer/`**, 108 commits atrás. Não serve como
  base nem como repouso.
- **Um ambiente por vez no modo padrão** — banco compartilhado; alternar entre
  branches com versões diferentes de plugin roda `upgrade.php` nos dois sentidos.
- **`docs/` reorganizada em sessão posterior** — este plano é anterior à
  estrutura atual de pastas.
