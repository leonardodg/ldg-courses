# Rename do projeto: `courses-free` → `ldg-courses`

> Ao executar, renomear este arquivo para `2026-09-11-rename-para-ldg-courses.md`,
> que é a convenção do diretório.

---

## Onde a execução divergiu do plano

Registrado em 11/09/2026, durante a execução.

**A migração automática do `registry.tsv` foi descartada.** O plano previa uma
função no `moodev` para reescrever a coluna `stack`, seguindo o precedente da
migração `.cf/` → `.moodev/`. Descartada: o registro é **um arquivo de quatro
linhas, numa máquina só**, e eu já ia editá-lo à mão no cutover. Somar uma função
a um script de 1600 linhas para poupar uma edição manual é exatamente a automação
cara para tarefa única que este projeto já pagou uma vez.

**`LDG_DATA_ROOT` não foi criada.** `MOODEV_DATA_ROOT` já é o nome certo da
variável; acrescentar um sinônimo não ganha nada. Só o default mudou, para
`~/localhost/ldg-data`, e `CF_DATA_ROOT` segue aceito como legado.

**A guarda de regressão busca identificador, não a palavra solta.** Casar
`courses-free` cru reprovaria a etimologia do `cf`, a nota do `CF_DATA_ROOT` e o
registro do sandbox do Asaas — todas verdadeiras. Um passo que acusa linha
correta vira ruído, e ruído acaba silenciado. A primeira versão, escrita por
extenso, **casou consigo mesma**; o padrão passou a ser montado a partir de
`$ANTIGO`.

**Etapa 7, nova, a pedido:** remover a guarda do `deploy.yml` depois que tudo
estiver verde e estável. Ela é andaime da migração, não permanente. O trade-off,
para decidir na hora: o valor dela é justamente de longo prazo, contra alguém
copiar um comando de um documento antigo daqui a meses — o custo é ~2 s de CI.

**Um bug anterior ao rename apareceu na verificação.** `moodev new --no-code`
saía com código 1 depois de funcionar: a última linha de `cmd_new` era uma lista
`&&` cujo teste dá falso sem `--code`. Corrigido no mesmo PR.

**O stack do `paygw-pagarme` ficou FORA DO AR de propósito.** O `.env` dele já
aponta para os nomes novos, mas a worktree está 55 commits atrás de `dev` e o
`base.yml` dela ainda tem o default antigo. Subir agora recriaria o volume
`courses-free_composer_cache`. Ele precisa de rebase em `dev` **depois** do merge,
antes de voltar ao ar.

## Contexto

O projeto nasceu como `courses-free` e o nome está espalhado em três eixos
independentes de infraestrutura: o nome do stack compose, a imagem no Docker Hub e
os caminhos de dados no host. O objetivo é trocar tudo para `ldg-courses`, alinhando
repositório, imagem, stack, tooling e documentação com a marca LDG.

A medição abaixo mostra que o rename é **de infraestrutura, não de aplicação**. Isso
muda o tamanho do risco: não há migração de banco, não há bump de versão de plugin,
não há `xmldb` a tocar.

---

## O tamanho do estrago (medido, não estimado)

**36 arquivos, ~207 ocorrências** na worktree `dev`, fora `docs/ai-plans/` (14 arquivos,
que ficam como histórico, por decisão sua).

Atenção: o `grep` desta máquina respeita o `.gitignore`. Uma varredura ingênua **perde**
`.env` e `.devcontainer/env/dev.env`, que são justamente os arquivos vivos. Use
`command grep` ou `grep --no-ignore` ao conferir.

### O que NÃO é afetado (a boa notícia)

- Nenhum componente Moodle, namespace PHP, tabela, capability ou lang string usa o nome.
  Todos os hits em `public/` são comandos `docker exec` copiáveis dentro de READMEs.
