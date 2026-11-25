> **Situação:** executado · **Início:** 2026-08-26
> **Origem:** `~/.claude/plans/wobbly-gathering-sonnet.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** entregue nos PRs #50, #51 e #52. O registro do ciclo, com o que se aprendeu
> depois, é o [2026-08-26-cf-fluxo-de-worktrees.md](2026-08-26-cf-fluxo-de-worktrees.md) —
> este arquivo é o **plano**, aquele é o registro.

# Fluxo de worktrees do courses-free: um comando para nascer uma feature

## Contexto

Hoje, começar uma feature no `courses-free` custa uma sequência manual e frágil.
O `git worktree add` entrega só o código versionado; tudo que faz o ambiente
funcionar está no `.gitignore` e precisa ser copiado na mão — o próprio
`README.md` documenta isso na seção "Trocando de worktree", com um `cp -a` de
cinco linhas mais um `sed` no `.env`.

Quatro travas concretas, verificadas no ambiente:

1. **Stack único por construção.** `.devcontainer/compose/base.yml` fixa
   `name: courses-free`, e as portas em `.env` são literais (8080/8443/3307/9004).
   `MOODLE_HOST_DATA` e `DB_HOST_DATA` apontam para uma pasta só. Subir outra
   worktree não cria um segundo ambiente: **recria o mesmo container** apontando
   para outro código.
2. **Bootstrap manual.** Gitignored e obrigatórios: `/.env`, `/config.php`,
   `.devcontainer/secrets/`, `.devcontainer/certs/develop-local.{crt,key}`,
   `.devcontainer/env/{db,dev}.env`.
3. **Branch base sem ambiente.** `.devcontainer/` existe apenas em
   `feature/paygw-mercadopago` e `feature/leodg-academy-theme`. Ramificar de
   `dev`/`main` — que é o que o README manda fazer — produz um worktree **sem
   ambiente nenhum**.
4. **Rebuild do VS Code.** `devcontainer.json` declara três `features`
   (git, common-utils, shell-history), o que obriga a CLI do Dev Containers a
   construir uma **imagem derivada por pasta**; e inclui `build-dev.yml`, cujo
   `context: ../../` muda de caminho a cada worktree. O arquivo ainda está
   estagnado do projeto anterior: nome "Ivana Academy", mount de
   `../../moodledata-academy` (**não existe** no disco) e sem `local-tls.yml`,
   o que derruba o HTTPS que o `MOODLE_URL=https://localhost:8443` exige.

**Resultado pretendido:** `cf new paygw-pix` cria a worktree, provisiona o
ambiente, clona o banco, sobe um stack isolado e abre o VS Code já dentro do
container — sem rebuild, sem tocar no stack principal que está rodando.

### Decisões tomadas com o usuário

- **Isolamento híbrido.** O stack principal (`courses-free`, hoje apontando para
  `feature-marketplace`) continua exatamente como está — mesmo nome, mesmas
  portas, mesmos dados. Stacks adicionais só nascem quando pedidos.
- **Corrigir o `devcontainer.json`** para que "Reopen in Container" reaproveite
  o container, em vez de abandonar o devcontainer.
- **Seed por clone**: feature nova nasce com um `mysqldump` do banco principal
  mais uma cópia do `moodledata`.

### Restrições do host (moldam o dimensionamento)

- `/home` com **33 GB livres** (94% em uso). Worktree ≈ 600 MB, `dbdata` ≈ 251 MB,
  `moodledata` ≈ 6 MB ⇒ **~900 MB por feature isolada**. Cabem 2–3 confortavelmente;
  o `cf new` deve recusar abaixo de um piso de espaço livre.
- 30 GB de RAM, ~16 GB disponíveis. Limites atuais: 2 GB (moodle) + 1 GB (db)
  por stack.

---

## Mudanças

### 1. `.devcontainer/compose/base.yml` — nome de projeto parametrizável

- `name: courses-free` → `name: ${STACK_NAME:-courses-free}`.
  O default preserva o stack atual sem nenhuma ação. Variável própria em vez de
  `COMPOSE_PROJECT_NAME` para deixar explícito no arquivo de onde vem o nome.
- `composer_cache`: dar `name: courses-free_composer_cache` fixo, para os stacks
  **compartilharem** o cache do Composer em vez de cada um baixar o seu. O
  comentário atual justifica evitar nome fixo por colisão com outro projeto — o
  prefixo `courses-free_` resolve isso e mantém a intenção.

### 2. `.devcontainer/compose/dev.yml` — absorver o que o build traz

Ao tirar `build-dev.yml` da lista do devcontainer (passo 4), três coisas se
perderiam. Movê-las para `dev.yml`, onde valem para qualquer forma de subir:

- volume `../../phpu_moodledata:/var/www/phpu_moodledata` (por worktree, correto)
- volume `../php/uploads.ini:/usr/local/etc/php/conf.d/uploads.ini`
- `XDEBUG_CONFIG: client_host=host.docker.internal`

