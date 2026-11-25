# Guia de worktrees e ambientes locais

Como abrir uma feature nova sem reconfigurar nada, e como manter duas rodando ao
mesmo tempo sem que uma atrapalhe a outra.

Público: quem desenvolve neste repositório. Para arquitetura do marketplace, veja
`decisoes-marketplace.md`; para os plugins, `guia-desenvolvedor.md`.

---

## 1. O problema que isto resolve

O repositório é um *bare repo* com várias worktrees:

```
/home/leodg/localhost/gitworktree-bare-moodle/
├── .bare/                  repositório git de verdade
├── .moodev/                    registro de ambientes (criado pelo moodev)
├── feature-marketplace/    worktree principal
├── leodg-academy/
└── main/  dev/  ...
```

`git worktree add` entrega **apenas o código versionado**. Tudo que faz o
ambiente subir está no `.gitignore`, e antes era copiado à mão:

| Arquivo | Por que é ignorado |
|---|---|
| `/.env` | caminhos absolutos da máquina local |
| `/config.php` | gerado a partir de `config-docker.php` |
| `.devcontainer/secrets/` | senhas do banco |
| `.devcontainer/certs/develop-local.{crt,key}` | par do mkcert |
| `.devcontainer/env/db.env`, `env/dev.env` | credenciais e URL do site |

Além disso o ambiente era **único por construção**: `base.yml` fixava um
`name:` só e o `.env` fixava as portas. Subir o compose de outra
worktree não criava um segundo ambiente — **recriava o mesmo container**
apontando para outro código.

O `moodev` resolve os dois problemas.

---

## 2. Começando

```bash
moodev new paygw-pix
```

Isso faz, em ordem:

1. confere que a branch base (`origin/dev`) tem `.devcontainer/` — ver §8;
2. confere que a base não está atrás do banco que a worktree vai usar — ver §8;
3. `git worktree add ../paygw-pix -b feature/paygw-pix origin/dev`;
4. copia os cinco conjuntos de arquivos gitignored;
5. **gera** o `.env` apontando o código para a worktree nova;
6. ajusta `MOODLE_URL` e os caminhos no `dev.env` copiado;
7. faz o stack que já roda passar a servir esse código;
8. roda `upgrade.php` e `purge_caches.php`;
9. abre o VS Code **dentro** do container.

Ao final o site responde em `https://localhost:8443` — a URL de sempre, o mesmo
banco de sempre — agora servindo o código da feature nova. Nenhum container novo
foi criado.

Para um ambiente **isolado**, que roda em paralelo com o atual:

```bash
moodev new paygw-pix --new-stack
```

Aí sim vêm container, banco e portas próprios, e o site fica em
`https://localhost:8453`. Ver a comparação em §3.

---

## 3. Comandos

Notação: `<obrigatório>`, `[opcional]`. Onde aparece `[worktree]`, é o **nome da
worktree** — a mesma coluna `WORKTREE` que o `moodev ls` mostra, por exemplo
`paygw-pix`.

| Comando | O que faz |
|---|---|
| `moodev new <nome> [opções]` | cria worktree e ambiente (opções logo abaixo) |
| `moodev use <worktree>` | o stack atual passa a servir outra worktree |
| `moodev ls` | worktrees, offsets, stacks, status e URLs |
| `moodev up [worktree]` | sobe o stack |
| `moodev down [worktree]` | para o stack |
| `moodev restart [worktree]` | `down` e `up` |
| `moodev code [worktree]` | abre o VS Code dentro do container |
| `moodev shell [worktree]` | `bash` no container, como `1000:33` |
| `moodev logs [worktree]` | segue os logs |
| `moodev cli <script.php> [args]` | roda um `admin/cli` do Moodle |
| `moodev build [worktree]` | reconstrói a imagem (único que carrega `build-dev.yml`) |
| `moodev rm <worktree> [--force]` | remove worktree, branch, dados e stack |
| `moodev doctor` | confere ferramentas, disco, certificado, `.env` e portas |

**Omitindo o nome, o comando age sobre a worktree do diretório atual.** Estando
dentro de `paygw-pix/`, `moodev up` sobe aquele ambiente; de fora, seria
`moodev up paygw-pix`.

### Lendo o `moodev ls`

