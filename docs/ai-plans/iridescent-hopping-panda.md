# Diagramas ER e de arquitetura dos plugins LeoDG

## Contexto

Os onze plugins desenvolvidos para a plataforma nunca tiveram o desenho do banco
representado em um lugar só. O que existe hoje é parcial e espalhado:
`docs/data-model/marketplace.md` descreve o `local_marketplace` em prosa, e
`docs/architecture/arquitetura-plataforma.svg` mostra o fluxo de split de
pagamento num único SVG de 680×404 — nenhum dos dois mostra tabela, campo ou
relação, e nenhum cobre os gateways, o `local_partners`, o `format_ldg` ou o
`mod_ldgvideo`.

O efeito prático é que a integridade referencial da plataforma só existe na
cabeça de quem escreveu. Boa parte dela é **de aplicação, não de banco**: o
XMLDB do Moodle não declara `ON DELETE`, e vários vínculos reais foram
deixados sem chave estrangeira de propósito — `local_partners_application.planid`
aponta para tabela de outro plugin e a FK reprovaria o `check_database_schema`;
`format_ldg_lesson.cmid` não tem FK para `course_modules` porque a linha órfã
não pode impedir a troca de formato. Essa distinção é exatamente o que um
diagrama mostra e um `install.xml` não.

O resultado é `docs/diagram/`: cinco diagramas de dados e três de arquitetura,
com skin da marca, gerados a partir do schema real — não de memória.

## Fonte da verdade

Todos os campos e relações saem dos `db/install.xml` lidos nesta sessão. No
Moodle o `install.xml` é o estado final do schema (o `upgrade.php` apenas leva
instalações antigas até ele), então é a fonte correta. Nada é inventado: campo
que não está no XML não entra no diagrama.

### Inventário — 16 tabelas próprias

| Plugin | Tabelas |
|---|---|
| `local_marketplace` | `_company`, `_member`, `_offer`, `_account`, `_offer_course`, `_entitlement`, `_sale`, `_course`, `_plan`, `_plan_tier` |
| `paygw_mercadopago` | `paygw_mercadopago` |
| `paygw_asaas` | `paygw_asaas` |
| `paygw_pagarme` | `paygw_pagarme` |
| `local_partners` | `local_partners_application` |
| `format_ldg` | `format_ldg_lesson` |
| `mod_ldgvideo` | `ldgvideo` |

### Plugins sem tabela própria — entram pelo que tocam

| Plugin | Onde vive o estado |
|---|---|
| `enrol_marketplace` | `{enrol}` + `{user_enrolments}` (confirmado no `lib.php`) |
| `availability_marketplace` | JSON em `course_modules.availability`: `{"type":"marketplace","offerid":N}` |
| `block_marketplace` | `{block_instances}` |
| `theme_ldg` | sem estado em tabela; `course_categories.theme` é o gancho por empresa |

### Tabelas do core, simplificadas

`user`, `course`, `course_categories`, `course_modules`, `context`,
`payments`, `payment_accounts`, `payment_gateways`, `enrol`,
`user_enrolments`, `block_instances` — só as colunas que participam de uma
relação, nunca o schema inteiro do Moodle.

## Decisões já tomadas

| Dial | Escolha |
|---|---|
| Fatiamento | mestre + 4 zooms por subsistema |
| Fluxos de cadastro | os dois — parceiro e aluno |
| Skin | perfil `leodg` dark, derivado de `docs/brand/design_system_leodg.md` |
| Saída | `.html` (fonte) + `.svg` + `.png` @2 |

## Entregáveis

Em `docs/diagram/` (é symlink para `dev/docs/diagram/`), cada um nos três formatos:

| Arquivo | Tipo | Conteúdo |
|---|---|---|
| `01-er-mestre` | `type-er` | 16 tabelas próprias + 11 do core, campos resumidos, **todas** as relações |
| `02-er-comercial` | `type-db-schema` | `_company`, `_plan`, `_plan_tier`, `_account`, `_member` |
| `03-er-venda-acesso` | `type-db-schema` | `_offer`, `_offer_course`, `_entitlement`, `_sale`, `payments` |
| `04-er-gateways` | `type-db-schema` | `paygw_mercadopago`, `paygw_asaas`, `paygw_pagarme`, `payments`, `payment_accounts` |
| `05-er-conteudo` | `type-db-schema` | `ldgvideo`, `format_ldg_lesson`, `course_modules`, `local_partners_application`, `_course` |
| `06-arquitetura` | `type-architecture` | índices, admin, visão do cliente, banco, gateways externos |
| `07-fluxo-parceiro` | `type-flowchart` | candidatura → confirmação → fila admin → empresa criada |
| `08-fluxo-aluno` | `type-flowchart` | vitrine → checkout → webhook → direito → matrícula |
| `README.md` | índice | o que cada diagrama responde, e a data do schema lido |

## A regra visual que carrega o argumento

Duas classes de aresta, e a legenda existe para separá-las:

- **Linha sólida** — FK declarada no `install.xml` (`<KEY TYPE="foreign">` ou
  `foreign-unique`). São 17.
- **Linha tracejada** — vínculo real sem FK declarada, garantido por código.
  São ~13, e cada uma tem motivo registrado no comentário do XMLDB.

A legenda diz, uma vez, que **o Moodle não declara `ON DELETE` no XMLDB** — a
cascata é de aplicação. Isso substitui o rótulo `ON DELETE …` que o
`type-db-schema.md` pede: no lugar dele, cada aresta leva o tipo da chave
(`foreign`, `foreign-unique`) ou `sem FK — validado em <arquivo>`.

### Relações a desenhar

FKs declaradas:

```
_company.categoryid   → course_categories.id
_company.planid       → _plan.id
_member.companyid     → _company.id          _member.userid      → user.id
_offer.companyid      → _company.id
_account.companyid    → _company.id          _account.accountid  → payment_accounts.id  (unique)
_offer_course.offerid → _offer.id            _offer_course.courseid → course.id
_entitlement.userid   → user.id              _entitlement.offerid → _offer.id
_entitlement.companyid→ _company.id
_sale.paymentid       → payments.id (unique) _sale.offerid       → _offer.id
_sale.companyid       → _company.id
_course.courseid      → course.id (unique)   _course.companyid   → _company.id
_plan_tier.planid     → _plan.id
paygw_{mercadopago,asaas,pagarme}.userid → user.id
```

Vínculos sem FK (tracejados):

```
local_partners_application.planid     ⇢ _plan.id       (validate_planid no persistent)
local_partners_application.companyid  ⇢ _company.id    (criada na aprovação; é o que torna aprovar idempotente)
local_partners_application.userid     ⇢ user.id        local_partners_application.reviewerid ⇢ user.id
paygw_*.paymentid                     ⇢ payments.id    (nulo até a confirmação — é o que impede entrega dupla)
paygw_*.accountid                     ⇢ payment_accounts.id
paygw_*.itemid                        ⇢ _offer.id      (quando paymentarea='offer')
format_ldg_lesson.cmid                ⇢ course_modules.id
ldgvideo.course                       ⇢ course.id      course_modules.instance ⇢ ldgvideo.id
course_modules.availability (JSON)    ⇢ _offer.id      (availability_marketplace)
payment_accounts.contextid            ⇢ context.id     (contexto da categoria da empresa)
enrol.courseid ⇢ course.id · user_enrolments.enrolid ⇢ enrol.id (enrol_marketplace)
```

## Superfícies para os diagramas de arquitetura

Caminhos reais, conferidos nesta sessão — os nós levam o caminho, não um nome inventado.

| Zona | Páginas |
|---|---|
| Público | `local/partners/index.php` (landing, sem `require_login` de propósito), `apply.php`, `confirm.php`, `thanks.php`, `sitemap.php` |
| Aluno | `local/marketplace/offers.php`, `claim.php` (oferta grátis), `mysubscriptions.php`, `cancel.php`, bloco `block_marketplace` |
| Vendedor | `local/marketplace/company.php` (painel), `offer_edit.php`, `report.php`, `refund.php`, `resend.php` |
| Admin do site | `local/marketplace/admin/{companies,company_edit,members,plans,plan_edit}.php`, `local/partners/admin/{applications,application_view}.php` |
| Gateways | `webhook.php`, `return.php`, `link.php`/`unlink.php` por gateway; `oauth_*.php` só no Mercado Pago; `pix.php`/`card.php` só no Pagar.me |
| Cron | `local_marketplace\task\notify_expiring` (06:30), `reconcile` nos três gateways, `refresh_tokens` no Mercado Pago |

## Passos

1. **Perfil de marca.** Snapshot de `default.md` a partir do `style-guide.md`
   pristino do pacote, depois `/diagram-design:profile save leodg` mapeando os
   tokens de `docs/brand/design_system_leodg.md`: bg `#121212`, surface
   `#1E1E1E`, accent `#007AFF`, border `#3A3B3C`, texto `#FFFFFF`/`#B0B3B8`,
   Inter. Gravar o marker `dev/.diagram-design` com o slug, para diagrama
   futuro neste repo resolver o perfil sozinho.
2. **`01-er-mestre`.** `type-er`, canvas `fit` (~2200×1500). Quatro clusters
   espaciais — comercial, venda/acesso, gateways, conteúdo — com o core no
   perímetro. Acento coral em `local_marketplace_company`, a raiz agregada.
   Campos resumidos: PK, FKs e as colunas que contam a história; o resto vira
   `+ N colunas`, nunca truncado em silêncio.
3. **`02`–`05`.** `type-db-schema`, `doc-wide`, 5 tabelas cada, FK ancorada
   linha-a-linha com os tipos SQL reais do XMLDB (`char(14)`, `number(10,2)`,
   `int(10)`). Compartimento de índices só com os que importam — por exemplo
   `subscriptionid` no Asaas, que é o caminho de volta do webhook do ciclo 2.
4. **`06`–`08`.** Arquitetura e os dois fluxos, `doc-wide`.
5. **Exportar** cada HTML com `/diagram-design:export-diagram` (Playwright e
   Chromium já verificados pelo doctor nesta sessão).
6. **`README.md`** com o índice, a data do schema lido e a nota de que o
   `install.xml` é a fonte.

## Verificação

- `python3 scripts/lint-skin.py` do pacote diagram-design em cada HTML.
- Conferência contra o schema: um script de leitura que extrai `TABLE`/`FIELD`/
  `KEY` dos sete `install.xml` e compara com as tabelas, campos e arestas
  presentes nos HTML — falha se um diagrama citar coluna que não existe ou
  omitir FK declarada. É o que impede o diagrama de envelhecer mentindo.
- Ler os oito `.png` gerados e olhar: texto estourando caixa, aresta cruzando
  rótulo, contraste do `#B0B3B8` sobre `#1E1E1E`, e se o diagrama mestre é
  legível a 100%.
- `git status` no `dev` para confirmar que só `docs/diagram/` e o marker
  apareceram — nenhum arquivo do core do Moodle tocado.

## Fora de escopo

Não reescrevo `docs/data-model/marketplace.md` nem substituo
`docs/architecture/arquitetura-plataforma.svg`. O `README.md` novo aponta para
os dois; consolidar fica para uma decisão sua, depois de ver os diagramas.
