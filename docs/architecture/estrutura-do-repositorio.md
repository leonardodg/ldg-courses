# Estrutura do projeto

Trabalhar com gitworktree nesta pasta para gerenciar meus arquivos do .git fazendo um link da pasta .git para o .bare
Pasta faz gerenciamento do arquivos HEAD do git o quais devem ser adicionado e removidos os worktree's
Folder: /home/leodg/localhost/gitworktree-bare-moodle


> Commands:
```
$ git remote -v
origin  https://github.com/moodle/moodle.git (fetch)
origin  git@github.com:leonardodg/ldg-courses.git (push)

$ git worktree add main
$ git worktree remove main
```

## Estrutura
_____________________________________________________

```
leodg@Alienware-m18-R2:~/localhost/gitworktree-bare-moodle> ls -la
total 20
drwxrwxr-x  4 leodg leodg 4096 Aug 24 11:49 .
drwxrwxr-x 35 leodg leodg 4096 Aug 24 11:41 ..
drwxrwxr-x  7 leodg leodg 4096 Aug 24 11:57 .bare
-rw-rw-r--  1 leodg leodg   16 Aug 24 11:44 .git
drwxrwxr-x 11 leodg leodg 4096 Aug 24 11:48 MOODLE_502_STABLE
leodg@Alienware-m18-R2:~/localhost/gitworktree-bare-moodle> ls -la .bare/
total 100
drwxrwxr-x 7 leodg leodg  4096 Aug 24 11:57 .
drwxrwxr-x 4 leodg leodg  4096 Aug 24 11:49 ..
-rw-rw-r-- 1 leodg leodg    21 Aug 24 11:44 HEAD
-rw-rw-r-- 1 leodg leodg   182 Aug 24 11:57 config
-rw-rw-r-- 1 leodg leodg    73 Aug 24 11:43 description
drwxrwxr-x 2 leodg leodg  4096 Aug 24 11:43 hooks
drwxrwxr-x 2 leodg leodg  4096 Aug 24 11:43 info
drwxrwxr-x 4 leodg leodg  4096 Aug 24 11:43 objects
-rw-rw-r-- 1 leodg leodg 61152 Aug 24 11:44 packed-refs
drwxrwxr-x 4 leodg leodg  4096 Aug 24 11:43 refs
drwxrwxr-x 3 leodg leodg  4096 Aug 24 11:49 worktrees
leodg@Alienware-m18-R2:~/localhost/gitworktree-bare-moodle> 
```
_____________________________________________________

## Fluxo de Trabalho

- Passo 1: Atualizar a base local (dev)Antes de adicionar o plugin, sincronize a branch dev com as atualizações do Moodle oficial:Bash

cd ~/localhost/gitworktree-bare-moodle/MOODLE_502_STABLE
git pull

cd /home/leodg/localhost/gitworktree-bare-moodle/dev
git merge upstream/MOODLE_502_STABLE
git push origin dev


- Passo 2: Criar a worktree da feature

Use o `moodev`. Ele cria a worktree, copia os arquivos que o git ignora (`.env`,
`config.php`, secrets, certificados, `env/*.env`), aloca portas próprias, clona
o banco, sobe o stack e abre o VS Code dentro do container:

```
moodev new meu-plugin
```

A base e `origin/dev` por padrao, que e o Passo 1 acima — nao precisa passar
`--from`. `main` nao serve como base: segue sem `.devcontainer/` e esta fora do
fluxo. O `moodev new` confere isso antes de criar qualquer coisa.

Documentação completa dos comandos, portas, `.env`, VS Code e como rodar duas
worktrees ao mesmo tempo: **`docs/dev/guia-worktrees.md`** dentro da worktree.

Fazendo à mão (equivalente ao que o `moodev` automatiza):

```
cd ~/localhost/gitworktree-bare-moodle
git worktree add feature-meu-plugin -b feature/instalacao-plugin origin/dev
cd feature-meu-plugin
```

- Passo 3: Instalar e commitar o pluginAdicione a pasta do plugin no caminho correto do Moodle (exemplo para plugin de disponibilidade em availability/condition/meu_plugin):Bash# Copie ou baixe o plugin para o diretório correto
```
cp -r /caminho/do/plugin availability/condition/meu_plugin

# Commite as alterações
git add availability/condition/meu_plugin
git commit -m "feat: adiciona plugin de disponibilidade"
git push -u origin feature/instalacao-plugin
```

- Passo 4: Pull Request e Merge no GitHub

Abra o PR: `feature/instalacao-plugin` -> `dev`. Depois do merge, remova o
ambiente local inteiro (stack, worktree, branch e dados) com:

```
moodev rm meu-plugin
```

Ele avisa se houver alteração não commitada ou commit da propria branch fora do
remoto, e pede confirmação.

O que acontece depende do estado da worktree (coluna `OFFSET` do `moodev ls`):

| | Stack proprio (`>= 1`) | Servida agora (`0`) | Sem stack (`-`) |
|---|---|---|---|
| Containers e volumes | derrubados | — | intocados |
| Dados proprios | apagados | não existem | não existem |
| Dados do principal | — | preservados | preservados |
| Stack principal | intocado | **movido para `dev`** | intocado |

So a worktree SERVIDA move o stack, e por obrigacao: o codigo montado no
container vai sumir do disco. `dev` e a worktree de repouso e por isso o
`moodev rm` a recusa.

Fazendo à mão:

```
cd ~/localhost/gitworktree-bare-moodle
git worktree remove feature-meu-plugin
git branch -d feature/instalacao-plugin
```

Atualize sua dev local:

```
cd dev
git pull origin dev
```

