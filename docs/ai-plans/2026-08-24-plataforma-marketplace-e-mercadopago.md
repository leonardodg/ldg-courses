> **Situação:** executado, com pontas soltas · **Início:** 2026-08-24
> **Origem:** `~/.claude/plans/vamos-implementar-solucao-proposta-snappy-petal.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** plano fundador do marketplace. O `paygw_mercadopago` saiu daqui, nos PRs #27 a #48. As pontas soltas do gateway estão no [plano dos gateways](2026-08-27-plano-original-asaas-e-pagarme.md).

# Plataforma de cursos com marketplace e customização por vendedor

## Context

Construir uma plataforma de cursos onde qualquer pessoa publica conteúdo gratuito ou pago:

| Regra de negócio | Detalhe |
|---|---|
| Conteúdo gratuito | Vídeo **obrigatoriamente** hospedado fora da plataforma |
| Conteúdo pago, vídeo externo | Plataforma retém **25%** da venda |
| Conteúdo pago, vídeo na plataforma | Modelo **em aberto** — custo de `moodledata` e banda escala com nº de alunos |
| Pagamento | Mercado Pago com split; cada vendedor vincula a conta dele |
| Perfil vendedor | Empresa (CNPJ opcional) + tema próprio + domínio próprio (`meuscursos.joao.com`) |
| Captação | Tema exclusivo da plataforma para atrair novos empreendedores |

A conversa anterior (`conversa.txt`, `plano-plataforma-cursos_1.md`) recomendou **IOMAD** como base. Essa
recomendação foi feita sem acesso à máquina. Com o código IOMAD em mãos (worktree `iomad-base`), a medição
mudou a decisão — ver "Por que não IOMAD" abaixo.

Reaproveita-se o `/home/leodg/ivana-academy` (Moodle 4.5.1+), que já tem devcontainer, CI e 9 plugins validados.

### Decisões tomadas

| Decisão | Escolha |
|---|---|
| Base | Moodle **5.2** no `gitworktree-bare-moodle` (push → `leonardodg/courses-free`) |
| Empresa/tenancy | Plugin **`local_marketplace`** próprio + recursos nativos do core — **sem fork** |
| Domínio por vendedor | Mapa **Host→wwwroot no `config.php`** (arquivo do site, não do core) |
| Reverse proxy | **nginx puro** + certbot na VPS Oracle |
| Landing do vendedor | Dentro do Moodle, no domínio dele — não é vitrine externa |
| Plugins | Releases oficiais 5.x do marketplace — **sem migração** do 4.5. Os 3 sem release 5.2 ficam de fora do MVP |

### Por que não IOMAD

Medido com `git diff 152bf2ebbdd 0616692c833` (Moodle 5.0.9 → IOMAD 5.0.9):

```
171 arquivos do core MODIFICADOS
 14 arquivos do core REMOVIDOS   (admin/tool/mfa/factor/sms inteiro, entre outros)
