# `moodev` — ambientes de desenvolvimento por worktree

`moodev` é **Moodle Dev**: worktrees, devcontainer e ferramentas de
desenvolvimento num comando só. Ele cria, serve e remove os ambientes Docker de
cada worktree do repositório, gera o `config.php` a partir do template e instala
o ferramental (`phpcs`, `moosh`, `grunt`, Chrome do Behat) na imagem.

> **Chamava-se `cf`**, de *courses-free*, quando só cuidava de worktrees deste
> projeto. O nome ficou pequeno e amarrado a um projeto só — ver
> [instalar num fork novo](moodev-em-projeto-novo.md). `cf` continua funcionando
> como atalho para não quebrar memória muscular.

- **Onde mora:** `.devcontainer/bin/moodev`, versionado — cada worktree tem a sua cópia
- **Como responde no PATH:** `~/.local/bin/moodev` → `<raiz>/.devcontainer/bin/moodev` → `dev/.devcontainer/bin/moodev`
- **Ajuda rápida:** `moodev --help` · **Manual:** `man moodev` (ver [instalação](#manual-do-sistema))

> Este documento cobre o `moodev` em si. Para o fluxo de trabalho com worktrees —
> portas, VS Code, a VPS, armadilhas — veja
> [`guia-worktrees.md`](guia-worktrees.md).

---

## 1. O problema

Abrir uma feature exigia oito passos manuais. `git worktree add` entrega só o
código versionado; tudo que faz o ambiente subir está no `.gitignore`:

| Arquivo | Por que é ignorado |
|---|---|
| `/.env` | caminhos absolutos da máquina |
| `/config.php` | gerado a partir de `config-docker.php` |
| `.devcontainer/secrets/` | senhas do banco |
| `.devcontainer/certs/develop-local.{crt,key}` | par do mkcert |
| `.devcontainer/env/{db,dev}.env` | credenciais e URL do site |

Pior: o ambiente era **único por construção**. `base.yml` fixava um
`name:` só e o `.env` fixava as portas, então subir o compose de outra
worktree não criava um segundo ambiente — **recriava o mesmo container**
apontando para outro código.

```bash
moodev new paygw-pix
```

---

## 2. O modelo mental

Duas coisas independentes, e confundi-las é a origem de quase toda dúvida:

**Worktree** é uma pasta com um checkout do repositório. É barata: ~600 MB de
arquivos, criada em segundos.

**Stack** é o par de containers (Moodle + MariaDB) que serve *uma* worktree. É
cara: ~900 MB com o banco, portas próprias, um minuto para subir.

**Várias worktrees podem existir para um stack só.** O container monta o código
de uma delas por vez; trocar é remontar, não recriar. Daí os dois modos:

| | `moodev new <nome>` (padrão) | `moodev new <nome> --new-stack` |
|---|---|---|
| Container | o que já roda | novo |
| Banco | **o mesmo** | próprio |
| Portas | 8080/8443/3307/9004 | offset próprio |
| Custo | ~0 | ~900 MB, ~1 min |
| Em paralelo? | não, um de cada vez | **sim** |

Use `--new-stack` quando precisar das **duas features no ar ao mesmo tempo** ou
de **bancos separados**. Fora isso, o padrão.

### Offset

Cada worktree registrada tem um **offset**, que define seu estado e suas portas:

| Offset | Significa | Portas |
|---|---|---|
| `0` | é quem o stack principal **serve agora** | 8080 / 8443 / 3307 / 9004 |
| `≥ 1` | tem **stack próprio**, rodando em paralelo | base + N×10 |
| `-` | existe no disco, **sem ambiente servindo** | — |

Offset `-` não é erro: é só uma worktree que não é a atual. `moodev use` a coloca no
ar.

O offset 0 é o ambiente principal. `base.yml` usa
`name: ${STACK_NAME:-ldg-courses}`, então quem não define `STACK_NAME` cai nesse
default — é assim que a VPS sobe sem precisar deste script.

Como o default é quem nomeia o stack de todo mundo que não define `STACK_NAME`,
**trocá-lo renomeia containers, rede e volumes da VPS junto**. Foi o que
aconteceu no rename de `courses-free` para `ldg-courses`: o stack antigo teve que
ser derrubado à mão antes, senão o compose enxerga projeto novo e esbarra nas
portas que o velho ainda segura.

---

## 3. Comandos

Notação: `<obrigatório>`, `[opcional]`. Onde aparece `[worktree]` é o **nome da
pasta** — a coluna `WORKTREE` do `moodev ls`.

**Omitindo o nome, o comando age sobre a worktree do diretório atual.** Dentro de
`paygw-pix/`, `moodev up` sobe aquele ambiente; de fora, `moodev up paygw-pix`.

### Ciclo de vida

| Comando | O que faz |
|---|---|
| `moodev new <nome> [opções]` | cria worktree + branch e coloca no ar |
| `moodev use <worktree>` | o stack atual passa a servir outra worktree |
| `moodev rm <worktree> [--force]` | remove worktree, branch e (se houver) stack e dados |

### Operação

| Comando | O que faz |
|---|---|
| `moodev ls` | worktrees, offsets, stacks, status e URLs |
| `moodev up [worktree]` | sobe o stack |
| `moodev down [worktree]` | para o stack |
| `moodev restart [worktree]` | `down` e `up` — é o que adota imagem nova |
| `moodev logs [worktree]` | segue os logs |
| `moodev doctor` | confere instalação, disco, certificado, `.env` e portas |

### Trabalho

| Comando | O que faz |
|---|---|
| `moodev code [worktree]` | abre o VS Code **dentro** do container |
| `moodev shell [worktree]` | `bash` no container, como `1000:33` |
| `moodev cli <script.php> [args]` | roda um `admin/cli` do Moodle |
| `moodev build [worktree]` | reconstrói a imagem (único que carrega `build-dev.yml`) |

---

## 4. `moodev new`

```
moodev new <nome|prefixo/nome> [--branch <b>] [--from <b>] [--new-stack]
                           [--seed clone|fresh|share] [--no-code]
```

| Opção | Padrão | O que faz |
|---|---|---|
| `--branch <b>` | `feature/<nome>` | nome da branch, quando difere do da pasta |
| `--from <b>` | `origin/dev` | de onde a branch nova sai — precisa conter a branch do offset 0 |
| `--new-stack` | desligado | ambiente próprio em vez de reaproveitar o atual |
| `--seed <modo>` | `clone` | de onde vêm os dados (só com `--new-stack`) |
| `--no-code` | desligado | **não** abre o VS Code ao final |

### Nome da pasta ≠ nome da branch

**Barra no nome significa "isto é a branch inteira"**, e a pasta sai dela
trocando barra por hífen:

```bash
moodev new paygw-pix              # pasta paygw-pix       branch feature/paygw-pix
moodev new fix/tls-porta          # pasta fix-tls-porta   branch fix/tls-porta
moodev new x --branch hotfix/y    # pasta x               branch hotfix/y
```

Sem barra, mantém-se o prefixo `feature/`, que cobre o caso comum sem obrigar a
digitá-lo.

> Antes o `feature/` era cravado, então `moodev new fix-docs` criava
> `feature/fix-docs` — um conserto classificado como feature. A branch existe
> neste repositório; foi assim que o problema apareceu.

O `moodev` recusa nome de branch inválido ou já existente **antes de criar qualquer
coisa**. O `moodev rm` **lê a branch da worktree** em vez de deduzi-la do nome da
pasta, então uma `fix/…` não fica órfã.

### `--from`

O padrão é `origin/dev`, a branch de integração — o mesmo ponto que o fluxo do
projeto usa (`feature → dev → deploy`).

**`main` não serve como base:** continua sem `.devcontainer/` e está fora do
fluxo normal, onde não há PR `dev`→`main`. O `moodev new` confere que a base tem
`.devcontainer/devcontainer.json` e para com a mensagem do que fazer. Uma
worktree meio provisionada é pior que nenhuma, porque parece pronta.

**A base precisa conter o que o stack de origem serve.** O `moodev new` resolve duas
origens diferentes: o **código** vem do `--from`, o **banco** vem da worktree
offset 0 — a branch que estiver lá, possivelmente à frente da `dev`. Se estiver,
o banco chega com plugin mais novo que o código e o Moodle recusa:

```
Cannot downgrade format_ldg from 2026090304 to 2026090301
```

O `moodev` compara os `version.php` que mudaram entre as duas refs e recusa **antes
de criar qualquer coisa**, listando os plugins e as duas saídas: `--from` da
branch que a origem serve, ou `--seed fresh`. Veja em qual branch está o offset 0
com `moodev ls`.

> A comparação lê o `HEAD` da origem. Um bump de versão ainda não commitado lá já
> está no banco e não aparece — nesse caso o `moodev` avisa, mas segue.

### `--seed`

Só tem efeito com `--new-stack`; sem ele o banco é o do stack atual, e o `moodev`
avisa se você passar à toa.

```bash
# clone (padrão): cópia do banco e do moodledata do principal
moodev new fix/tls-porta --new-stack --from <branch do offset 0>

# fresh: Moodle instalado do zero, login admin/admin
moodev new fix/tls-porta --new-stack --seed fresh

# share: usa os dados do principal (com ele parado)
moodev new fix/tls-porta --new-stack --seed share --from <branch do offset 0>
```

- **`clone`** (padrão) — `mariadb-dump` do stack principal mais cópia do
  `moodledata`. A feature nasce com os dados de teste. ~30–60 s. Exige o stack de
  origem **no ar** — o dump roda dentro do container dele. Cache, sessões e
  `temp` não vão junto: são do outro `wwwroot` e do outro domínio.
- **`fresh`** — `install_database.php` do zero, admin/admin. Mais lento e sem
  dados, mas exercita a migração do plugin em base virgem, que é o que a VPS vai
  ver. É o único modo que dispensa o `--from`: banco vazio nunca fica à frente do
  código, então a checagem nem roda.
- **`share`** — reescreve o `.env` apontando `MOODLE_HOST_DATA` e `DB_HOST_DATA`
  para os caminhos do principal. **Os dois stacks disputam o mesmo schema**: um
  `upgrade.php` num afeta o outro, e dois MariaDB no mesmo diretório de dados não
  convivem — na prática, só com o principal parado (`moodev down`). Só para inspeção
  rápida.

> Remover a worktree depois é seguro nos três: o `moodev rm` só apaga dados sob
> `ldg-data/`, então um `--seed share` não leva o banco principal junto.

### `--no-code`

O `moodev new` termina abrindo o VS Code dentro do container. Isto pula o passo —
útil em script, por SSH, ou quando você só quer o ambiente no ar para rodar
teste por linha de comando. Dá para abrir depois com `moodev code <worktree>`.

### O que ele faz, em ordem

1. confere que a base tem `.devcontainer/`
2. confere que a base não está atrás do banco que vai ser semeado
3. `git worktree add ../<nome> -b <branch> <base>`
4. copia os cinco conjuntos de arquivos gitignored
5. **gera** o `.env` (não copia com `sed`) apontando o código para a worktree nova
6. ajusta `MOODLE_URL` e os caminhos no `dev.env` copiado
7. coloca no ar — reaproveitando o stack, ou subindo um próprio com `--new-stack`
8. roda `upgrade.php` e `purge_caches.php`
9. abre o VS Code dentro do container

Os dois primeiros passos são checagens, e é de propósito que venham antes de tudo:
uma worktree meio provisionada é pior que nenhuma, porque parece pronta.

---

## 5. `moodev use`

```bash
moodev use <worktree>
```

O stack principal passa a servir o código de outra worktree. Mesmo container,
mesmo banco, mesmas portas, mesma URL — **só o bind mount do código muda**.

A worktree anterior passa a offset `-`; a nova assume o `0`. Como o banco é
compartilhado, o `moodev use` roda `upgrade.php` ao trocar: uma branch pode trazer
plugin que a outra não conhece.

> Se as migrações entre as duas branches forem destrutivas, prefira
> `--new-stack` — bancos separados não se atropelam.

---

## 6. `moodev rm`

```bash
moodev rm <worktree> [--force]
```

Remove a worktree e a **branch que ela tem de fato** — lida da worktree, não
deduzida do nome da pasta.

O resto depende do offset:

| | Stack próprio (`≥ 1`) | Servida agora (`0`) | Sem stack (`-`) |
|---|---|---|---|
| Containers e volumes | derrubados | — | **intocados** |
| `~/localhost/ldg-data/<nome>/` | apagado | não existe | não existe |
| Dados do stack principal | — | **preservados** | **preservados** |
| Stack principal | **intocado** | movido para `dev` | **intocado** |

**Só a worktree servida move o stack**, e por obrigação: o código montado no
container vai sumir do disco. Nos outros casos o principal segue rodando —
remover uma worktree não é motivo para interromper o trabalho em outra.

A distinção de dados não é cosmética. A linha de registro de uma worktree sem
stack aponta para o `moodledata` e o `dbdata` **do principal** — foi ele que a
serviu. Apagar por ali destruiria o banco principal a partir de um comando que
parece local. O `moodev rm` só apaga dados sob `~/localhost/ldg-data/`, e só derruba
stack quando existe um próprio.

Antes de remover, avisa se houver alteração não commitada ou commit da **própria
branch** fora do remoto, e pede o nome por extenso (`--force` pula).

**`dev` é recusada** — é a worktree de repouso, para onde o stack volta.

---

## 7. `moodev doctor`

Primeiro comando a rodar quando algo estiver estranho. Confere, nesta ordem:

1. **A própria instalação** — onde está o script, sob que nome responde no PATH,
   e se o symlink aponta para lugar seguro
2. **Ferramentas** — `docker`, `git`, `code`, `devcontainer`
3. **Disco** — piso de 5 GB (uma feature custa ~1 GB)
4. **Certificado** — validade do par mkcert
5. **Worktrees** — `.env` coerente com o registro, arquivos gitignored presentes
6. **Portas** — offsets únicos

Saída limpa termina com `ok nenhum problema`.

---

## 8. Onde o `moodev` guarda estado

`<raiz>/.moodev/registry.tsv` — um TSV com uma linha por worktree:

```
nome  offset  stack  wwwroot  moodledata  dbdata
```

Fica **fora de qualquer worktree** porque é estado da *máquina*, não do código:
não deve ser commitado nem sumir quando uma worktree é removida.

Na primeira execução o `moodev` adota o ambiente existente como offset 0,
descobrindo qual worktree é a principal **pelo bind mount do container que está
no ar** — não por varredura de disco, que acertaria por acaso.

> Não edite `.env` nem portas à mão: o `moodev` gera esses arquivos e o `moodev doctor`
> reclama quando divergem do registro.

### `.moodev/host` — o nome do stack principal

Uma linha só, com o host do **offset 0**. Sem o arquivo, `localhost`.

```bash
echo courses.leodg.localhost > <raiz>/.moodev/host
moodev use <worktree do offset 0>   # alinha o MOODLE_URL do dev.env
moodev restart
```

`use`, `new`, `ls` e `up` passam a escrever e mostrar
`https://courses.leodg.localhost:8443`. Stack secundário continua em
`localhost`: é efêmero, e o certificado sempre cobre esse nome.

Mora aqui, e não no `dev.env`, porque o `moodev` **reescreve** o `MOODLE_URL` a
cada `use` — editado à mão, o nome sumia no próximo `moodev new`. O certificado
precisa cobrir o nome, e o `moodev doctor` confere. Por que `*.localhost`, e o
resto do ambiente: [`ambiente-local.md`](ambiente-local.md).

---

## 9. Instalação

```bash
# ponto fixo na raiz, apontando para a worktree que fornece o moodev
mkdir -p ~/localhost/gitworktree-bare-moodle/.devcontainer/bin
ln -sfn ../../dev/.devcontainer/bin/moodev \
        ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev

# o PATH aponta para o ponto fixo, e nunca mais muda
ln -sfn ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev ~/.local/bin/moodev

# CLI do Dev Containers
sudo npm install -g @devcontainers/cli

moodev doctor
```

**Por que dois symlinks.** O script mora *dentro* de uma worktree, e worktree é
descartável — apontar o PATH direto para uma delas significa que apagá-la faz o
comando sumir. Com a indireção, trocar de worktree é mexer em **um** symlink na
raiz; o `~/.local/bin/moodev` fica intacto.

**Por que `dev`.** `main` não tem `.devcontainer/` e está bem atrás. `dev` é a
branch de integração: sempre tem a versão mais recente do `moodev` que passou por PR.

> **Efeito colateral:** o `moodev` do PATH é o de `dev`. Para exercitar uma versão em
> desenvolvimento, chame pelo caminho: `./.devcontainer/bin/moodev` de dentro da
> worktree da feature.

### Manual do sistema

```bash
sudo install -m 644 docs/man/moodev.1 /usr/local/share/man/man1/moodev.1
sudo mandb -q
man moodev
```

Sem `sudo`, num diretório do usuário:

```bash
mkdir -p ~/.local/share/man/man1
install -m 644 docs/man/moodev.1 ~/.local/share/man/man1/moodev.1
mandb -q ~/.local/share/man
```

---

## 10. Receitas

**Começar uma feature**

```bash
moodev new paygw-pix
```

**Alternar entre duas features**

```bash
moodev use paygw-pix
moodev use relatorios
```

**Comparar duas features lado a lado**

```bash
moodev new relatorios --new-stack     # 8463, banco próprio
# a atual continua em 8443
```

**Rodar um CLI do Moodle**

```bash
moodev cli upgrade.php --non-interactive
moodev cli purge_caches.php
```

**Rodar os testes** (dentro do container, como `1000:33`)

```bash
moodev shell
php vendor/bin/phpunit --testsuite local_marketplace_testsuite
```

**Adotar uma imagem nova**

```bash
moodev restart      # up sozinho reaproveita o container existente
```

**Encerrar uma feature depois do merge**

```bash
moodev rm paygw-pix
```

---

## 11. Problemas conhecidos

**`cf: command not found`** — o symlink ficou pendurado, provavelmente porque a
worktree que ele apontava foi removida. O `moodev doctor` diagnostica, mas ele
próprio some junto; conserte pela indireção:

```bash
ln -sfn ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev ~/.local/bin/moodev
```

**`arithmetic syntax error: operand expected`** — versão antiga do `moodev` lendo um
registro escrito por uma versão nova. Atualize a worktree que fornece o comando:

```bash
cd ~/localhost/gitworktree-bare-moodle/dev && git pull --ff-only origin dev
```

**`required variable DB_HOST_DATA is missing a value`** — falta o symlink que
torna o `.env` visível para a extensão do VS Code. Ela monta o próprio
`docker compose -f …` **sem `--env-file`**, e o compose procura o `.env` no
diretório do primeiro arquivo compose. `moodev up` recria o link; se precisar à mão:

```bash
ln -sfn ../../.env <worktree>/.devcontainer/compose/.env
```

**Site em HTTP 500, `invaliddatarootpermissions`** — algum processo criou
diretório com dono root no `moodledata`. O entrypoint corrige no boot; para
resolver com o container no ar:

```bash
docker exec -u root <container> chown -R www-data:www-data /var/www/moodledata
```

**`Cannot downgrade <plugin> from X to Y`** — o banco está à frente do código. Ou
a worktree foi criada de uma base atrás da branch que o stack de origem serve, ou
você trocou de branch depois. O `moodev new` recusa isso desde 03/09/2026; se já
aconteceu, o conserto é trazer o código para a altura do banco:

```bash
git -C <worktree> merge --ff-only <branch que o banco conhece>
moodev cli upgrade.php --non-interactive && moodev cli purge_caches.php
```

Se o fast-forward não for possível, ou você não quiser aquele trabalho na branch,
recrie o ambiente com `--seed fresh` — perde os dados de teste, mas nasce
coerente.

**`Could not open input file: …/admin/cli/…`** — nesta base o `admin/` fica na
**raiz** do repositório, não em `public/`. O `moodev cli` procura nos dois lugares;
se você chamou `php` direto, ajuste o caminho.

**O VS Code reconstrói a imagem** — não deveria. Confira que o
`devcontainer.json` não voltou a ter `features` nem `build-dev.yml`, e que só
existe uma imagem `vsc-*` por worktree:

```bash
docker images | grep vsc-
```

---

## 12. Limites conhecidos

- **Um ambiente por vez no modo padrão.** Banco compartilhado; alternar entre
  branches com versões diferentes de plugin roda `upgrade.php` nos dois sentidos.
- **`--new-stack` custa ~900 MB** por feature. O `moodev new` recusa abaixo de 5 GB
  livres.
- **Uma camada de UID de ~726 MB por worktree**, criada uma vez, do
  `updateRemoteUserUID`. Desligá-la eliminaria a camada, mas o repo é bind mount
  com dono 1000 e modo 644 — `www-data` como uid 33 cairia em "outros" e o VS
  Code não salvaria arquivo nenhum.
- **`main` não é utilizável** como base nem como repouso enquanto não tiver
  `.devcontainer/`.

---

## Ver também

- [`guia-worktrees.md`](guia-worktrees.md) — o fluxo completo: portas, `.env`,
  VS Code, a VPS, armadilhas
- [`../architecture/estrutura-do-repositorio.md`](../architecture/estrutura-do-repositorio.md) — o bare repo e as worktrees
- `cf --help` — referência rápida, sempre em dia com o script