- O nome do banco é `moodle` — não muda.
- Não existe imagem `leodg/courses-free` no Docker local. Os containers rodam imagens
  derivadas do VS Code (`vsc-<worktree>-<hash>-uid`), que dependem do **nome da pasta da
  worktree**, não do nome do projeto. Ou seja: **não há 30 GB para rebaixar localmente.**
- Na VPS, o `docker pull` da imagem nova reaproveita as camadas já em disco (são
  endereçadas por conteúdo); só o manifesto muda. O pull é rápido.

### Os três eixos

**1. Nome do stack compose** — o de maior raio de explosão.
- `.devcontainer/compose/base.yml:23` — `name: ${STACK_NAME:-courses-free}` — **fonte única da verdade**
- `.devcontainer/compose/base.yml:38` e `build-dev.yml:41` — `name: courses-free_composer_cache`
  (volume de nome fixo; os dois **têm que mudar juntos**, como o próprio comentário avisa)
- `.devcontainer/compose/behat.yml:27` — `container_name: ${STACK_NAME:-courses-free}-selenium`
- `.devcontainer/bin/moodev:196` — grava o literal `courses-free` no registry
- `.devcontainer/bin/moodev:851` — `local stack="courses-free-$name"`
- `.devcontainer/bin/moodev:170` — `docker inspect courses-free-moodle-1` (nome de container **hardcoded**)
- `.devcontainer/devcontainer.json:82` — volume `courses-free-history-${localWorkspaceFolderBasename}`
- `.moodev/registry.tsv` — estado da máquina, fora de qualquer worktree

**2. Imagem Docker Hub** — `leodg/courses-free:development`
- `compose/dev.yml:10` (fallback), `compose/build-dev.yml:18` (`cache_from`)
- `.env`, `.env.example:40`, `.devcontainer/env/dev.env:62`, `dev.env.example:26`
- `.devcontainer/bin/moodev:623` e `:923` (os dois heredocs que **geram** `.env`)
- `.github/workflows/deploy.yml:406, 474, 535`

**3. Caminhos de dados no host** — ~1 GB, fora do repo, invisível para find/replace
- `/home/leodg/localhost/moodledata-courses-free` (215M) — moodledata vivo
- `/home/leodg/localhost/dbdata-courses-free` (251M) — MariaDB vivo
- `/home/leodg/localhost/cf-data/` (470M) — dados dos stacks secundários (`MOODEV_DATA_ROOT`)
- `/home/leodg/localhost/backups-courses-free` (89M)
- Na VPS: `vars.VPS_PATH = /home/ubuntu/courses-free`

### Resto

- **nginx**: upstream `courses_free` em `courses.leodg.dev.conf:17` e `:105`, e
  `vendor-domain.conf.template:61`. Renomear **em par** ou o nginx não sobe.
- **Identidade do site Moodle**: `deploy.yml:748-749` e `.devcontainer/readme.md:134` —
  só rodam em banco vazio, então não tocam o site atual da VPS.
- **Pacotes de tooling**: `.devcontainer/devtools/composer.json:2`, `devtools/package.json:2`
- **CI**: `deploy.yml:47` — grupo de concorrência `deploy-courses-free-…`
- **Docs e comandos copiáveis**: 35 ocorrências de `courses-free-moodle-1` em `CLAUDE.md`,
  `docs/dev/*`, `docs/architecture/*`, `docs/legal/*`, `docs/man/moodev.1` e 6 READMEs de plugin.

### Estado vivo do Docker (a limpar)

Containers `courses-free-{moodle,db}-1`, `courses-free-selenium`,
`courses-free-paygw-pagarme-{moodle,db}-1`, `courses-free-paygw-pagarme-selenium`.
Redes `courses-free_moodle_network`, `courses-free-paygw-pagarme_moodle_network`.
**26 volumes** com o nome: `courses-free_composer_cache`, `courses-free_dbdata` (legado,
de antes dos bind mounts) e ~24 volumes de histórico de shell de worktrees já removidas.

---

## Decisões tomadas