```

Não é um conjunto de plugins — é um fork. Prende o projeto ao Moodle 5.0 e ao ciclo de release do IOMAD,
e remover o fator MFA por SMS é regressão de segurança não pedida. O que ele entrega já existe no 5.2:

| Necessidade | Recurso nativo do 5.2 | Onde |
|---|---|---|
| Tema por empresa | `allowcategorythemes` / `allowcohortthemes` | `public/admin/settings/appearance.php:334` |
| Vendedor com credencial de pagamento própria | payment account **escopada por contexto** | `moodle/payment:manageaccounts` é `CONTEXT_COURSE` em `public/lib/db/access.php:2700` |
| Curso pago → conta do vendedor | `enrol_fee` guarda a conta em `customint1` | `public/enrol/fee/classes/plugin.php:346` |
| Contas visíveis por contexto-pai | `helper::get_payment_accounts_menu()` | `public/payment/classes/helper.php:406` |

O único item que o IOMAD resolve e o core não é o **domínio por empresa** — e ele resolve reescrevendo
`$CFG->wwwroot` cedo demais para um plugin alcançar (`iomad-base/lib/setuplib.php:680`):

```php
$CFG->wwwrootdefault = $CFG->wwwroot;
if ($companyrec = $DB->get_record('company', ['hostname' => $_SERVER['SERVER_NAME']])) {
    $CFG->wwwroot = $company->get_wwwroot();   // mesmo scheme e path, host trocado
}
```

Daí o patch de core. Mas o `config.php` roda **antes** do `lib/setup.php` (`config-dist.php:1340`) e não é
arquivo do core — é onde `$CFG->wwwroot` nasce (`config-dist.php:176`). O mesmo efeito cabe ali, sem fork.

---

## Arquitetura

```
empresa (local_marketplace)
   ├── categoria de curso        →  isolamento de catálogo
   ├── tema                      →  allowcategorythemes (nativo)
   ├── domínio                   →  mapa Host→wwwroot no config.php
   ├── payment account           →  contexto da categoria (nativo)
   │      └── paygw_mercadopago  →  token OAuth do vendedor + marketplace_fee
   └── papel "vendedor"          →  atribuído no contexto da categoria
```

Um container Moodle, um banco, N domínios. O nginx só termina TLS e encaminha preservando `Host` e
`X-Forwarded-Proto`.

---

## Componentes a construir

### 1. `public/local/marketplace/` — empresa e vendedor

Núcleo do projeto. Responsável por:

- CRUD de empresa: nome, CNPJ (opcional), domínio, tema, categoria vinculada, status
- Ao criar empresa: cria a categoria, atribui o papel `vendedor` ao dono no contexto dela, cria a payment account
- Política de hospedagem de vídeo por curso (`external` | `platform`) e o percentual de comissão resultante
- Fluxo OAuth do Mercado Pago (autorizar → guardar `access_token`/`refresh_token`) e gravação no gateway da conta do vendedor
- Task de cron para renovar tokens antes dos ~6 meses de validade
- Relatório por empresa: bruto, taxa MP, comissão da plataforma, líquido
- **Regenerar o mapa de domínios** (item 3) a cada mudança de domínio

Tabelas:

```
local_marketplace_company
  id, name, cnpj (null), ownerid, categoryid, themename,
  hostname (null, unique), status, timecreated, timemodified

local_marketplace_seller
  id, companyid, userid, mp_user_id, access_token, refresh_token,
  token_expires_at, oauth_status, timemodified

local_marketplace_course
  id, courseid, companyid, hosting_type (external|platform), commission_pct

local_marketplace_sale
  id, courseid, companyid, buyerid, amount, mp_payment_id,
  commission_pct, marketplace_fee, status, timecreated
