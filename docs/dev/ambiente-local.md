# Ambiente local espelho da producao

O stack principal (`ldg-courses`, offset 0) serve
**`https://courses.leodg.localhost:8443`** com a mesma configuracao de
`courses.leodg.dev`, e e por ele que plugin novo passa antes da VPS. O tunel
**`https://mp.leodg.dev`** entrega o mesmo site para fora, que e por onde o
Mercado Pago chega.

Plano e decisoes: [`../ai-plans/2026-11-25-ambiente-local-courses-leodg-localhost.md`](../ai-plans/2026-11-25-ambiente-local-courses-leodg-localhost.md).

## O que e igual, e o que nao e

Igual, e conferido por script: tema `ldg` e seus ajustes, SMTP Gmail,
autenticacao por e-mail, pagina inicial, `local_partners`, `local_marketplace`,
gateways, idioma e fuso.

Diferente, de proposito:

| | Producao | Local |
|---|---|---|
| Endereco | `courses.leodg.dev` | `courses.leodg.localhost:8443` e `mp.leodg.dev` |
| Mercado Pago | aplicacao `7939…`, `testmode` desligado | aplicacao `2401225442871147` (conta CNPJ), `testmode` ligado, chave publica de teste |
| Cursos | 3 demo | 3 demo + backups da Ivana Academy |
| Debug | `NONE` | `DEVELOPER` (do `dev.env`) |

## As pecas

| Peca | Onde | Versionado? |
|---|---|---|
| Nome do host do principal | `<raiz>/.moodev/host` | nao — escolha desta maquina |
| Certificado com o nome | `.devcontainer/certs/develop-local.{crt,key}` | nao |
| Endereco do tunel | `MOODLE_TUNNEL_URL` no `dev.env` | nao (modelo no `dev.env.example`) |
| Segredos (SMTP, MP, senhas) | `.devcontainer/secrets/ambiente-local.env` | nao (modelo no `env/ambiente-local.env.example`) |
| O espelho em si | `.devcontainer/devtools/ambiente-local.php` | **sim** |
| Rota do tunel | painel Cloudflare Zero Trust, servico `cloudflared` da maquina | — |

### Por que `*.localhost`

Navegador e `curl` resolvem qualquer subdominio de `localhost` para 127.0.0.1,
sem `/etc/hosts`. O `moodev` le o nome de `.moodev/host` e o usa so no offset 0:
`moodev use`, `new`, `ls` e `up` passam a escrever e mostrar
`https://courses.leodg.localhost:8443`. Sem o arquivo, `localhost` como sempre.

O `:8443` fica. Tira-lo exigiria mudar as portas base do `moodev`, que deslocam
todos os stacks.

### Por que dois enderecos funcionam ao mesmo tempo

O Moodle tem um `wwwroot` so, e redireciona todo `Host` que nao e o dele. O
`config-docker.php` aceita **exatamente** o host de `MOODLE_TUNNEL_URL` como
segundo `wwwroot` — nao sufixo, nao curinga. CLI e cron ficam no `MOODLE_URL`.

> **Compra de teste comeca pelo tunel.** O `notification_url` da preferencia
> sai do `wwwroot` da requisicao. Comprar em `courses.leodg.localhost` manda o
> webhook para um endereco que o Mercado Pago nao alcanca, e o pagamento aprova
> sem matricular — sem erro nenhum.

Sessao e por host: logar em um endereco nao loga no outro.

## Montar do zero

Destroi o banco e o `moodledata` do stack principal. Os dois sao **movidos**,
nao apagados, para `*.antes-AAAA-MM-DD`.

```bash
# 1. nome e certificado (uma vez por maquina)
echo courses.leodg.localhost > ~/localhost/gitworktree-bare-moodle/.moodev/host
cd .devcontainer/certs
mkcert -cert-file develop-local.crt -key-file develop-local.key \
  courses.leodg.localhost localhost courses.leodg.dev 127.0.0.1
cd -

# 2. segredos
cp .devcontainer/env/ambiente-local.env.example .devcontainer/secrets/ambiente-local.env
#    preencher (ver "Segredos" abaixo)

# 3. tunel no dev.env
echo 'MOODLE_TUNNEL_URL=https://mp.leodg.dev' >> .devcontainer/env/dev.env

# 4. banco novo
moodev use <worktree>            # reescreve o MOODLE_URL com o nome novo
moodev down
D=$(date +%F); mv ~/localhost/dbdata-ldg-courses{,.antes-$D}; mv ~/localhost/moodledata-ldg-courses{,.antes-$D}
moodev up
docker exec -u 1000:33 ldg-courses-moodle-1 php /var/www/html/admin/cli/install_database.php \
  --agree-license --adminpass=trocar-depois --adminemail=admin@example.com \
  --fullname="LDG Courses & Technology" --shortname=LDG

# 5. backups da Ivana no moodledata (o container nao enxerga ~/localhost)
B=~/localhost/moodledata-ldg-courses/ambiente-local-backups
mkdir -p $B && cp -r ~/localhost/ivana-videos/moodle/{br,arg-free} $B/

# 6. espelho, cenario e conferencia
A="docker exec -u 1000:33 ldg-courses-moodle-1 php"
$A /var/www/html/public/local/marketplace/cli/seed_demo.php --company=demo \
   --seller=vendedor1 --password="<SELLER_PASSWORD do arquivo de segredos>"
$A /var/www/html/.devcontainer/devtools/ambiente-local.php --apply
$A /var/www/html/.devcontainer/devtools/ambiente-local.php          # --check: tem que sair 0
```