| Decisão | Escolha |
|---|---|
| Dados no host | **Renomear tudo com `mv`** |
| Cutover da VPS | **Você faz por SSH**, antes do deploy |
| Domínio `courses.leodg.dev` | **Mantém.** Só o upstream nginx interno é renomeado |
| Onde trabalhar | Branch nova a partir de `dev`, **aproveitando suas edições pendentes** |
| `docs/ai-plans/*.md` | Fora do rename (histórico) |

### Fora de escopo, de propósito

- `docs/data-validation/asaas-sandbox.md:383` e `docs/data-validation/scripts/provar-split-asaas.py:136,51`
  — `Moodle CoursesFree` é o nome **real** da conta/webhook no sandbox do Asaas, e a
  medição registrada. Renomear no repo sem renomear no Asaas transforma prova em ficção.
  Fica como está; se você renomear no painel do Asaas, aí sim o repo acompanha.
- `docs/private/pagarme.txt` — log de sessão, mesmo tratamento de `ai-plans`.
- `~/.local/bin/cf` — symlink legado que aponta para `moodev`. Não depende do nome; fica.

---

## O que só você pode fazer

| # | Ação | Onde | Quando |
|---|---|---|---|
| A | Renomear o repo `courses-free` → `ldg-courses` | GitHub → Settings → Repository name | Etapa 4 |
| B | **Criar** o repositório `leodg/ldg-courses` no Docker Hub (Docker Hub **não tem rename**; o antigo fica como arquivo) | hub.docker.com | Antes da Etapa 5 |
| C | Conferir se o `DOCKER_TOKEN` tem permissão de push no repo novo (se for PAT com escopo por repositório, precisa incluir o novo) | Docker Hub → Account settings → Personal access tokens | Antes da Etapa 5 |
| D | Trocar a variable `VPS_PATH` de `/home/ubuntu/courses-free` para `/home/ubuntu/ldg-courses` | GitHub → Settings → Environments → `development` | Etapa 5, estágio 2 |
| E | Cutover por SSH na VPS (comandos prontos na Etapa 5) | VPS | Etapa 5 |
| F | Ajustar nome/nome curto do site em produção (`Administração → Página inicial`) | Moodle da VPS | Depois da Etapa 5 |

O resto eu faço. As variables do ambiente `development` hoje são:
`DOCKER_USERNAME=leodg`, `MOODLE_DBNAME=moodle`, `MOODLE_URL=https://courses.leodg.dev`,
`VPS_PATH=/home/ubuntu/courses-free`. Secrets e variables **sobrevivem** ao rename do repo,
e o GitHub mantém redirect para o remote git — nada quebra por causa do rename em si.

---

## Etapas

### Etapa 0 — Preservar o que você já fez, e abrir a branch

A worktree `fix-check-and-issues` tem duas edições **não commitadas** que já começam este
rename: `.devcontainer/readme.md` e `.github/workflows/deploy.yml`, trocando para
`--fullname="LDG Technology & Courses"`. O `--shortname` está divergente entre os dois
arquivos (`LDG-courses` vs `LDG-Courses`) — padronizo em **`LDG-Courses`**, que é o que
está no workflow, o arquivo que de fato executa.

1. Salvar o diff dessas duas edições em `/tmp/.../scratchpad/edicoes-fix-check.patch`
   **antes** de qualquer coisa — a worktree vai ser destruída.
2. `git worktree add` de `rename-ldg-courses`, branch `feature/rename-ldg-courses`, a
   partir de `dev`. Sem `moodev new` — o `moodev` é justamente o que está sendo renomeado.
3. Aplicar o patch como primeiro commit, com o shortname padronizado.

### Etapa 1 — Editar o código (sem tocar em infra)

Tudo em `feature/rename-ldg-courses`. Esta etapa é puramente textual: nenhum container
precisa estar de pé.