```
WORKTREE               OFFSET STACK                      STATUS  URL
paygw-pix              0      ldg-courses               up      https://localhost:8443
  feature/paygw-pix
relatorios             1      ldg-courses-relatorios    up      https://localhost:8453
  feature/relatorios
fix-tls                -      -                          sem stack   -
  fix/tls
dev                    -      -                          sem stack   -
  dev
```

A coluna `OFFSET` diz o estado da worktree, e é o que determina o que cada
comando faz com ela:

| Offset | Significa | Consequência |
|---|---|---|
| `0` | é quem o **stack principal serve agora** | é o site em 8443 |
| `≥ 1` | tem **stack próprio**, rodando em paralelo | portas próprias, banco próprio |
| `-` | existe no disco, **sem ambiente servindo** | `moodev use` a coloca no ar |

Uma worktree com `-` não está quebrada — só não é a que o container está
servindo no momento. A segunda linha de cada entrada é a **branch**, que pode
diferir do nome da pasta (ver §3, opções do `moodev new`).

### Dois modos: trocar o código, ou subir um ambiente novo

Esta é a decisão mais importante ao começar uma feature, e o padrão é o barato.

| | **Padrão** (`moodev new`, `moodev use`) | **`moodev new --new-stack`** |
|---|---|---|
| Container | o que já roda | novo |
| Banco | **o mesmo** | próprio |
| Portas | 8080/8443/3307/9004 | offset próprio |
| Custo | ~0 | ~900 MB, ~1 min |
| Em paralelo? | não, um de cada vez | **sim** |

**O padrão só troca o bind mount do código.** Mesmo container, mesmo banco,
mesma URL — o que muda é qual worktree o Apache serve. É instantâneo e não
consome nada.

```bash
moodev new fix-docs        # worktree nova, servida pelo stack que já roda
moodev use paygw-pix       # volta o stack para outra worktree existente
```

Use `--new-stack` quando precisar das **duas features no ar ao mesmo tempo**
(comparar comportamento lado a lado, deixar uma rodando enquanto trabalha na
outra) ou de **bancos separados**:

```bash
moodev new paygw-pix --new-stack
```

> **O banco é compartilhado no modo padrão.** Trocar entre branches com versões
> diferentes de plugin faz o Moodle pedir `upgrade.php` de ida e de volta — o
> `moodev use` roda isso sozinho ao trocar. Se as migrações forem destrutivas,
> prefira `--new-stack`.

Ao trocar, a worktree anterior **sai do registro** (o `.env` dela fica no disco).
Um `moodev up` nela para com "não está no registro", em vez de disputar as portas com
o stack que agora serve outra coisa.

### Opções do `moodev new`

| Opção | Padrão | O que faz |
|---|---|---|
| `--branch <branch>` | `feature/<nome>` | nome da branch, quando difere do da pasta |
| `--from <branch>` | `origin/dev` | de onde a branch nova sai — precisa conter a branch do offset 0 (§8) |
| `--new-stack` | desligado | ambiente próprio em vez de reaproveitar o atual |
| `--seed <modo>` | `clone` | de onde vêm os dados (só com `--new-stack`) |
| `--no-code` | desligado | **não** abre o VS Code ao final |

#### Nome da pasta ≠ nome da branch

São duas coisas, e o `moodev` deixava isso confuso: cravava `feature/` em tudo, então
`moodev new fix-docs` criava a branch `feature/fix-docs` — um conserto classificado
como feature.

**Barra no nome significa "isto é a branch inteira"**, e a pasta sai dela
trocando barra por hífen:

```bash
moodev new paygw-pix              # pasta paygw-pix       branch feature/paygw-pix
moodev new fix/tls-porta          # pasta fix-tls-porta   branch fix/tls-porta
moodev new x --branch hotfix/y    # pasta x               branch hotfix/y
```

Sem barra, mantém-se o prefixo `feature/`, que cobre o caso comum sem obrigar a
digitá-lo. O `moodev` recusa nome de branch inválido ou já existente, antes de criar
qualquer coisa. O `moodev rm` **lê a branch da worktree** em vez de deduzi-la do
nome da pasta, então uma `fix/…` não fica órfã.

#### `--no-code`