```

### 2. `public/payment/gateway/mercadopago/` — gateway com split

Molde completo já existe em `dev/public/payment/gateway/paypal/`: `classes/gateway.php`,
`classes/external/get_config_for_js.php`, `classes/external/transaction_complete.php`,
`amd/src/gateways_modal.js`, `db/install.xml`, `settings.php`. Copiar a estrutura e trocar a integração.

Especificidades do Mercado Pago:

- Config **por payment account** (a do vendedor): `mp_user_id`, `access_token`, `refresh_token`
- Ao criar a preferência (Checkout Pro): `marketplace_fee` = `amount × commission_pct`
  (Checkout API/Bricks usa `application_fee`)
- Ordem de dedução é fixa e não configurável: taxa do MP primeiro, comissão da plataforma sobre o restante.
  O relatório precisa refletir isso, não estimar 25% do bruto.
- Webhook de notificação → confirma `local_marketplace_sale` e dispara `enrol_fee`
- Sandbox do MP para teste ponta a ponta antes de qualquer credencial real

### 3. Mapa Host→wwwroot no `config.php`

`$DB` não existe ainda no `config.php`, então **não** consultar o banco ali. `local_marketplace` gera um
arquivo PHP no `moodledata` sempre que um domínio muda:

```php
// $CFG->dataroot/marketplace_domains.php  (gerado)
return ['meuscursos.joao.com' => 'https://meuscursos.joao.com', ...];
```

E o `config.php` do site, antes do `require_once(__DIR__ . '/lib/setup.php')`:

```php
$CFG->wwwrootdefault = $CFG->wwwroot;
if (php_sapi_name() !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $map = @include($CFG->dataroot . '/marketplace_domains.php');
    if (is_array($map) && isset($map[$_SERVER['HTTP_HOST']])) {
        $CFG->wwwroot = $map[$_SERVER['HTTP_HOST']];
    }
}
```

Regras que vêm junto:
- CLI/cron mantém o `wwwroot` padrão — e-mails e links de cron saem no domínio da plataforma
- **Não** definir `$CFG->sessioncookiedomain`: o cookie fica escopado por host, e a sessão passa a ser por
  domínio. Um aluno logado em `plataforma.com` **não** está logado em `meuscursos.joao.com` — comportamento
  aceito, precisa estar claro na UX de checkout
- Domínio só entra no mapa depois de validado (registro do vendedor + certificado emitido)

### 4. Tema de captação

Tema filho de `theme_boost_union` para a frontpage da plataforma, focado em conversão de novos vendedores.
Separado do tema entregue às empresas.

### 5. Infra e CI

- `.devcontainer/` já está em `feature-infra-vps` (byte-a-byte idêntico ao do ivana-academy) — falta subir
  para o 5.2 e ajustar a versão do Moodle no `readme.md`, que ainda diz 4.5.1+
- Portar de `/home/leodg/ivana-academy/.github/`: `workflows/*.yml` (5 workflows: sandbox GCP + produção) e
  `moodle-plugins.txt`, ajustando os paths para o layout `public/` e acrescentando `local/marketplace`,
  `payment/gateway/mercadopago` e o tema filho do moove à lista validada pelo `moodle-plugin-ci`.
  Os workflows atuais deployam em **GCP**; a VPS é **Oracle** — o job de deploy precisa ser reescrito, os de
  validação (lint/phpcs/phpunit) aproveitam como estão.
- nginx: `server` por domínio, `proxy_set_header Host $host` e `X-Forwarded-Proto $scheme`;
  certbot com `--nginx`. Emissão de certificado vira passo do onboarding de vendedor.

---

## Fases

| Fase | Entrega | Depende de |
|---|---|---|
| 0 | Infra 5.2 no ar (devcontainer + CI) + os **6 plugins com release 5.2** instalados e validados | — |
| 1 | `local_marketplace`: empresa, categoria, papel vendedor, tema por categoria | 0 |
| 2 | `paygw_mercadopago` + OAuth + split 25% (vídeo externo), ponta a ponta em sandbox | 1 |
| 3 | Domínio por vendedor: mapa Host→wwwroot, nginx, certbot no onboarding | 1 |
| 4 | Tema de captação + relatório financeiro por empresa | 2 |
| 5 | Conteúdo hospedado na plataforma — **bloqueada por decisão de negócio** | 4 |

---

## Riscos e pontos em aberto

1. **Três plugins não têm release 5.2.** Nada é migrado do 4.5 — cada plugin vem da release oficial 5.x
   do marketplace. Verificado em `marketplace.moodle.com`:

   | Plugin | Suporte declarado | 5.1 | 5.2 |
   |---|---|:--:|:--:|
   | `mod_customcert` | 2.9–5.2 | ✅ | ✅ |
   | `block_completion_progress` | 3.9–5.2 | ✅ | ✅ |
   | `block_configurable_reports` | 1.9–5.2 | ✅ | ✅ |
   | `enrol_coursecompleted` | 3.3–5.2 | ✅ | ✅ |
   | `theme_boost_union` | 4.0–5.2 | ✅ | ✅ |
   | `theme_moove` | 3.2–5.2 | ✅ | ✅ |
   | `mod_attendance` | 2.3–5.1 | ✅ | ❌ |
   | `format_onetopic` | 2.0–5.1 | ✅ | ❌ |
   | `format_tiles` | 3.3–5.1 (release há 7 meses) | ✅ | ❌ |

   **Decisão: fica no 5.2.** A Fase 0 instala os 6 com release 5.2 e deixa `mod_attendance`,
   `format_onetopic` e `format_tiles` **de fora**, sem forçar `$plugin->requires`. Consequências aceitas:
   - Curso usa formato nativo (`topics`/`weeks`) até `format_tiles`/`format_onetopic` publicarem 5.2
   - Sem controle de presença no MVP
   - `format_tiles` está há 7 meses sem release — se não publicar até a Fase 4, reavaliar substituto
   Manter os 3 comentados em `.github/moodle-plugins.txt` (o arquivo já usa esse padrão para plugin pendente)
   e revisar a cada fase.

2. **`theme_moove` customizado.** A base é a release oficial 5.x (Certified Partner, 27k instalações);
   suas customizações entram como **tema filho**, não como fork do `theme_moove`. Assim o upstream continua
   atualizável e o CI valida só o seu código.

3. **Split do Mercado Pago exige app de marketplace.** O `marketplace_fee` só funciona com token obtido via
   OAuth para a *sua* aplicação. "Vendedor cola a chave dele" (como estava na proposta original) não habilita
   split — precisa ser o fluxo OAuth completo. Validar em sandbox antes de construir a UI de onboarding.

4. **Token do vendedor expira em ~6 meses.** Sem a task de renovação, o repasse daquele vendedor para de
   funcionar silenciosamente. A task precisa alertar, não só tentar renovar.

5. **Enforcement de "vídeo externo".** Nada impede um vendedor de subir MP4 no `moodledata` num curso marcado
   como `external`. Precisa de verificação (task que audita tamanho por curso/categoria) — senão a regra dos
   25% é honra, não sistema. Escopo da Fase 2.

6. **Decisão de negócio pendente (sua, não técnica):** cobrança para conteúdo hospedado na plataforma.
   Duas rotas: assinatura com cota de storage/banda + comissão menor, ou comissão maior (30-35%) com limite
   técnico por curso. Alternativa que tira o peso do Moodle: streaming externo (Bunny/Mux/Vimeo) mesmo no
   plano "hospedado". O plano técnico das Fases 0-4 não muda com essa escolha — só o parâmetro de comissão.

---

## Verificação

**Fase 0**
```bash
export COMPOSE_FILE=.devcontainer/compose/base.yml:.devcontainer/compose/db.yml:.devcontainer/compose/dev.yml
docker compose up -d && docker compose logs -f moodle
php admin/cli/install_database.php --agree-license --adminpass=... --adminemail=...
php admin/cli/upgrade.php --non-interactive     # deve terminar sem plugin em erro
```
Checar em *Administração → Plugins → Visão geral* que nenhum dos 6 aparece como incompatível.

**Fase 1** — criar empresa pela UI; confirmar que a categoria nasceu, o papel foi atribuído no contexto dela,
e que trocar o tema da categoria muda a aparência só dentro dela (com `allowcategorythemes` ligado).

**Fase 2** — credenciais de teste do Mercado Pago; comprar um curso como aluno de teste; conferir no painel MP
que o valor caiu na conta do vendedor **menos** a taxa MP e menos os 25%; conferir que `enrol_fee` matriculou.
Testar o webhook com pagamento pendente→aprovado, não só o caminho feliz.

**Fase 3** — apontar dois domínios de teste no `/etc/hosts` para o container; confirmar que cada um serve o
tema da sua empresa, que `$CFG->wwwroot` acompanha o Host, e que **login em um domínio não vaza para o outro**.
Rodar `php admin/cli/cron.php` e verificar que os links dos e-mails usam o domínio padrão.

**Contínuo**
```bash
moodle-plugin-ci phplint && moodle-plugin-ci phpcs --max-warnings 0 && moodle-plugin-ci phpunit
```
com `local/marketplace` e `payment/gateway/mercadopago` listados em `.github/moodle-plugins.txt`.