**Eixo 1 — stack compose**
- `base.yml:23` → `name: ${STACK_NAME:-ldg-courses}`
- `base.yml:38` **e** `build-dev.yml:41` → `name: ldg-courses_composer_cache`
- `moodev:196` → grava `ldg-courses`; `moodev:851` → `stack="ldg-courses-$name"`
- `moodev:170` → `docker inspect ldg-courses-moodle-1`
- `devcontainer.json:82` → `ldg-courses-history-${localWorkspaceFolderBasename}`
- `devcontainer.json:28` → `"name": "ldg-courses — ${localWorkspaceFolderBasename}"`
- Adicionar em `moodev` uma migração de registry que reescreve `courses-free` →
  `ldg-courses` na coluna `stack` do `registry.tsv`, **seguindo o precedente já existente**
  em `moodev:151-157` (a migração `.cf/` → `.moodev/`). Isso faz o rename se auto-curar
  nas outras worktrees, em vez de exigir edição manual em cada máquina.
- `moodev doctor` (`moodev:1486-1495`) continua válido: offset 0 segue **sem** `STACK_NAME`,
  herdando o novo default. Conferir que a checagem não menciona o nome antigo.

**Eixo 2 — imagem**
- `dev.yml:10`, `build-dev.yml:18`, `.env.example:40`, `dev.env.example:26`,
  `moodev:623`, `moodev:923` → `leodg/ldg-courses:development`
- `deploy.yml:406, 474, 535` → `${{ vars.DOCKER_USERNAME }}/ldg-courses`

**Eixo 3 — caminhos (só os templates; os `.env` vivos são regenerados na Etapa 2)**
- `.env.example:28-30` → `/home/ubuntu/ldg-courses/{repo,moodledata,dbdata}`
- `moodev:53` → `MOODEV_DATA_ROOT:-${LDG_DATA_ROOT:-$HOME/localhost/ldg-data}`,
  mantendo `CF_DATA_ROOT` como fallback aceito para não quebrar quem já exporta
- `moodev:138, 1284-1285` → comentários de segurança apontando os caminhos novos.
  **Não apagar esses comentários**: eles são a guarda contra `moodev rm` destruir o banco
  principal a partir de uma worktree secundária.

**nginx** — `courses.leodg.dev.conf:17` e `:105`, `vendor-domain.conf.template:61`:
`courses_free` → `ldg_courses`. Os três na mesma edição, ou o nginx não sobe.

**CI** — `deploy.yml:47` grupo de concorrência; `deploy.yml:748-749` fullname/shortname.

**Tooling** — `devtools/composer.json:2` → `leodg/ldg-courses-devtools`;
`devtools/package.json:2` → `ldg-courses-devtools`.