O `moodev new` termina abrindo o VS Code **dentro** do container. `--no-code` pula
esse passo — útil em script, por SSH, ou quando você só quer o ambiente no ar
para rodar teste por linha de comando. A worktree fica pronta do mesmo jeito;
dá para abrir depois com `moodev code <worktree>`.

#### `--seed <modo>`

De onde vêm os dados do banco. Só tem efeito com `--new-stack`; sem ele o banco é
o do stack atual, e o `moodev` avisa se você passar `--seed` à toa.

**`clone` (padrão) — cópia do banco e do `moodledata` do principal**

```bash
moodev new fix/tls-porta --new-stack --from <branch do offset 0>
```

`mariadb-dump` do stack de origem mais cópia do `moodledata`: a feature nasce com
os cursos, usuários e matrículas de teste. ~30–60 s. Exige o stack de origem **no
ar** (o dump roda no container dele); se estiver parado, `moodev up <origem>` antes.
Cache, sessões e `temp` não vão junto — são do outro domínio, e sessão copiada faz
o site novo tentar validar um login que não é dele.

O `--from` é o que mantém código e banco alinhados; veja a seção 8.

**`fresh` — Moodle instalado do zero**

```bash
moodev new fix/tls-porta --new-stack --seed fresh
```

`install_database.php`, login **admin / admin**. Site limpo, sem curso e sem tema
aplicado: serve para provar instalação de plugin em base virgem, que é o que a
VPS vai ver, ou para reproduzir bug de site novo. É o mais lento na primeira
subida, e o único modo que dispensa o `--from`.

**`share` — usa os dados do principal**

```bash
moodev new fix/tls-porta --new-stack --seed share --from <branch do offset 0>
```

Reescreve o `.env` apontando `MOODLE_HOST_DATA` e `DB_HOST_DATA` para os caminhos
do principal, e avisa em amarelo. **Os dois stacks disputam o mesmo schema** — um
`upgrade.php` num afeta o outro — e dois MariaDB no mesmo diretório de dados não
convivem: na prática, só com o principal parado (`moodev down`). Só para inspeção
rápida.

| Quero… | Modo |
|---|---|
| trabalhar com os cursos de teste que já existem | `clone` |
| site limpo, ou fugir do `--from` da origem | `fresh` |
| mexer nos dados do principal com outro código | `share`, com o principal parado |

Desfazer é igual nos três: `moodev rm fix-tls-porta` — worktree, branch, dados e
stack. Note a pasta com hífen, mesmo tendo criado com barra. E o `moodev rm` só apaga
dados sob `ldg-data/`, então um `--seed share` não leva o banco principal junto.

---

## 4. Portas e `.env`

Cada worktree recebe um **offset** inteiro. Dele saem o nome do stack e as
quatro portas, por soma:

| Offset | Stack | HTTP | HTTPS | Banco | Xdebug |
|---|---|---|---|---|---|
| 0 | `ldg-courses` | 8080 | 8443 | 3307 | 9004 |
| 1 | `ldg-courses-<nome>` | 8090 | 8453 | 3317 | 9014 |
| 2 | `ldg-courses-<nome>` | 8100 | 8463 | 3327 | 9024 |

O offset 0 é o ambiente principal. `base.yml` usa
`name: ${STACK_NAME:-ldg-courses}`, então **quem não define `STACK_NAME` cai
nesse default** — é assim que o principal e a VPS sobem sem precisar do `moodev`.

A contrapartida: o default nomeia o stack de todo mundo que não define
`STACK_NAME`. Trocá-lo renomeia containers, rede e volumes da VPS junto, e o
stack antigo precisa ser derrubado à mão antes — para o compose é outro projeto,
e as portas seguem ocupadas pelo que está no ar.

O `moodev` só entrega um offset depois de conferir com `ss -ltn` que as quatro
portas estão livres. Se alguma estiver ocupada por outro serviço da máquina, ele
pula para o offset seguinte.

### O `.env` gerado

