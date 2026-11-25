# Instalar o `moodev` num fork novo do Moodle

Como levar este ambiente de desenvolvimento para **outro projeto** — um fork
novo do Moodle, com outro nome, outras portas e outros plugins.

O resultado é um comando `moodev` funcionando naquele projeto, com worktrees,
devcontainer e ferramental (`phpcs`, `moosh`, `grunt`, Chrome do Behat) prontos
desde o primeiro `moodev new`.

> **O que este documento não é.** Ele não repete o *uso* diário — isso está em
> [`guia-worktrees.md`](guia-worktrees.md) e [`moodev.md`](moodev.md), e no
> `man moodev`. Aqui é só a montagem inicial.

---

## O que o `moodev` assume

Três coisas. Se alguma não valer no projeto de destino, adapte antes:

| Premissa | Onde ela aparece |
|---|---|
| O repositório é um **bare repo com worktrees**, e não um clone comum | todo o `moodev` opera sobre `$MOODEV_ROOT`, a pasta que contém as worktrees |
| Existe uma **worktree de repouso** permanente, chamada `dev` | `MOODEV_BASE_WT`; é para onde o stack volta depois de qualquer `moodev rm` |
| O Moodle serve a partir de **`public/`** | o `APACHE_DOCUMENT_ROOT` do compose. Moodle 4.x não usa `public/` |

A primeira é a que mais assusta e a menos negociável: o comando inteiro existe
para que **vários ambientes rodem ao mesmo tempo**, e isso pressupõe várias
árvores de trabalho do mesmo repositório.

---

## Passo 1 — O bare repo e as worktrees

No destino, a estrutura fica assim:

```
<projeto>/
├── .bare/                      o repositório de verdade (bare)
├── .git                        arquivo, não pasta: "gitdir: ./.bare"
├── .moodev/                    registro de ambientes (o moodev cria)
├── dev/                        worktree de repouso — NUNCA removida
├── main/                       espelho do upstream
└── <feature>/                  as que nascem e morrem com o trabalho
```

```bash
mkdir meu-moodle && cd meu-moodle
git clone --bare https://github.com/<voce>/<seu-fork>.git .bare
echo "gitdir: ./.bare" > .git

# O bare clone não traz refspec de fetch; sem isto "git fetch" não atualiza nada.
git --git-dir=.bare config remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'
git fetch origin

git worktree add dev  dev
git worktree add main main
```

O detalhe do `remote.origin.fetch` é o que costuma morder: sem ele o `fetch`
roda, sai sem erro e não traz nada.

O passo a passo comentado, com o porquê de cada linha, está em
[`estrutura-worktrees.md`](estrutura-worktrees.md).

---

## Passo 2 — Trazer o `.devcontainer/`

É o pacote inteiro, e o `moodev` mora dentro dele:

```
.devcontainer/
├── bin/
│   ├── moodev                  o comando
│   ├── moodle-entrypoint       arranque do container
│   └── moodle-cron
├── build/moodle.Dockerfile     imagem de produção e de desenvolvimento
├── compose/                    base, db, dev, local-tls, local-dev, behat
├── config/
│   ├── config-docker.php       TEMPLATE do config.php — a fonte
│   └── config-local.php.example
├── devtools/                   phpcs, moosh, grunt: manifestos
├── env/                        db.env e dev.env (não versionados)
└── secrets/                    senhas locais (não versionados)
```

```bash
cd dev
git checkout -b chore/moodev
cp -a /caminho/deste/projeto/.devcontainer .
```

### O que TEM que ser trocado

Nada disso é opcional — são os pontos onde o nome deste projeto está gravado:

| Onde | O quê |
|---|---|
| `.devcontainer/bin/moodev` | `MOODEV_BASE_WT` se a worktree de repouso não se chamar `dev` |
| `.devcontainer/bin/moodev` | as portas base do offset 0 (`8080`, `8443`, `3307`, `9004`) |
| `.devcontainer/compose/base.yml` | `STACK_NAME` padrão — hoje `ldg-courses` |
| `.devcontainer/build/moodle.Dockerfile` | a tag da imagem (`leodg/ldg-courses:development`) |
| `.devcontainer/config/config-docker.php` | `$CFG->wwwroot`, banco, e o bloco de domínio por vendedor, que é específico deste projeto |

**As portas importam mais do que parecem.** Dois projetos com a mesma porta base
brigam pelo `8443` no primeiro `moodev up`, e o erro aparece como "port is
already allocated" — sem dizer qual projeto. Escolha uma faixa por projeto:
`8080` aqui, `8180` no próximo.

---

## Passo 3 — Os arquivos que o git não guarda

O `moodev` chama de `IGNORED_FILES` e os copia de worktree em worktree. Num
projeto novo eles não existem ainda:

```bash
cd dev

# Segredos locais
mkdir -p .devcontainer/secrets
printf 'moodle'      > .devcontainer/secrets/db_user
printf '<uma senha>' > .devcontainer/secrets/db_password

# Certificado local. mkcert evita o aviso do navegador em https://localhost.
mkdir -p .devcontainer/certs
mkcert -install
mkcert -cert-file .devcontainer/certs/develop-local.crt \
       -key-file  .devcontainer/certs/develop-local.key localhost 127.0.0.1

# Variáveis de ambiente: comece pelos exemplos versionados
cp .devcontainer/env/db.env.example  .devcontainer/env/db.env
cp .devcontainer/env/dev.env.example .devcontainer/env/dev.env
```

**O `config.php` NÃO entra nesta lista, e isso é deliberado.** Ele é *gerado*
do template a cada worktree nova — ver o passo 5. Copiá-lo foi o desenho
anterior, e o efeito é que mudança no template nunca chegava a máquina nenhuma:
neste projeto o `config.php` local ficou **onze dias** parado, sem o bloco de
domínio por vendedor que o template já tinha.

---

## Passo 4 — Instalar o comando no `PATH`

```bash
mkdir -p ~/.local/bin
ln -sfn <projeto>/dev/.devcontainer/bin/moodev ~/.local/bin/moodev
```

Aponte para a **worktree de repouso**, e não para uma de trabalho: um
`moodev rm` na worktree que alimenta o `PATH` deixaria o link pendurado e o
comando sumiria — inclusive o `moodev doctor`, que diagnosticaria o problema.
O `moodev rm` detecta esse caso e reaponta sozinho, mas a instalação certa evita
o susto.

Com mais de um projeto, dê nomes diferentes (`moodev`, `moodev-outro`) ou use o
caminho completo. Um `moodev` só não serve dois projetos: ele deduz a raiz do
próprio caminho.

---

## Passo 5 — Primeira subida

```bash
cd <projeto>/dev
./.devcontainer/bin/moodev build     # imagem, com phpcs, moosh e grunt dentro
./.devcontainer/bin/moodev config    # gera o config.php do template
./.devcontainer/bin/moodev up        # sobe moodle e db
```

Pelo caminho completo, e não por `moodev` puro: o comando do `PATH` só existe
depois do passo 4, e num projeto recém-montado ainda pode apontar para o outro.

Instale o Moodle pela tela ou pelo CLI, e confira:

```bash
moodev doctor
```

Ele confere ferramentas, disco, validade do certificado, o `.env` contra o
offset registrado, colisão de portas entre stacks e **divergência do `config.php`
em relação ao template**. É a primeira coisa a rodar quando algo estiver
estranho.

---

## Passo 6 — Testes

```bash
# PHPUnit
moodev cli ../admin/tool/phpunit/cli/init.php

# Behat, incluindo os cenários @javascript
moodev up --full                     # sobe o Chrome junto
moodev cli ../admin/tool/behat/cli/init.php
```

Os `$CFG->behat_*` **já vêm no template versionado** — `behat_dataroot`,
`behat_prefix`, `behat_wwwroot` e o perfil `chrome`. Não há passo manual, e isso
também é correção de rumo: eles moravam no `config-local.php`, que é arquivo de
máquina, e o efeito era que todo ambiente novo nascia sem Behat.

O caminho completo de execução está em [`behat.md`](behat.md).

---

## Passo 7 — Adaptar a documentação

Estes documentos citam `ldg-courses`, `local_marketplace` e as portas daqui.
Copie e ajuste:

| Documento | O que ajustar |
|---|---|
| [`guia-worktrees.md`](guia-worktrees.md) | portas, nome do stack, nomes de branch |
| [`moodev.md`](moodev.md) | referência de comando; a estrutura serve como está |
| [`estrutura-worktrees.md`](estrutura-worktrees.md) | URL do remote |
| [`behat.md`](behat.md) | os `--tags` das features do projeto |
| [`../man/moodev.1`](../man/moodev.1) | `.TH`, portas e caminhos |

Instalar o manual:

```bash
sudo install -m 644 docs/man/moodev.1 /usr/local/share/man/man1/moodev.1
sudo mandb -q
man moodev
```

---

## Armadilhas, todas já pagas aqui

**O `composer.json` da raiz é do upstream do Moodle.** Acrescentar dependência
de desenvolvimento ali cria conflito em toda sincronização com o Moodle. As
ferramentas do projeto vão em `.devcontainer/devtools/` — ver o
[README de lá](../../.devcontainer/devtools/README.md).

**O mesmo vale para o `.gitattributes` da raiz.** As regras de fim de linha do
projeto vivem em `.devcontainer/.gitattributes` pelo mesmo motivo.

**O código vem do `--from`, mas o banco vem do offset 0.** Se o offset 0 estiver
numa branch à frente da base, o banco nasce com plugin mais novo que o código e
o `upgrade.php` recusa com `cannotdowngrade`. O `moodev new` confere e para
antes de criar qualquer coisa.

**O Chrome do Behat não sobe junto com o stack.** São centenas de megabytes por
stack para um serviço que a maioria das rodadas não usa. `moodev up --full`
quando precisar.

**Não edite `.env` nem portas à mão.** O `moodev` gera esses arquivos, e o
`moodev doctor` reclama quando divergem do registro.