**Docs** — os 35 `courses-free-moodle-1` → `ldg-courses-moodle-1` em `CLAUDE.md`,
`docs/dev/*`, `docs/architecture/estrutura-do-repositorio.md`, `docs/legal/mapa-de-dados-pessoais.md`,
`docs/man/moodev.1` e os 6 READMEs de plugin. Mais as URLs do GitHub em
`estrutura-do-repositorio.md:12,140` e `estrutura-worktrees.md:75`.
`docs/dev/moodev.md:8,85` e `docs/dev/README.md:7` trazem a etimologia ("chamava-se `cf`,
de *courses-free*") — reescrever como nota histórica, não apagar.

**Limpeza de resíduo do projeto anterior, no mesmo passe** (achado durante a medição):
`build/moodle.Dockerfile:65-68` ainda tem labels OCI de `Ivana Academy - Moodle 4.5`, e
`env/defaults.env.example:8` ainda aponta `MOODLE_URL=https://develop.ivana.academy`.

### Etapa 2 — Cutover local: derrubar, renomear, limpar, subir

Ordem importa. O erro aqui é subir o stack novo com o diretório de dados errado: o Moodle
acha banco vazio e **tenta reinstalar por cima**.

1. Derrubar os dois stacks: `moodev down` em cada worktree, e conferir com
   `docker ps -a | grep courses-free` que **nada** restou segurando bind mount.
2. `mv` dos quatro diretórios:
   `moodledata-courses-free` → `moodledata-ldg-courses`, `dbdata-courses-free` → `dbdata-ldg-courses`,
   `backups-courses-free` → `backups-ldg-courses`, `cf-data` → `ldg-data`.
3. Reescrever `.moodev/registry.tsv` (4 linhas): coluna `stack` e as duas colunas de caminho.
4. Limpar os órfãos: os ~24 volumes `*_courses-free-history-*` (histórico de shell,
   descartável), `courses-free_composer_cache` (repopula sozinho) e `courses-free_dbdata`
   — este último **conferir com `docker volume inspect` que está órfão** antes de remover;
   é legado de antes dos bind mounts. Redes `courses-free_*` depois dos containers.
   **Não tocar** nos volumes `ivana-academy_*` e `moodle_composer_cache` sem sua confirmação:
   são de outro projeto na mesma máquina.
5. `moodev use rename-ldg-courses` — regenera o `.env` já com os valores novos.
6. `moodev up` (ou rebuild do devcontainer). O container nasce `ldg-courses-moodle-1`.
7. Destruir a worktree `fix-check-and-issues`, como você pediu.

### Etapa 3 — Provar que voltou a funcionar, local

Ver a matriz de verificação no fim.

### Etapa 4 — GitHub

1. Você renomeia o repo (ação **A**).
2. Eu rodo `git remote set-url origin git@github.com:leonardodg/ldg-courses.git`.
   O repo é bare compartilhado, então **um** comando cobre todas as worktrees.
3. Abrir o PR. Conferir que os jobs de build/lint passam **sem** o job de deploy
   (em PR o deploy não roda) — é a prova barata de que o build da imagem nova funciona
   antes de qualquer coisa irreversível.

### Etapa 5 — VPS, em dois estágios

Dois estágios de propósito: o estágio 1 tem janela de indisponibilidade de ~1-2 min; fazer
o `mv` do diretório junto multiplicaria isso pelo tempo de build da imagem (~10-15 min).

**Pré-requisito:** ações **B** e **C** feitas (repo no Docker Hub existe e o token empurra nele).

**Estágio 1 — nome do stack e imagem (junto com o merge)**

1. Fazer o merge do PR e acompanhar com `gh run watch`.
2. Quando o job `deploy` **começar** (depois de `image` e `image-manifest`, que são os
   demorados), você entra na VPS e derruba o stack antigo:
   ```bash
   cd /home/ubuntu/courses-free/repo
   export COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml
   docker compose -p courses-free down --remove-orphans   # SEM -v: o dado é bind mount e sobrevive
   docker ps -a | grep courses-free                        # tem que sair vazio
   ```
   Sem isso o container velho segura `127.0.0.1:8095` e `3307`, e o `up` do projeto novo
   falha com *port is already allocated* — o site fica servindo a versão antiga e o deploy
   "falha com o site no ar", exatamente o modo de falha que o comentário em `deploy.yml:696`
   descreve do incidente de 25/08.
3. O deploy segue: escreve o `.env`, `docker compose pull`, `up -d --wait` sob o projeto
   `ldg-courses`, `upgrade.php` (o banco já existe, então **não** cai no ramo de instalação),
   instala o nginx com o upstream renomeado e faz o smoke test de HTTP 200 em
   `https://courses.leodg.dev/login/index.php`.

**Estágio 2 — caminho na VPS (depois, com calma, sem CI)**

```bash
cd /home/ubuntu
docker compose -p ldg-courses -f ldg-courses/repo/... down   # ou: cd ldg-courses/repo && docker compose down
mv courses-free ldg-courses
cd ldg-courses/repo
# ajustar as 3 linhas de caminho no .env: MOODLE_HOST_WWWROOT, MOODLE_HOST_DATA, DB_HOST_DATA
docker compose up -d --wait
```
Depois disso, você troca a variable `VPS_PATH` (ação **D**) para que os **próximos** deploys
façam rsync no caminho novo. Janela: ~1 min, sem build.

### Etapa 6 — Guarda de regressão

Um rename "pronto" que deixa o nome antigo voltar pela próxima cópia-e-cola não está pronto.
Adicionar ao `deploy.yml`, junto do lint, um passo que falha se `courses-free`/`courses_free`
reaparecer fora de `docs/ai-plans/` e `docs/data-validation/` (as duas exclusões decididas
acima). O passo tem que ser **provado falhando** antes de entrar: rodar com uma ocorrência
plantada, ver vermelho, remover, ver verde.

---

## Verificação

Nada aqui é "deve funcionar". Cada linha é um comando com saída conferida.

**Infra local**
- `docker ps` → `ldg-courses-moodle-1` e `ldg-courses-db-1` **healthy**; nenhum `courses-free-*`
- `docker volume ls | grep courses-free` → vazio
- `moodev ls` → tabela coerente; `moodev doctor` → sem avisos
- `moodev new --new-stack` numa worktree descartável → stack `ldg-courses-<nome>` nas portas
  do offset; `moodev rm` nela → **confirmar que os dados do principal continuam lá**
  (é a guarda de `moodev:1284-1285`, e é o pior bug possível desta mudança)
- `moodev build` → imagem sai como `leodg/ldg-courses:development`

**Aplicação local**
- Site responde em `https://courses.leodg.dev:8443` e faz login
- PHPUnit dos plugins do projeto — **com `RENDERER_TARGET_GENERAL`**, sem isso o teste de
  tema passa verificando nada
- `phpcs` nos plugins alterados
- Behat: `moodev full` (senão os `@javascript` morrem procurando `localhost:4444`) e
  `--profile=chrome`
- Conferir no navegador, contra a referência: home/landing, portal do aluno (`format_ldg`),
  `local_partners` — nos dois modos de cor. Nenhum deles deveria mudar, e é exatamente por
  isso que vale olhar: mudança aqui significa que o rename pegou algo que não devia.

**Comandos da documentação**
- Executar de verdade os `docker exec … ldg-courses-moodle-1 …` de `CLAUDE.md:166-180`,
  `docs/dev/behat.md`, `docs/dev/padrao-de-implementacao.md:354-372` e dos 6 READMEs de
  plugin. São comandos copiáveis: se não rodam, a doc está mentindo.
- `command grep -rn "courses-free\|courses_free\|CoursesFree" --exclude-dir=ai-plans .`
  → só os hits do Asaas, deliberadamente preservados

**VPS**
- `docker compose ls` na VPS → projeto `ldg-courses` running(2), nenhum `courses-free`
- `curl -sS -o /dev/null -w '%{http_code}' https://courses.leodg.dev/login/index.php` → `200`
- `sudo nginx -t` → ok, com o upstream `ldg_courses`
- Login no site, e uma compra de ponta a ponta no gateway do Asaas em sandbox — é o fluxo que
  atravessa nginx, container, banco e integração externa de uma vez só
- **Auditoria se mede em produção**, não em localhost: o smoke test tem que ser contra
  `courses.leodg.dev`

---

## Rollback

- **Local**: `mv` de volta nos quatro diretórios, `git checkout dev`, `moodev use dev`.
  Os dados nunca são apagados em nenhum passo — só movidos.
- **VPS, estágio 1**: reverter o merge e re-rodar o workflow do commit anterior; o
  `docker compose -p courses-free up -d` volta o stack antigo, já que o `down` não levou
  volume nem bind mount.
- **VPS, estágio 2**: `mv ldg-courses courses-free`, desfazer as 3 linhas do `.env`,
  `docker compose up -d`, reverter a variable `VPS_PATH`.
- **Docker Hub**: o repositório `leodg/courses-free` continua publicado com a tag
  `:development` apontando para a imagem atual. Não apagar até a VPS estar verde por alguns dias.