```bash
STACK_NAME=ldg-courses-paygw-pix          # separa este stack dos demais
MOODLE_HOST_WWWROOT=/home/leodg/localhost/gitworktree-bare-moodle/paygw-pix
MOODLE_HOST_DATA=/home/leodg/localhost/ldg-data/paygw-pix/moodledata
DB_HOST_DATA=/home/leodg/localhost/ldg-data/paygw-pix/dbdata
MOODLE_HTTP_PORT=8090
MOODLE_HTTPS_PORT=8453
DB_PORT=3317
XDEBUG_PORT=9014
```

O arquivo é **gerado**, não copiado com `sed`. O `README` antigo mandava fazer
`sed "s#feature-marketplace#$(basename $PWD)#" ../feature-marketplace/.env`, que
substitui a string em qualquer linha — inclusive dentro de `MOODLE_HOST_DATA`, se
um dia aquele caminho contiver o nome da worktree.

### O ajuste no `dev.env`

O `dev.env` copiado traz o `MOODLE_URL` da origem. O `moodev` reescreve três linhas:

```bash
MOODLE_URL=https://localhost:8453          # tem que bater com a porta do stack
MOODLE_HOST_WWWROOT=<caminho da worktree>
MOODLE_HOST_DATA=<moodledata da worktree>
```

`MOODLE_URL` é o `$CFG->wwwroot`. Se não bater **exatamente** com a URL usada no
navegador, a sessão não fecha e o login entra em laço.

> O `dev.env` do repositório apontava para `feature-infra-vps`, uma worktree que
> não existe mais. Era inofensivo — a variável só é lida como interpolação a
> partir do `.env` — mas mostra por que o `moodev` reescreve em vez de confiar.

---

## 5. Duas worktrees ao mesmo tempo

```bash
moodev new paygw-pix          # offset 1
moodev ls
```

```
WORKTREE               OFFSET STACK                      STATUS  URL
feature-marketplace    0      ldg-courses               up      https://localhost:8443
  feature/paygw-mercadopago
paygw-pix              1      ldg-courses-paygw-pix     up      https://localhost:8453
  feature/paygw-pix
```

Quatro containers no ar. O que fica **separado**:

| Recurso | Como é separado |
|---|---|
| Containers e rede | prefixo do projeto compose (`STACK_NAME`) |
| Portas | offset |
| Código | bind mount da worktree (`MOODLE_HOST_WWWROOT`) |
| Banco | `dbdata` próprio em `~/localhost/ldg-data/<nome>/` |
| `moodledata` | idem |
| Dataroot do PHPUnit | `phpu_moodledata` dentro da worktree |
| Histórico de shell | volume nomeado por worktree |

O que é **compartilhado de propósito**:

- **A imagem** `leodg/ldg-courses:development` — é a mesma para todos, e é
  justamente por isso que subir a segunda worktree não custa build.
- **O cache do Composer** (volume `ldg-courses_composer_cache`) — conteúdo
  imutável endereçado por hash; dividir evita rebaixar tudo a cada worktree.
- **O certificado** do mkcert — cobre `localhost`, então vale em qualquer porta.

Criar um curso num ambiente **não** o faz aparecer no outro: os bancos são
independentes. A exceção é `--seed share`, que existe exatamente para o caso
contrário.

### Xdebug com dois ambientes

Cada stack publica a própria porta de Xdebug (9004, 9014, …). No
`launch.json` da worktree, aponte a porta do offset dela. Duas sessões de debug
simultâneas funcionam porque as portas não colidem.

---

## 6. Como funciona no VS Code

`moodev code` (ou `moodev new`) abre a janela **já conectada ao container**, sem passar
pela caixa de diálogo. Por baixo é o endereçamento padrão da extensão Dev
Containers:

```
vscode-remote://dev-container+<caminho-do-host-em-hex>/var/www/html
```

### O que sobrou de build, e o que não sobrou

Sendo preciso: **abrir uma worktree já criada custa ~1 segundo** e reaproveita o
container. O que existe é um custo único, na *criação* de cada worktree.

| Momento | Antes | Agora |
|---|---|---|
| `moodev new` (uma vez por worktree) | build completo da imagem | camada de UID, ~726 MB |
| Reopen in Container | build completo de novo | ~1 s, container reaproveitado |

A camada de UID vem de `updateRemoteUserUID`, ligado por padrão quando o
`remoteUser` não é root: a CLI deriva uma imagem que remapeia `www-data` de uid
33 para o seu uid 1000, com um `chown -R` da árvore inteira. É por worktree,
porque o nome da imagem carrega o hash do caminho da pasta.