`--apply` e idempotente: rodar de novo conserta o que divergiu e nao duplica
usuario, curso nem matricula. Os cursos restaurados levam `idnumber`
`ambiente-local:<arquivo>`, e e por ele que o script sabe o que ja restaurou.

### Duas coisas que o restore esconde

**Os backups trazem as alunas reais, sem anonimizar** (`users=1`,
`anonymize=0` no `moodle_backup.xml`). O script desliga `users`,
`role_assignments` e afins antes de restaurar, e o `--check` **falha** se houver
no banco usuario com e-mail fora de `example.com` e `exemplo.local`. O SMTP
daqui e o de producao: essa guarda e o que impede e-mail de chegar em pessoa
real.

**Alguns cursos vem ocultos.** A primeira versao matriculou o aluno no primeiro
curso restaurado, que era oculto: a matricula existia, o `--check` passava e o
painel dizia *No courses*. O aluno agora vai para o primeiro curso **visivel**,
e o `--check` confere com `can_access_course()`, o mesmo caminho do painel.

## Segredos

| Chave | De onde vem |
|---|---|
| `ADMIN_PASSWORD`, `STUDENT_PASSWORD` | escolha local |
| `SMTP_USER`, `SMTP_PASS` | `mdl_config` da producao (`smtpuser`, `smtppass`) |
| `MP_CLIENTID`, `MP_CLIENTSECRET`, `MP_PUBLICKEYTEST` | aplicacao `2401225442871147`: `docs/private/mercadopago.txt` (fora do git) ou o painel, em **Credenciais de teste** |
| `MP_WEBHOOKSECRET` | painel do MP, Webhooks, **modo de teste** |

**A aplicacao do Mercado Pago NAO e a da producao**, por decisao de 25/11/2026: a
`2401225442871147` tem client id, secret e chave publica de teste num lugar so.
Juntar a chave publica de uma aplicacao com o client id de outra da
`Invalid users involved` ou `Resource not found`. A producao usa a `7939…`.

Copiar o SMTP da producao **sem o valor passar pela tela**, de dentro da worktree
servida (o `ssh` usa o alias `automation-oracle`):

```bash
F=.devcontainer/secrets/ambiente-local.env
T=.devcontainer/secrets/prod.tmp
ssh automation-oracle 'cd /home/ubuntu/courses-free/repo && U=$(cat .devcontainer/secrets/db_user) && P=$(cat .devcontainer/secrets/db_password) && docker exec -e MYSQL_PWD="$P" ldg-courses-db-1 mariadb -u"$U" moodle -N -B -e "SELECT CONCAT(\"SMTP_USER=\", value) FROM mdl_config WHERE name=\"smtpuser\" UNION ALL SELECT CONCAT(\"SMTP_PASS=\", value) FROM mdl_config WHERE name=\"smtppass\""' > $T
wc -l $T                       # tem que dizer 2
while IFS='=' read -r k v; do sed -i "/^$k=/d" $F; printf '%s=%s\n' "$k" "$v" >> $F; done < $T
rm $T; chmod 600 $F
```

`printf`, e nao `sed` com o valor: senha com `/`, `&` ou `#` quebraria a
substituicao.

## Tunel e Mercado Pago

O `cloudflared` roda como servico (`systemctl status cloudflared`), com a
configuracao **no painel** (token em `/etc/cloudflared/token`). A rota:

| Hostname publico | Servico |
|---|---|
| `mp.leodg.dev` | `http://localhost:8080` |

HTTP, nao HTTPS: a Cloudflare termina o TLS, e o `sslproxy` do `config.php`
faz o Moodle se ver em HTTPS.

No painel do Mercado Pago, aplicacao da plataforma, **modo de teste**:

| Campo | Valor |
|---|---|
| Redirect URI | `https://mp.leodg.dev/payment/gateway/mercadopago/oauth_callback.php` |
| Webhook | `https://mp.leodg.dev/payment/gateway/mercadopago/webhook.php`, evento `payment` |

O vendedor de teste vincula a conta pelo OAuth, no navegador, **pelo tunel**:
categoria *Empresa Demo* → Contas de pagamento → Mercado Pago → Vincular. As
armadilhas do modo de teste estao em
[`../data-validation/mercadopago-split.md`](../data-validation/mercadopago-split.md).

## Plugin novo neste ambiente

E o fluxo de sempre: `moodev new <plugin>` monta a worktree nova no mesmo
stack, com este banco e este nome. Depois de instalar, rode o `--check`: plugin
que muda configuracao do site aparece ali antes de chegar a producao.

Quando a producao mudar uma configuracao de proposito, a lista do espelho muda
junto, no mesmo PR.