`build-dev.yml` continua existindo para quando você quiser **reconstruir** a
imagem (`cf build`); só deixa de ser exigido no caminho normal.

### 3. `.devcontainer/build/moodle.Dockerfile` — assar as features (stage `development`)

Isto é o que mata o rebuild por pasta. As três features do `devcontainer.json`
custam quase nada de embutir:

- `features/git:1` — **redundante**: o stage `development` já instala `git` e
  `git-man` (linhas ~186-200). Só remover do JSON.
- `features/common-utils:2` — instala zsh/oh-my-zsh e mexe em usuário. Com
  `remoteUser: www-data` e `terminal.integrated.defaultProfile.linux: bash` já
  fixado no JSON, não agrega. Remover.
- `dev-container-features/shell-history:0` — replicar com duas linhas:
  `ENV HISTFILE=/commandhistory/.bash_history` mais criação de
  `/commandhistory` com dono `www-data`. O histórico passa a viver num volume
  nomeado por stack (passo 4), substituindo o bind em
  `../../moodle-academy-bashhistory`, que é do projeto antigo.

Apagar também `.devcontainer/devcontainer-lock.json` (só existe por causa das features).

### 4. `.devcontainer/devcontainer.json` — reescrever

```jsonc
{
  "name": "courses-free — ${localWorkspaceFolderBasename}",
  "dockerComposeFile": ["./compose/base.yml", "./compose/db.yml",
                        "./compose/dev.yml", "./compose/local-tls.yml"],
  "service": "moodle",
  "runServices": ["moodle", "db"],
  "workspaceFolder": "/var/www/html",
  "remoteUser": "www-data",
  "shutdownAction": "none",
  ...
}
```

Pontos que importam:

- **sem `features`** (assadas no passo 3) e **sem `build-dev.yml`** (usa a imagem
  `leodg/courses-free:development` já publicada) ⇒ nenhuma imagem derivada por pasta.
- `local-tls.yml` **entra** na lista — hoje falta, e abrir pelo VS Code perde a 443.
- `shutdownAction: "none"` — fechar a janela não derruba o stack.
- mounts: remover `../../moodledata-academy` (caminho inexistente) e
  `../../moodle-academy-bashhistory`; manter `.gitconfig` e `.ssh`.
- extensões e `containerEnv` ficam como estão.

### 5. `.devcontainer/bin/cf` — o script (peça central, novo)

Bash, versionado na branch de integração, exposto por symlink em `~/.local/bin/cf`.
Estado em `~/localhost/gitworktree-bare-moodle/.cf/registry.tsv`
(`worktree • offset • stack • branch`), fora de qualquer worktree.

**Alocação de portas** — offset inteiro por worktree, porta = base + offset×10:

| offset | worktree | HTTP | HTTPS | DB | Xdebug |
|---|---|---|---|---|---|
| 0 | principal (`feature-marketplace`) | 8080 | 8443 | 3307 | 9004 |
| 1 | primeira feature | 8090 | 8453 | 3317 | 9014 |
| 2 | segunda feature | 8100 | 8463 | 3327 | 9024 |

Antes de gravar, conferir com `ss -ltn` que as quatro estão livres; se não,
avançar o offset.

**Comandos:**

- `cf new <nome> [--from <branch>] [--seed clone|fresh|share] [--no-code]`
  1. valida espaço livre (piso de 5 GB) e existência do nome
  2. `git worktree add <nome> -b feature/<nome> <base>`
  3. **falha cedo e explícito** se a base não tiver `.devcontainer/` (ver
     "Pendência" abaixo); default de `--from` é a branch da worktree principal
  4. copia os gitignored da worktree de origem: `.devcontainer/secrets/`,
     `certs/develop-local.{crt,key}`, `env/{db,dev}.env`, `config.php`
  5. **gera** `/.env` (não `sed`): `STACK_NAME`, `MOODLE_HOST_WWWROOT`,
     portas do offset, `MOODLE_HOST_DATA`/`DB_HOST_DATA` em
     `~/localhost/cf-data/<nome>/{moodledata,dbdata}`
  6. corrige no `dev.env` copiado: `MOODLE_URL=https://localhost:<https>` e
     `MOODLE_HOST_WWWROOT` (o arquivo atual aponta para `feature-infra-vps`,
     worktree que **não existe mais** — bug latente que isto elimina)
  7. seed (abaixo)
  8. `cf up <nome>` e `cf code <nome>`
- `cf up|down|restart [<wt>]` — monta `COMPOSE_FILE`/`COMPOSE_ENV_FILE` e sobe.
  Prefere `devcontainer up --workspace-folder <wt>` quando a CLI existir
  (ver passo 6); fallback `docker compose up -d`.