Dá para eliminá-la com `"updateRemoteUserUID": false`, **mas não vale**: o repo
é bind-mount com dono `leodg` (1000) e modo 644/755, então `www-data` como uid
33 cairia em "outros" e o VS Code não conseguiria **salvar arquivo nenhum**.

Verifique quando desconfiar:

```bash
docker images | grep vsc-      # uma por worktree; não deve crescer sozinha
```

### Duas causas de rebuild que foram removidas

Estas sim reconstruíam a imagem **a cada abertura**:

1. **`features` no `devcontainer.json`.** Toda feature declarada obriga a CLI do
   Dev Containers a construir uma **imagem derivada** para injetá-la, e ela
   refaz esse build por pasta. As três que existiam foram resolvidas na origem:
   - `git:1` — redundante, o stage `development` já instala `git`;
   - `common-utils:2` — traz zsh/oh-my-zsh e mexe em usuário; com
     `remoteUser: www-data` e terminal `bash` fixado, não agregava;
   - `shell-history:0` — virou `ENV HISTFILE` no Dockerfile mais um volume
     nomeado.
2. **`build-dev.yml` na lista de compose.** Aquele arquivo tem uma seção
   `build:` com `context: ../../` — o caminho muda a cada worktree, o cache não
   aproveita e a imagem é reconstruída. Hoje o `devcontainer.json` usa a imagem
   já publicada; `build-dev.yml` só entra via `moodev build`.

### Por que o `moodev` sobe pela CLI do Dev Containers

Se o stack sobe por `docker compose` puro e depois você faz "Reopen in
Container", a extensão gera os próprios arquivos de override, considera a
configuração diferente e **recria** os containers que acabaram de subir.
Subindo pela mesma CLI que o VS Code usa, a janela apenas se conecta.

A CLI está instalada em `~/.local/share/devcontainers-cli`, com symlink em
`~/.local/bin/devcontainer`. Sem ela o `moodev` ainda funciona, avisando que o
primeiro Reopen vai recriar os containers.

### Fechar a janela não derruba o ambiente

`"shutdownAction": "none"`. Sem isso, fechar a worktree A mataria o ambiente que
estava rodando — o padrão da extensão com compose é parar os serviços.

---

## 7. Onde o `moodev` mora, e como chamá-lo sem caminho

O script é **versionado no repositório**, em `.devcontainer/bin/moodev`. Ou seja,
ele viaja com a branch e toda worktree tem a sua cópia.

Para digitar `moodev` de qualquer diretório, a instalação usa **dois** symlinks
encadeados:

```
~/.local/bin/moodev
   └─> ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev     (ponto fixo)
          └─> ../../dev/.devcontainer/bin/moodev                        (qual versão)
```

```bash
# ponto fixo na raiz, apontando para a worktree que fornece o moodev
mkdir -p ~/localhost/gitworktree-bare-moodle/.devcontainer/bin
ln -sfn ../../dev/.devcontainer/bin/moodev \
        ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev

# o PATH aponta para o ponto fixo, e nunca mais muda
ln -sfn ~/localhost/gitworktree-bare-moodle/.devcontainer/bin/moodev ~/.local/bin/moodev
```

**Por que dois em vez de um.** O script mora *dentro* de uma worktree, e
worktree é coisa descartável — apontar o `PATH` direto para uma delas significa
que apagá-la faz o comando sumir sem explicação. Com a indireção, trocar de
worktree é mexer em **um** symlink na raiz; o `~/.local/bin/moodev` fica intacto.
O `readlink -f` percorre a cadeia inteira, então o `moodev` continua descobrindo a
raiz corretamente.

**Por que `dev` e não `main`.** `main` não tem `.devcontainer/` — está 108
commits atrás e fora do caminho normal deste projeto, onde o fluxo é
`feature → dev → deploy`, sem PR `dev`→`main`. `dev` é a branch de integração:
sempre tem a versão mais recente do `moodev` que passou por PR.

> **Efeito colateral:** o `moodev` do `PATH` passa a ser o de `dev`. Para exercitar
> uma versão em desenvolvimento, chame pelo caminho:
> `./.devcontainer/bin/moodev doctor` de dentro da worktree da feature.