- Passo 5: Promover para main (Produção)Com o plugin validado na dev, envie as alterações para a main via PR (dev $\rightarrow$ main) no GitHub. Sincronize a main local após o merge:Bashcd ~/localhost/gitworktree-bare-moodle/main
git pull origin main

- Passo 6: Deploy na VPSNa sua VPS, clone o repositório apontando diretamente para a branch main e rode a atualização de banco do Moodle via CLI:Bash

```
# 1. Clonar na VPS (apenas na primeira vez)
git clone -b main git@github.com:leonardodg/ldg-courses.git /var/www/html/moodle

# 2. Em atualizações futuras na VPS:
cd /var/www/html/moodle
git pull origin main

# 3. Rodar o upgrade CLI do Moodle para instalar a tabela do plugin no banco
php admin/cli/upgrade.php --non-interactive
```




## Ambientes locais (moodev)

**Não é mais preciso "trocar de worktree".** Cada worktree tem o próprio
ambiente, e vários rodam ao mesmo tempo.

```
moodev ls                          # worktrees, portas, status e URLs
moodev new <nome>                  # worktree nova, servida pelo stack que ja roda
moodev new <nome> --new-stack      # worktree nova com ambiente PROPRIO, em paralelo
moodev use <worktree>              # o stack atual passa a servir outra worktree
moodev up|down|restart [worktree]  # controla o stack
moodev code [worktree]             # abre o VS Code dentro do container
moodev shell [worktree]            # bash no container, como 1000:33
moodev cli upgrade.php             # roda um admin/cli do Moodle
moodev rm <worktree>               # remove worktree, branch, dados e stack
moodev doctor                      # confere ferramentas, disco, cert, .env e portas
```

**Dois modos.** Por padrao o `moodev new` so troca o codigo montado no container que
ja roda: mesmo banco, mesmas portas, custo zero — mas um ambiente de cada vez.
Com `--new-stack`, sobe um ambiente proprio que roda em PARALELO, ao custo de
~900 MB (container, banco e portas separados).

Cada worktree recebe um **offset**, e dele saem o nome do stack e as portas:

| Offset | Stack | HTTP | HTTPS | Banco | Xdebug |
|---|---|---|---|---|---|
| 0 | `ldg-courses` | 8080 | 8443 | 3307 | 9004 |
| 1 | `ldg-courses-<nome>` | 8090 | 8453 | 3317 | 9014 |
| 2 | `ldg-courses-<nome>` | 8100 | 8463 | 3327 | 9024 |

O offset 0 é o ambiente principal: `base.yml` usa
`name: ${STACK_NAME:-ldg-courses}`, então quem não define `STACK_NAME` cai nesse
default — é assim que o principal e a VPS sobem sem precisar do `moodev`.

Em compensação, **trocar esse default renomeia o stack de todo mundo**, VPS
inclusive, e stack antigo tem que ser derrubado antes: para o compose é um
projeto novo, e as portas continuam ocupadas pelo velho.

**Guia completo** (comandos, `.env`, VS Code, duas worktrees em paralelo,
armadilhas): `docs/dev/guia-worktrees.md`, dentro de qualquer worktree que tenha
`.devcontainer/`.

### Onde o moodev mora

O script e versionado no repo, em `.devcontainer/bin/moodev` — cada worktree tem a
sua copia. Como worktree e coisa descartavel, o PATH nao aponta direto para
nenhuma: ha um **ponto fixo na raiz** que indireciona.

```
~/.local/bin/moodev
   └─> ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev   (ponto fixo)
          └─> ../../dev/.devcontainer/bin/moodev                      (qual versao)
```

Trocar a worktree que fornece o `moodev` e mexer em **um** symlink, o da raiz; o do
PATH nunca muda. `~/.local/bin` ja esta no PATH pelo `~/.profile` e `~/.bashrc`.

Aponta para `dev` e nao para `main` porque `main` nao tem `.devcontainer/` — o
fluxo do projeto e `feature → dev → deploy`, sem PR `dev`→`main`.

### Instalação numa máquina nova

```
mkdir -p ~/localhost/gitworktree-bare-moodle/.devcontainer/bin
ln -sfn ../../dev/.devcontainer/bin/moodev ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev
ln -sfn ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev ~/.local/bin/moodev
sudo npm install -g @devcontainers/cli
moodev doctor
```

### Modo manual

Se precisar apontar um container para outra worktree sem o `moodev`, o caminho
montado vem de `MOODLE_HOST_WWWROOT` no `.env`:

```
cd /caminho/da/outra-worktree
cp -a ../feature-marketplace/.devcontainer/secrets .devcontainer/
cp -a ../feature-marketplace/.devcontainer/certs/develop-local.* .devcontainer/certs/
cp -a ../feature-marketplace/.devcontainer/env/{db,dev}.env .devcontainer/env/
cp ../feature-marketplace/config.php .
# edite MOODLE_HOST_WWWROOT, MOODLE_URL e as portas a mao

export COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml:.devcontainer/compose/local-tls.yml:.devcontainer/compose/local-dev.yml
export COMPOSE_ENV_FILE=.env
docker compose up -d
```

> O `sed "s#feature-marketplace#$(basename $PWD)#"` que este README ensinava
> antes substitui a string em QUALQUER linha, inclusive dentro de
> `MOODLE_HOST_DATA`. O `moodev new` gera o `.env` em vez de reescrevê-lo.
>
> `build-dev.yml` saiu desta lista: ele tem uma seção `build:` e reconstrói a
> imagem a cada caminho novo. Para reconstruir de propósito, use `moodev build`.
