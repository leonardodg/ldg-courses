# LDG Courses  — Infraestrutura

Ambiente Docker do projeto. **Um único ambiente** (`development`), rodando na VPS
atrás de nginx.

---

## Stack

| Componente | Versão | Por quê |
|---|---|---|
| Moodle | 5.2.2+ | branch `MOODLE_502_STABLE` |
| PHP | 8.4 | Moodle 5.2 exige >= 8.3.0; 8.4 é a maior tag publicada pela `moodlehq` |
| MariaDB | 11.4 | Moodle 5.2 exige >= 10.11 |
| Apache | 2.4 | da imagem `moodlehq/moodle-php-apache` |

**Webroot é `public/`.** No layout do Moodle 5.x o `config.php` e os scripts de
`admin/cli/` ficam na raiz do repo, *fora* do que o Apache serve. O `DocumentRoot`
aponta para `/var/www/html/public`.

---

## Estrutura

```
.devcontainer/
├── build/moodle.Dockerfile   # multi-stage: base → production | development
├── compose/
│   ├── base.yml              # nome do projeto, rede, secrets, volumes
│   ├── db.yml                # MariaDB (bind mount em DB_HOST_DATA)
│   ├── dev.yml               # o ambiente (usado local E na VPS)
│   ├── local-tls.yml         # SO LOCAL: TLS no container + porta 443
│   └── build-dev.yml         # SO LOCAL: builda em vez de puxar do Hub
├── env/
│   ├── defaults.env          # não-sensível, versionado
│   ├── db.env                # gitignored
│   └── dev.env               # gitignored
├── secrets/                  # gitignored por inteiro
├── certs/                    # *.key e *.crt gitignored; só os .example entram
├── apache/, php/, bin/, config/
└── readme.md
```

Na raiz do repo:

- `.env` — **gitignored**. Variáveis de *interpolação* do compose.
- `.env.example` — template versionado.
- `config.php` — **gitignored**. Cópia de `.devcontainer/config/config-docker.php`.

### Por que `.env` e `env/dev.env` são coisas diferentes

`env_file:` define variáveis **dentro do container**. `${VAR}` dentro dos YAML é
**interpolação**, resolvida pelo compose antes de subir, e vem do shell ou do
arquivo apontado por `COMPOSE_ENV_FILE`. Um caminho de bind mount é interpolação,
então tem que estar no `.env` — colocá-lo só no `dev.env` não funciona.

### Por que `config.php` precisa existir na raiz do host

O Dockerfile copia `config-docker.php` para `/var/www/html/config.php` dentro da
imagem, mas o `dev.yml` monta o repo do host por cima de `/var/www/html` e esconde
esse arquivo. Não use symlink: o `require_once(__DIR__ . '/lib/setup.php')` no fim
do arquivo resolveria para o diretório real e quebraria.

---

## Setup inicial

```bash
# 1. Arquivos de configuração
cp .env.example .env                                  # ajuste os caminhos do host
cp .devcontainer/env/dev.env.example .devcontainer/env/dev.env
cp .devcontainer/env/db.env.example  .devcontainer/env/db.env
cp .devcontainer/config/config-docker.php config.php

# 2. Secrets (têm que bater entre db.env e os arquivos)
head /dev/urandom | tr -dc 'A-Za-z0-9' | head -c 24 > .devcontainer/secrets/db_password
head /dev/urandom | tr -dc 'A-Za-z0-9' | head -c 24 > .devcontainer/secrets/db_root_password
echo "moodle" > .devcontainer/secrets/db_user

# 3. Pastas de dados (bind mounts — precisam existir antes)
mkdir -p "$MOODLE_HOST_DATA" "$DB_HOST_DATA"

# 4. Certificado: o entrypoint gera um self-signed se não existir.
```

---

## Uso

```bash
# LOCAL — inclui local-tls.yml (o container serve HTTPS) e build-dev.yml
export COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml:.devcontainer/compose/local-tls.yml:.devcontainer/compose/build-dev.yml
export COMPOSE_ENV_FILE=.env

docker compose up --build -d
docker compose logs -f moodle
docker compose ps
docker compose down
```

```bash
# VPS — sem local-tls.yml e sem build-dev.yml.
# Quem termina TLS é o nginx (certbot); a imagem vem do Docker Hub.
export COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml
export COMPOSE_ENV_FILE=.env
```

`COMPOSE_ENV_FILE` **não é opcional**: sem ele o compose procura o `.env` no
diretório do primeiro arquivo compose (`.devcontainer/compose/`), não na raiz.

### Por que o TLS do container é um arquivo separado

Local não há nginx na frente, então o próprio Apache do container precisa servir
HTTPS — e com certificado **confiável**, não qualquer um: `.dev` é um TLD
HSTS-preloaded e o navegador não oferece botão de "prosseguir" para certificado
desconhecido. Por isso o par vem do `mkcert`:

```bash
cd .devcontainer/certs
mkcert -cert-file develop-local.crt -key-file develop-local.key \
       courses.leodg.localhost localhost courses.leodg.dev 127.0.0.1
```

