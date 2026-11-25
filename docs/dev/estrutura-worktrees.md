# Bare repo com worktrees: como montar

Como este repositório está organizado, e como reconstruí-lo do zero.

> Documentado a partir da instalação real em
> `/home/leodg/localhost/gitworktree-bare-moodle`, não de um tutorial genérico —
> os comandos abaixo reproduzem exatamente o que existe hoje.

---

## 1. Por que bare + worktrees

Num clone comum há **um** working tree: trocar de branch reescreve os arquivos
no lugar. Com Moodle isso significa 63 mil arquivos mudando de estado, o
container reiniciando e o `vendor/` possivelmente inconsistente.

Com worktrees, cada branch tem a **própria pasta**, todas compartilhando um só
banco de objetos. Trocar de contexto vira `cd`, e nada é reescrito.

O que isso destrava neste projeto:

- **Vários ambientes ao mesmo tempo** — o [`moodev`](moodev.md) sobe um stack por
  worktree quando você pede `--new-stack`
- **Comparar branches lado a lado**, sem `git stash`
- **Manter `dev` sempre disponível** — é a worktree de repouso do `moodev` e a que
  alimenta o comando no `PATH`

O custo: ~600 MB de arquivos por worktree. Os objetos do git **não** são
duplicados.

---

## 2. O layout

```
gitworktree-bare-moodle/
├── .bare/                      o repositório de verdade (bare)
├── .git                        arquivo de uma linha: gitdir: ./.bare
├── .moodev/                        registro de ambientes (criado pelo moodev)
├── .devcontainer/bin/moodev        symlink -> ../../dev/.devcontainer/bin/moodev
├── docs -> dev/docs/           atalho de leitura
│
├── dev/                        branch de integração — worktree de repouso
├── main/                       produção (hoje sem .devcontainer/)
├── MOODLE_502_STABLE/          espelho do upstream, para sincronizar
└── <sua-feature>/              nascem e morrem com as features
```

**A raiz não é um working tree.** Ela guarda o `.bare/`, o estado do `moodev` e as
worktrees. Não commite arquivos ali — foi de onde saíram `README.md`, o SVG e a
conversa inicial, hoje em [`../architecture/`](../architecture/) e
[`../history/`](../history/).

### O truque do `.git`

O arquivo `.git` na raiz contém uma linha:

```
gitdir: ./.bare
```

Com isso, comandos git rodados na raiz encontram o repositório sem que a raiz
seja um checkout. É o que permite `git worktree list` e `git worktree add`
funcionarem de dentro dela.

---

## 3. Montar do zero

```bash
mkdir -p ~/localhost/gitworktree-bare-moodle
cd ~/localhost/gitworktree-bare-moodle

# 1. o repositorio, sem working tree
git clone --bare git@github.com:leonardodg/ldg-courses.git .bare
echo "gitdir: ./.bare" > .git

# 2. o clone --bare so traz refs/heads; sem isto, nenhum remote-tracking
git config remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'
git fetch origin

# 3. o upstream do Moodle, de onde vem a base
git remote add upstream https://github.com/moodle/moodle.git
git fetch upstream

# 4. as worktrees permanentes
git worktree add dev               dev
git worktree add main              main
git worktree add MOODLE_502_STABLE upstream/MOODLE_502_STABLE
```

> **O passo 2 é o que quase todo tutorial esquece.** `git clone --bare` grava um
> `fetch` que não popula `refs/remotes/`, então `origin/dev` não existe e
> `git worktree add <nome> <branch>` falha sem explicar por quê.

Depois, a instalação do `moodev` — ver [`moodev.md`](moodev.md#9-instalação).

---

## 4. Operações

### Criar

```bash
moodev new minha-feature          # worktree + ambiente + VS Code
```

O caminho manual, para saber o que o `moodev` faz:

```bash
cd ~/localhost/gitworktree-bare-moodle
git worktree add minha-feature -b feature/minha-feature origin/dev
```

Só isso não dá um ambiente: faltam os arquivos que o `.gitignore` esconde. É
para isso que o `moodev` existe — ver [`moodev.md`](moodev.md#4-cf-new).

### Listar

```bash
git worktree list     # o que o git conhece
moodev ls                 # o que tem ambiente, com portas e status
```

As duas listas respondem perguntas diferentes. Uma worktree pode existir para o
git e não estar registrada no `moodev`.

### Remover

```bash
moodev rm minha-feature   # worktree, branch, e o stack/dados se forem dela
```

Manual:

```bash
git worktree remove minha-feature
git branch -d feature/minha-feature
```

> `git worktree remove` recusa se houver alteração não commitada — o que é bom.
> `--force` ignora, e aí não há volta.

### Depois de apagar uma pasta na mão

```bash
git worktree prune    # limpa os registros orfaos em .bare/worktrees/
```

---

## 5. Sincronizar com o Moodle upstream

O projeto acompanha o Moodle em vez de forkar. Antes de começar uma feature:

```bash
cd ~/localhost/gitworktree-bare-moodle/MOODLE_502_STABLE
git pull

cd ../dev
git merge upstream/MOODLE_502_STABLE
git push origin dev
```

Conferir se há o que trazer:

```bash
git -C dev rev-list --count origin/dev..upstream/MOODLE_502_STABLE
```

> **Não edite o `.gitattributes` da raiz do repositório** — é do Moodle upstream
> e conflita a cada sincronização. As regras deste projeto vivem em
> `.devcontainer/.gitattributes` e `docs/.gitattributes`.

---

## 6. Armadilhas

**Uma branch só pode estar em uma worktree.** `git worktree add` recusa uma
branch já checada em outro lugar. É proteção, não limitação: duas pastas
editando a mesma branch divergiriam em silêncio.

**A pasta e a branch são coisas diferentes.** A worktree
`docs-organize-and-improve` tem a branch `docs/organize-and-improve` — o `moodev`
faz essa tradução (barra vira hífen). Ver [`moodev.md`](moodev.md#nome-da-pasta--nome-da-branch).

**`git config` na raiz é global para todas as worktrees.** A configuração vive
em `.bare/config`, compartilhada. Só `core.worktree` e alguns poucos são por
worktree.

**Commits ficam órfãos se a worktree for removida antes do push.** O `moodev rm`
avisa; o `git worktree remove` não avisa sobre commits, só sobre arquivos
modificados.

**`git status` numa worktree só vê a própria branch** — mas `git log --branches`
vê todas. Foi assim que o `moodev rm` chegou a acusar commits pendentes que eram de
outra branch.

---

## Ver também

- [`moodev.md`](moodev.md) — o comando que transforma worktree em ambiente
- [`guia-worktrees.md`](guia-worktrees.md) — portas, `.env`, VS Code, a VPS
- [`fluxo-de-contribuicao.md`](fluxo-de-contribuicao.md) — da worktree ao deploy
- [`../architecture/estrutura-do-repositorio.md`](../architecture/estrutura-do-repositorio.md) — o fluxo de branches
- `git help worktree` — a referência oficial
