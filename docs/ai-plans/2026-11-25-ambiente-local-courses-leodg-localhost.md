# Ambiente local espelho da producao: `courses.leodg.localhost`

Data: 25/11/2026. Worktree `ambiente-local`, branch `feature/ambiente-local`.

## Objetivo

Um ambiente local pronto, sempre no ar, que recebe os plugins novos antes da
VPS. "Pronto" quer dizer **a mesma configuracao da producao** — tema, SMTP,
autenticacao, pagina inicial, plugins e gateways — com tres diferencas
deliberadas, e so elas:

| | Producao | Local | Por que difere |
|---|---|---|---|
| Endereco | `courses.leodg.dev` | `courses.leodg.localhost:8443` | `*.localhost` resolve para 127.0.0.1 em qualquer navegador, sem `/etc/hosts` |
| Mercado Pago | `testmode=0`, chaves de producao | `testmode=1`, chaves de TESTE da mesma aplicacao | compra no localhost nao pode cobrar de verdade |
| Cursos | 3 cursos demo | cursos restaurados dos backups da Ivana Academy | dado de teste com cara de curso real |

O resto vem lido da producao e escrito no local por script, e o mesmo script
**confere** que continua igual. Um espelho que ninguem confere deixa de ser
espelho no primeiro `upgrade`.

## Decisoes do usuario (25/11/2026)

- **Recriar o stack principal** (`ldg-courses`, offset 0), com o banco local
  reinstalado do zero. O banco antigo e descartavel: sem cliente real.
- **Tunel: `mp.leodg.dev`.** Ja existe no `cloudflared` da maquina e ja esta no
  painel do Mercado Pago como redirect URI. Trocar o nome obrigaria recadastrar.
- **Mercado Pago: credenciais de teste, `testmode` ligado.**
- **SMTP: o mesmo Gmail da producao.** Alunos de teste com e-mail
  `@example.com`, para nada chegar em pessoa real.
- **`main` e `dev` acompanhando o `MOODLE_502_STABLE` oficial.**

## O que a investigacao mostrou

1. **A `main` nao e espelho do upstream.** E um retrato da `dev` de agosto, com
   75 commits proprios, e **push na `main` dispara o deploy para a mesma VPS**
   (`deploy.yml`, `on.push.branches: [dev, main]`). Atualiza-la com o upstream
   puro publicaria em producao um Moodle sem os plugins. O unico estado seguro
   para a `main` e **o mesmo conteudo da `dev`**. Simulado com
   `git merge-tree`: o merge `dev -> main` sai limpo e a arvore resultante e
   identica a da `dev`.
2. **O upstream estava 38 commits a frente** (5.2.5+ Build 20261124). Merge
   limpo; o `upgrade.php` local foi de 2026042005.01 para .02 sem erro.
3. **O `moodev` crava `https://localhost:<porta>`** no `MOODLE_URL` a cada
   `moodev use`/`new`. Editar o `dev.env` na mao duraria ate o proximo `moodev
   new`. O nome do host do stack principal precisa morar no `moodev`.
4. **O `notification_url` do Mercado Pago sai do `wwwroot` de cada
   preferencia** (`payment_processor.php`). Compra iniciada em
   `courses.leodg.localhost` manda o webhook para um endereco que o Mercado Pago
   nao alcanca. A compra de teste precisa comecar **pelo host do tunel**.
5. **O `wwwroot` e um so.** Hoje `mp.leodg.dev` responde `303` para
   `localhost:8443`, porque o Moodle redireciona todo Host que nao e o dele.
   Para os dois enderecos funcionarem, o `config.php` precisa aceitar o host do
   tunel como segundo `wwwroot` — so ele, e so quando a variavel existir.
6. **Producao usa o tema `ldg`**, nao mais o Moove (a memoria antiga dizia o
   contrario; medido no `mdl_config` em 25/11).
7. Os 31 backups `.mbz` da Ivana Academy ja estao nesta maquina
   (`~/localhost/ivana-videos/moodle/`), nada a copiar da VPS.

## Etapas

### 0. Upstream (`main` e `dev`)

- Merge de `upstream/MOODLE_502_STABLE` na `dev`, validado no stack local
  (upgrade + PHPUnit dos 11 plugins). **Push na `dev` = deploy**: so com o ok do
  usuario.
- `main`: PR `dev -> main` (merge, sem force). Tambem dispara deploy, do mesmo
  codigo.

### 1. `moodev` com host do stack principal

- `.moodev/host` (estado local, fora do git) guarda o nome do host do offset 0.
  Sem o arquivo, `localhost` — comportamento de hoje.
- `moodev use/new/ls/up` passam a escrever e mostrar `https://<host>:8443` no
  offset 0. Stacks secundarios continuam em `localhost`.
- `moodev doctor` confere que o certificado cobre o host.
- Certificado `mkcert` regenerado com `courses.leodg.localhost`.

### 2. Segundo `wwwroot` para o tunel

`config-docker.php` le `MOODLE_TUNNEL_URL`. Se o `Host` da requisicao for o
host dessa URL, o `wwwroot` vira ela. Sem a variavel — como na VPS — nada muda.
Comparacao exata, nunca sufixo: aceitar `*.leodg.dev` deixaria qualquer
subdominio virar `wwwroot`.

### 3. Script do espelho: `.devcontainer/devtools/ambiente-local.php`

Um script, dois modos:

- `--check` (padrao): compara o banco com a lista do espelho e sai `!= 0` na
  primeira divergencia. **E o teste** — escrito e rodado vermelho antes do
  `--apply` existir.
- `--apply`: grava a config, cria usuarios, restaura cursos, matricula o aluno,
  cria a conta de pagamento com o Mercado Pago em teste.

Segredos (senha do SMTP, chaves de teste do MP, senha do admin e do aluno) vem de
`.devcontainer/secrets/ambiente-local.env`, gitignored. O repo e publico: nem
senha nem e-mail pessoal entram no script.

### 4. Reinstalar o banco do stack principal

`moodev down`, mover (nao apagar) `dbdata-ldg-courses` e
`moodledata-ldg-courses` para `*.antes-2026-11-25`, `moodev up`,
`install_database.php`, `ambiente-local.php --apply`, `--check`.

### 5. Tunel e Mercado Pago (passos do usuario)

- Cloudflare Zero Trust: conferir que `mp.leodg.dev` aponta para
  `http://localhost:8080`.
- Painel do MP (aplicacao da plataforma): webhook de **teste** em
  `https://mp.leodg.dev/payment/gateway/mercadopago/webhook.php`, evento
  `payment`; redirect URI do OAuth ja cadastrada.
- Preencher o `ambiente-local.env` com as chaves de teste.

### 6. Validacao

- `ambiente-local.php --check` verde.
- Navegador: login admin e aluno em `courses.leodg.localhost:8443`, aluno ve o
  curso; mesma coisa por `mp.leodg.dev`; tema `ldg` no `styles.php`.
- Webhook: `POST` no `webhook.php` pelo tunel chega no Moodle (log do Apache).
- Compra de teste pelo tunel ate o webhook.

## Fora do escopo

- Copiar banco ou `moodledata` da producao (sem cliente real; o usuario
  dispensou).
- Porta 443 no host para tirar o `:8443` da URL: exigiria mexer nas portas base
  do `moodev`, que deslocam todos os stacks.