`courses.leodg.localhost` é o nome do stack principal (`.moodev/host`, ver
`docs/dev/ambiente-local.md`). Sem ele no certificado, o navegador recusa o nome.

Na VPS esses arquivos não existem — `certs/` está no `EXCLUDE` do rsync. Se os
secrets `ssl_cert`/`ssl_key` estivessem no `dev.yml`, o Docker falharia o bind
antes do container nascer, e o Compose **não permite secret opcional nem
remover secret por override** (o merge é aditivo). Daí o arquivo à parte.

### Instalar o Moodle

```bash
docker compose exec moodle php admin/cli/install_database.php \
    --agree-license --adminuser=admin --adminpass='...' \
    --adminemail='...' --fullname="LDG Technology & Courses" --shortname="LDG-Courses"
```

Os CLI ficam em `admin/cli/` na **raiz**, não em `public/admin/cli/`.

---

## Portas

Definidas no `.env`. Local e VPS divergem porque as portas ocupadas são outras:

| Serviço | Local | VPS | Container |
|---|---|---|---|
| Moodle HTTP | 8080 | **8095** | 80 |
| Moodle HTTPS | 8443 | 8443 | 443 |
| MariaDB | 3307 | 3307 | 3306 |
| Xdebug | 9004 | 9004 | 9003 |

Local: 80/443/3306/9003 estão com o stack do `ivana-academy`.
VPS: 8080, 8081 e 8082 estão com outros serviços.

`BIND_ADDR` controla a interface do Apache e do Xdebug. **Na VPS tem que ser
`127.0.0.1`**, senão `137.131.141.93:8095` responde direto e contorna o nginx e o
TLS, e a porta do Xdebug fica alcançável de fora.

`DB_BIND_ADDR` controla a do banco, e é uma variável separada justamente para
que liberar o banco não arraste as outras duas. `127.0.0.1` é o padrão; para
acessar o MariaDB de fora da VPS, use o IP da interface do WireGuard
(`ip -4 addr show wg0`) — o banco passa a escutar só na VPN e continua invisível
no IP público.

`MOODLE_URL` tem que bater **exatamente** com a forma de acesso, porta inclusa —
o Moodle reescreve toda URL absoluta para o `wwwroot`. Local: `https://courses.leodg.dev:8443`.
Na VPS, com nginx na 443: `https://courses.leodg.dev`.

---

## Dados e backup

Nem o banco nem o moodledata usam volume nomeado — os dois são **bind mount** em
pasta conhecida do host, para o backup ser um `tar` da pasta:

| O quê | Variável | Sugestão na VPS |
|---|---|---|
| Código | `MOODLE_HOST_WWWROOT` | `/home/ubuntu/ldg-courses/repo` |
| moodledata | `MOODLE_HOST_DATA` | `/home/ubuntu/ldg-courses/moodledata` |
| Banco | `DB_HOST_DATA` | `/home/ubuntu/ldg-courses/dbdata` |

---

## Armadilhas conhecidas

- **`chown` em bind mount vaza para o host.** O entrypoint chowneia só o
  `moodledata`. Um `chown -R www-data /var/www/html` reescreveria o dono do repo
  do host, inclusive `.git`, e o git passaria a recusar com *dubious ownership*.
  Se acontecer: `docker run --rm -v "$PWD":/repo alpine:3 chown -R 1000:1000 /repo`.
- **Não fixe `name:` em rede ou volume.** Um nome global faz stacks de projetos
  diferentes compartilharem a mesma rede, e os serviços `db`/`moodle` colidem no DNS.
- **`ports:` soma, não sobrescreve.** Declarar a mesma porta em `dev.yml` e num
  override duplica o bind e o `up` falha.
- **`| tail` mascara o exit code** do `docker compose`. Use `${PIPESTATUS[0]}`.
- **`PHP_OPCACHE_VALIDATE_TIMESTAMPS=0` desliga o hot-reload.** Com 0 o opcache
  nunca reconfere os arquivos e suas edições não têm efeito sem reiniciar.
- **Trocar um secret exige reiniciar o container.** O entrypoint roda como root,
  lê `/run/secrets/*` e **exporta como variável de ambiente** antes de entregar
  o controle ao Apache — necessário, porque `www-data` não consegue ler arquivos
  `600` do uid do host. O efeito colateral é que a credencial é lida **uma única
  vez, no boot**: reescrever o arquivo não muda nada até `docker compose restart`.

  O modo de falhar engana: `docker compose exec` cria um processo novo e lê o
  valor atualizado, então testes por CLI passam enquanto o site devolve 500.
  Para comparar o que o Apache realmente carrega com o que está no arquivo:

  ```bash
  docker compose exec -T moodle sh -c \
    "tr '\0' '\n' < /proc/1/environ | grep '^MOODLE_DBPASS=' | cut -d= -f2- | md5sum" </dev/null
  docker compose exec -T moodle md5sum /run/secrets/db_password </dev/null
  ```