`~/.local/bin` já entra no `PATH` nesta máquina, pelo `~/.profile` (padrão do
Ubuntu) e pelo `~/.bashrc`. Em outra máquina, confira:

```bash
case ":$PATH:" in *":$HOME/.local/bin:"*) echo ok;; *) echo "falta no PATH";; esac
```

O `moodev doctor` avisa se o link resolver para dentro de uma worktree **removível**
— ou seja, registrada com offset diferente de 0, que o `moodev rm` pode apagar.
Worktree permanente (`dev`, `main`) e a principal não disparam o aviso. E o
`moodev rm` reaponta o link sozinho se ele vier da worktree que está prestes a sair.

### CLI do Dev Containers

```bash
sudo npm install -g @devcontainers/cli
```

Sem sudo, dá para instalar num prefixo do próprio usuário:

```bash
npm install --prefix ~/.local/share/devcontainers-cli @devcontainers/cli
ln -sfn ~/.local/share/devcontainers-cli/node_modules/.bin/devcontainer \
        ~/.local/bin/devcontainer
```

Não faça as duas: `~/.local/bin` vem antes no `PATH` e sombreia a global.

### Conferindo

```bash
moodev doctor
```

A primeira seção da saída é a instalação do próprio `moodev`: onde está o script,
sob que nome ele responde no `PATH` e se o symlink aponta para lugar seguro.

### O registro

`~/localhost/gitworktree-bare-moodle/.moodev/registry.tsv`, um TSV com
`nome, offset, stack, wwwroot, moodledata, dbdata`. Fica fora de qualquer
worktree porque é estado **da máquina**, não do código — não deve ser commitado
nem sumir quando uma worktree é removida.

Na primeira execução o `moodev` adota o ambiente existente como offset 0, descobrindo
qual worktree é a principal pelo bind mount do container que está no ar.

---

## 8. De qual branch a feature nasce

O default do `--from` é **`origin/dev`**, a branch de integração — o mesmo ponto
que o fluxo do projeto usa (`feature → dev → deploy`). Na prática:

```bash
moodev new paygw-pix                 # nasce de origin/dev
moodev new paygw-pix --from outra    # só quando você tem motivo
```

> Isto mudou. Enquanto `.devcontainer/` existia apenas nas branches de feature,
> o default era a branch da worktree principal — único jeito de funcionar na
> época, mas uma armadilha: a principal fica em qualquer branch, inclusive uma
> já merjeada, e a feature nasceria de uma base arbitrária sem ninguém notar.

**`main` não serve como base.** Continua sem `.devcontainer/` e está fora do
fluxo normal, onde não há PR `dev`→`main`.

O `moodev new` confere que a base tem `.devcontainer/devcontainer.json` **antes de
criar qualquer coisa** e para com a mensagem do que fazer. Uma worktree meio
provisionada é pior que nenhuma, porque parece pronta.

### A base tem que combinar com o banco

O `moodev new` resolve **duas origens diferentes**, e elas não são a mesma coisa:

| | vem de |
|---|---|
| **código** | o `--from` — por padrão `origin/dev` |
| **banco** | a worktree **offset 0**, seja `--seed clone` (cópia) ou o modo padrão sem `--new-stack` (o próprio banco dela) |

Se o offset 0 estiver servindo uma branch **à frente** da base — o caso comum
quando você está no meio de uma feature e abre outra —, o banco chega com plugin
mais novo que o código, e o Moodle recusa:

```
Cannot downgrade format_ldg from 2026090304 to 2026090301
```

Então: **olhe o `moodev ls`, veja em qual branch está o offset 0, e passe essa branch
no `--from`.**

```bash
moodev ls                                          # offset 0 → fix/format-ldg-navegador
moodev new fix/tls-porta --from fix/format-ldg-navegador
```

Esquecer não custa nada: o `moodev` compara os `version.php` que mudaram entre as duas
refs e recusa antes de criar qualquer coisa, listando os plugins e dizendo qual
`--from` usar. É só repetir o comando. A outra saída é `--new-stack --seed fresh`,
que nasce com banco vazio e por isso nem é checado.