- `cf ls` — worktree, branch, stack, portas, status, URL clicável
- `cf code [<wt>]` — abre o VS Code já dentro do container
- `cf shell [<wt>]` / `cf logs [<wt>]` / `cf cli <script.php>`
  (atalho para `admin/cli/upgrade.php`, `purge_caches.php`)
- `cf rm <wt>` — `compose down -v`, `git worktree remove`, apaga
  `~/localhost/cf-data/<wt>`, libera o offset. Pede confirmação; **recusa** o
  offset 0.
- `cf build` — rebuild da imagem `development` com `build-dev.yml`
- `cf doctor` — portas livres, `.env` coerente com o registry, validade do cert,
  worktrees órfãs no registry, espaço em disco

**Seed `clone`** (default):
```
mariadb-dump do stack de origem  →  restaura no db do novo stack
cp -a moodledata, removendo cache/ localcache/ sessions/ temp/ trashdir/
admin/cli/upgrade.php --non-interactive   (a branch nova pode trazer plugin)
admin/cli/purge_caches.php
```
`fresh` roda `install_database.php`; `share` reaponta para os dados do principal
(útil para inspeção rápida, com aviso de que os dois stacks disputam o schema).

**Certificado:** o par mkcert atual cobre `courses.leodg.dev localhost 127.0.0.1`,
então `https://localhost:<porta>` funciona em todos os stacks com o mesmo
certificado, sem `sudo`. Um host por feature (`pix.courses.leodg.dev`) exigiria
mexer em `/etc/hosts` — fica de fora.

### 6. Instalar a CLI do Dev Containers

`npm i -g @devcontainers/cli` (Node 22 já presente; a CLI **não** está instalada).

É ela que fecha o ciclo: se o `cf up` sobe com `docker compose` puro e você
depois faz "Reopen in Container", a extensão gera seus próprios arquivos de
override e **recria** os containers. Subindo pela mesma CLI que o VS Code usa,
o container criado é bit a bit o que ele espera, e a janela apenas se conecta.

### 7. Documentação

- `README.md`: substituir a seção "Trocando de worktree" (o `cp -a` + `sed`
  manual) pela tabela de comandos do `cf`, e corrigir o Passo 2 do "Fluxo de
  Trabalho" para `cf new`.
- `.env.example`: documentar `STACK_NAME` e o esquema de offset de portas.
- `CLAUDE.md`: um parágrafo curto sobre o `cf`, já que o arquivo é carregado a
  cada sessão de agente.

---

## Pendência que este plano não resolve sozinho

`dev` — a branch de integração segundo o README — **não tem `.devcontainer/`**,
e está 106 commits atrás de `origin/dev`. Enquanto isso for verdade, `cf new`
só funciona ramificando de `feature/paygw-mercadopago` ou
`feature/leodg-academy-theme`.

O certo é promover a infraestrutura (`.devcontainer/`, `.env.example`, `cf`)
para `dev`, num PR próprio. Isso mexe no histórico do projeto e é decisão sua,
então **não está incluído aqui**: o `cf new` vai falhar com mensagem explícita
apontando esta pendência, em vez de gerar um worktree meio configurado.

---

## Verificação

O critério é: **o stack principal não pode sentir nada.**

1. **Baseline** — anotar `docker ps` do `courses-free` e abrir
   `https://localhost:8443`, confirmando que o site responde.
2. **Não-regressão do principal** — após os passos 1-4, `cf up` na worktree
   principal. Conferir: nome `courses-free`, portas 8080/8443/3307/9004,
   binds ainda em `feature-marketplace` / `moodledata-courses-free` /
   `dbdata-courses-free`, healthcheck do db passando, site respondendo.
3. **Sem rebuild** — `docker images leodg/courses-free` antes e depois de
   "Reopen in Container": o `IMAGE ID` e o `CREATED` **não** mudam, e nenhuma
   imagem `vsc-*` nova aparece. Este é o teste que valida a decisão do passo 3.
4. **Feature nova** — `cf new teste-fluxo`. Esperado: worktree criada, stack
   `courses-free-teste-fluxo` de pé em 8090/8453, `https://localhost:8453`
   abrindo com o banco clonado (mesmos cursos do principal), VS Code aberto
   dentro do container.
5. **Paralelismo real** — com os dois de pé, `docker ps` mostra 4 containers;
   editar um arquivo em cada worktree e confirmar que a mudança aparece **só**
   no site correspondente; criar um curso num e confirmar que **não** aparece
   no outro (prova de que os bancos são separados).
6. **Xdebug** — breakpoint no VS Code da feature nova, batendo na 9014 e não na 9004.
7. **Limpeza** — `cf rm teste-fluxo` e confirmar: containers e volumes sumiram,
   `~/localhost/cf-data/teste-fluxo` apagado, offset liberado no registry, e o
   stack principal **ainda de pé e respondendo**.
8. `cf doctor` sem alertas ao final.