Quando a `origin/dev` já estiver em dia com o principal — depois que a feature da
vez for merjeada —, o `--from` volta a ser dispensável.

> A comparação lê o `HEAD` do offset 0. Um bump de versão ainda não commitado lá
> já está no banco e não aparece; nesse caso o `moodev` avisa, mas segue.

---

## 9. O que nunca pode vazar para a VPS

A VPS roda **o mesmo stack de development**. O `COMPOSE_FILE` dela é:

```bash
base.yml:db.yml:dev.yml      # .github/workflows/deploy.yml
```

Ou seja, **editar `dev.yml` é editar produção**. Dois arquivos existem só para o
ambiente local e nunca entram naquela lista:

- **`local-tls.yml`** — publica a 443 do container. Na VPS quem termina TLS é o
  nginx, com certificado do certbot.
- **`local-dev.yml`** — `phpu_moodledata` (dataroot de *teste*; o caminho não
  existe na VPS e o docker o criaria como root dentro do repo de produção) e
  `uploads.ini` (sobe o limite de upload para 200 MB, contra os 100 MB do
  `defaults.env` — dobrar o limite da VPS não é efeito colateral aceitável de
  uma mudança de fluxo local).

Ao mexer em compose, verifique o que a VPS passa a ver:

```bash
cd <worktree>
COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml \
COMPOSE_ENV_FILE=.env docker compose config
```

---

## 10. A imagem: a VPS é ARM, sua máquina não

A VPS Oracle é Ampere (aarch64); quem desenvolve está em x86_64.

`leodg/ldg-courses:development` é **multi-arquitetura**: o `docker compose pull`
funciona nas duas pontas, e cada uma recebe a sua imagem. Confira com:

```bash
docker buildx imagetools inspect leodg/ldg-courses:development
#   Platform: linux/amd64
#   Platform: linux/arm64
```

### Como o CI faz isso

Cada arquitetura compila no **seu runner nativo** (`ubuntu-latest` e
`ubuntu-24.04-arm`) e empurra por digest; um job `image-manifest` funde os dois
numa tag só e **falha se faltar alguma arquitetura**, antes de o deploy rodar.

Não se usa `platforms: linux/amd64,linux/arm64` num job único de propósito —
isso poria o amd64 sob QEMU, que num build de Moodle custa dezenas de minutos.

> Nem sempre foi assim. O CI publicava só `linux/arm64`, e em amd64 o `pull`
> batia em `no matching manifest for linux/amd64`, deixando o `moodev build` como
> única fonte local da imagem.

### Quando ainda usar `moodev build`

Depois de mexer no `Dockerfile` e antes de o CI publicar, para testar a imagem
localmente:

```bash
moodev build
```

Antes de reconstruir, vale marcar a imagem que funciona, para ter para onde
voltar:

```bash
docker tag leodg/ldg-courses:development leodg/ldg-courses:backup-$(date +%F)
```

> É por isso que o CRLF da §11 passou tanto tempo escondido: enquanto o `pull`
> não funcionou em amd64, **todo** ambiente local dependia de um build local — e
> um build local com CRLF produz um `ENTRYPOINT` que não executa. Com o `pull`
> funcionando, o build local ficou raro, então a proteção do `.gitattributes`
> importa mais, não menos: quando você precisar dele, vai ser justamente numa
> hora em que não quer descobrir isso.

---

## 11. Armadilhas

**Fim de linha.** O git global tinha `core.autocrlf=true` — ajuste de Windows
numa máquina Linux, que fazia o checkout escrever **CRLF** no working tree mesmo
com LF gravado no repositório. **Corrigido em 27/08/2026** para `false`; se você
clonar noutra máquina, confira antes de suspeitar de qualquer outra coisa:

```bash
git config --get core.autocrlf     # deve estar vazio ou false
```
Script com CRLF não executa no Linux — o `moodle-entrypoint` é o `ENTRYPOINT` da
imagem, com CRLF vira `#!/bin/bash\r`, o kernel procura um interpretador com
`\r` no nome e o container morre no `exec`, sem subir.

Some com uma execução de:

```bash
git -C <worktree> ls-files --eol .devcontainer/bin/ | head
```

`.devcontainer/.gitattributes` força `eol=lf` nos arquivos que entram na imagem,
e o atributo tem precedência sobre `core.autocrlf` — vale para qualquer clone,
em qualquer máquina, sem depender de configuração do usuário.

Ele fica em `.devcontainer/` e não na raiz porque o `.gitattributes` da raiz é do
Moodle upstream, e este projeto faz merge de `upstream/MOODLE_502_STABLE` com
frequência.

**Permissão do `moodledata`.** Um CLI rodado como root dentro do container cria
diretório com dono root; o Apache roda como `www-data` e o site passa a responder
500 com `invaliddatarootpermissions`. O `moodle-entrypoint` corrige sozinho no
boot — mas o guard antigo olhava só o diretório raiz, que continuava correto, e
por isso nunca disparava. Hoje ele varre a árvore.

Se acontecer com o container no ar:

```bash
docker exec -u root <container> chown -R www-data:www-data /var/www/moodledata
```

**Rode os CLI como `1000:33`.** uid do host, grupo `www-data`. É o que `moodev shell`
e `moodev cli` já fazem. Sem isso o PHPUnit não escreve no dataroot e os arquivos
gerados ficam com dono errado no host.

**`< /dev/null` no `docker compose exec`.** Sem ele o exec consome o stdin do
shell que chamou e o comando seguinte do script morre. Já quebrou um deploy.

**Espaço em disco.** Cada feature custa ~900 MB (worktree ~600, `dbdata` ~250).
O `moodev new` recusa abaixo de 5 GB livres, e `moodev doctor` avisa antes.

**Banco à frente do código.** `Cannot downgrade <plugin> from X to Y` quer dizer
que o banco conhece uma versão de plugin que o código não tem — worktree criada de
uma base atrás da branch que o banco veio, ou troca de branch depois. O `moodev new`
recusa isso desde 03/09/2026 (seção 8); se já aconteceu, traga o código para a
altura do banco:

```bash
git -C <worktree> merge --ff-only <branch que o banco conhece>
moodev cli upgrade.php --non-interactive && moodev cli purge_caches.php
```

Aconteceu criando a `fix/theme-ldg` de `origin/dev` enquanto o principal servia
`fix/format-ldg-navegador`, um commit à frente: `format_ldg` e `theme_ldg` já
bumpados no banco, e o ambiente subiu inteiro só para morrer no último passo.

---

## 12. Encerrando uma feature

Depois do merge do PR:

```bash
moodev rm paygw-pix
```

Remove a worktree e a **branch que ela tem de fato** — lida da worktree, não
deduzida do nome da pasta, para que uma `fix/…` não fique órfã.

O resto depende do estado da worktree, que o `moodev ls` mostra na coluna `OFFSET`:

| | Stack próprio (`≥ 1`) | Servida agora (`0`) | Sem stack (`-`) |
|---|---|---|---|
| Containers e volumes | derrubados | — | **intocados** |
| `~/localhost/ldg-data/<nome>/` | apagado | não existe | não existe |
| Dados do stack principal | — | **preservados** | **preservados** |
| Stack principal | **intocado** | movido para `dev` | **intocado** |

**Só a worktree servida move o stack.** É obrigatório: o código montado no
container vai sumir do disco. Nos outros dois casos o principal segue rodando o
que rodava — remover uma worktree não é motivo para interromper o trabalho em
outra.

A distinção de dados não é cosmética. A linha de registro de uma worktree sem
stack aponta para o `moodledata` e o `dbdata` **do principal** — foi ele que a
serviu. Apagar por ali destruiria o banco principal a partir de um comando que
parece local. O `moodev rm` só apaga dados sob `~/localhost/ldg-data/`, e só derruba
stack quando existe um próprio.

Antes de remover, avisa se houver alteração não commitada ou commit da **própria
branch** fora do remoto, e pede o nome da worktree por extenso (`--force` pula).

### A worktree de repouso

`dev` é para onde o stack volta quando a worktree servida é removida, e por isso
**o `moodev rm` a recusa**. Ela é a escolha natural: é a branch de integração, tem
`.devcontainer/`, e é de onde o `moodev new` ramifica — o repouso é o mesmo ponto de
partida.

`main` não serve: não tem `.devcontainer/` e está 108 commits atrás, então o
stack não subiria nela.
