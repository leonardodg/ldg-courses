# local_partners

Captação de empresas parceiras: a landing pública, o formulário de candidatura e
a fila que o administrador aprova.

**Candidatura não é empresa — é um pedido.** A distinção existe porque criar
empresa cria uma **categoria de curso**, que é objeto global do site e aparece na
árvore que todo usuário vê. Por isso a plataforma não tem auto-atendimento: esta
tabela é a fila, e alguém decide.

## Para que serve

```
visitante → landing → candidatura → [confirma e-mail] → fila → aprovação → empresa
```

A aprovação não cria categoria, papel nem membro por conta própria: ela chama
`\local_marketplace\api::create_company()`, que já faz tudo isso. Aqui só se
valida o estado, escolhe o atalho, grava o plano e notifica.

## Dependências

| Depende de | Por quê |
|---|---|
| `local_marketplace` (2026083110+) | a landing exibe planos, e a aprovação cria a empresa |

**Não depende do `theme_ldg`**, de propósito. A dependência é do *tema* para cá,
e é opcional: o tema chama `\local_partners\landing::is_enabled()` por
`class_exists()`, e cai no marketing do Moove quando este plugin não existe.
Assim o `moodle-plugin-ci` consegue instalar o tema sozinho.

## O que precisa configurar

`/admin/settings.php?section=local_partners_settings`

| Configuração | Padrão | O que faz |
|---|---|---|
| `enablelanding` | ligado | liga a landing pública |
| `frontpagemode` | — | usa a landing como home para visitante anônimo |
| `requireemailconfirmation` | ligado | exige confirmar o e-mail antes de entrar na fila |
| `enablerecaptcha` | desligado | usa o reCAPTCHA do core neste formulário |
| `maxperhour` | `3` | limite de candidaturas por IP por hora |
| `unconfirmedretentiondays` | `7` | prazo até apagar candidatura nunca confirmada; `0` desliga |

### Para a landing virar a home

Precisa de **duas** coisas, e a segunda costuma ser esquecida:

1. `local_partners/frontpagemode` ligado aqui.
2. `theme_ldg` como tema do site — é ele que sobrescreve `layout/frontpage.php`.

Com outro tema a landing continua existindo em `/local/partners/index.php`, mas
`/` mostra a frontpage padrão.

**`enablemyhome` desligado quebra isto** — *Administração do site → Aparência →
Navegação → Painel ativado*. O sintoma engana duas vezes: o core redireciona o
visitante anônimo para fora de `/` **antes** de qualquer código de tema rodar,
então a landing nunca chega a ser renderizada; e o destino é a **tela de login**,
o que faz procurar em *Segurança → Políticas do site* e mexer no `forcelogin`,
que não tem nada a ver. O trecho é `public/index.php:79`:

```php
if (empty($CFG->enablemyhome)) {
    if (!isloggedin()) {
        // Non-logged-in users must log in first (forcelogin may be off, but the
        // page they are headed for is disabled, so send them to the login page).
        redirect(get_login_url());
    }
```

Diagnóstico de uma linha:

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://SEU-SITE/
```

`200` e vazio: servindo. `303` para `/login/index.php`: é o Painel, não o login.
E isso derruba a descoberta por buscador inteira junto — canônica, `hreflang` e
sitemap apontam todos para a raiz.

**Na home o tema não desenha cromo nenhum.** A landing já traz a própria barra de
seções e o próprio rodapé, e enquanto o layout de frontpage também montava a
navbar e o rodapé do tema, quem abria a raiz do domínio via duas barras e dois
rodapés, um dentro do outro. A página servida em `/` é idêntica à de
`/local/partners/index.php` — e um cenário behat conta os elementos para que
continue sendo.

### Para a confirmação de e-mail funcionar

O site precisa **conseguir enviar e-mail** (SMTP configurado em
`/admin/settings.php?section=outgoingmailconfig`). Com SMTP quebrado e
`requireemailconfirmation` ligado, **a fila trava inteira**: toda candidatura
anônima fica em `unconfirmed` e ninguém recebe o link.

### Para o reCAPTCHA funcionar

Precisa das chaves **do site** (`$CFG->recaptchapublickey` e
`recaptchaprivatekey`, em `/admin/settings.php?section=manageauths`). O
interruptor daqui só decide se *este* formulário usa o captcha do core — ele não
reimplementa nada. Existe porque as chaves são globais: sem o interruptor local,
desligar o captcha aqui obrigaria a apagar chave que a tela de cadastro de
usuário do Moodle também usa.

### Capacidade

`local/partners:review` — ver a fila e decidir. `archetypes` vazio de propósito:
aprovar cria objeto global, e isso não pode cair em papel por herança.

## Os três caminhos de envio

É a parte com mais sutileza do plugin.

| Situação | Status inicial | Confirma? | Dono na aprovação |
|---|---|---|---|
| autenticado | `pending` | não | o próprio usuário |
| anônimo, confirmação ligada | `unconfirmed` | sim, por link | conta criada ou casada por e-mail |
| anônimo, confirmação desligada | `pending` | não | conta criada ou casada por e-mail |

**Usuário autenticado tem o e-mail digitado ignorado** — vale o do perfil.
Aceitar outro criaria uma candidatura que parece de terceiro, e o dono da empresa
sai daí.

**`unconfirmed` não entra na fila.** Enquanto o e-mail não for provado, aquilo
não é uma candidatura: é o que alguém digitou. Também não notifica revisor.

**Só `pending` bloqueia duplicidade.** Uma `unconfirmed` não pode bloquear: quem
não recebeu o e-mail precisa poder tentar de novo, e um envio nunca confirmado
trancaria a pessoa para sempre. O reenvio substitui a anterior.

Quando a conta do dono precisa ser criada, ela nasce com senha aleatória
desconhecida e recebe **link de redefinição** — nunca senha em texto no e-mail.

## Anti-robô, em três camadas

Da mais confiável à mais frágil, porque proteção que depende de configuração
estar certa não é proteção:

1. **Sempre ligada** — limite por IP/hora e recusa de `pending` duplicada por
   e-mail ou CNPJ. Não depende de nada externo.
2. **Sempre ligado** — honeypot (campo `fax`, escondido por CSS). Preenchido:
   não grava nada e mostra a página de obrigado, sem dar pista ao robô.
3. **Quando configurado** — reCAPTCHA, e só para visitante anônimo.

Há também limite de tamanho por campo, validado no servidor: sem ele, uma
submissão de 5 000 caracteres virava `dml_write_exception` — HTTP 500 numa página
pública.

## Privacidade

O formulário é público e anônimo, então esta tabela acumula nome, e-mail,
telefone e IP de quem talvez nem exista.

- **Candidatura de visitante anônimo** não tem `userid` e não aparece na
  exportação de ninguém. Quem a remove é a tarefa `purge_unconfirmed` (o que
  nunca foi confirmado) ou o administrador.
- **Candidatura de usuário autenticado** grava `userid` e **é dado pessoal
  dele** — exportável e apagável. Num pedido de exclusão: a não aprovada some
  inteira; a aprovada perde nome, e-mail, telefone, mensagem e IP, mas a linha
  fica, porque existe uma **empresa** criada a partir dela.

## Onde mora o estilo

**No plugin, em `styles.css`** — e essa é a diferença que sustenta tudo o mais.
O `theme_config` varre `styles.css` de todo plugin e o injeta na CSS compilada
de **qualquer** tema, então a landing e o cadastro renderizam igual sob
`theme_ldg`, `theme_boost` e `theme_moove`. Enquanto o estilo morava em
`theme/ldg/scss/ldg/_landing.scss`, as mesmas páginas saíam cruas nos outros
dois.

Isso é uma **exceção deliberada** à regra do projeto de que o tema pinta e o
plugin entrega marcação. A regra continua valendo para o `format_ldg`; aqui ela
não serve, porque o requisito é justamente não depender do tema.

Consequências que valem conhecer:

- **Todo seletor carrega o escopo `.ldgp`.** A CSS de plugin entra *antes* da do
  tema, e em empate de especificidade o tema vence. Uma regra sem o escopo
  funciona sob Boost e desaparece sob o `ldg`, em silêncio.
- **Os tokens vivem em `.ldgp`, nunca em `:root`.** O `:root` é o `<html>`, e o
  atributo de modo de cor é escrito no wrapper.
- **As fontes são servidas pelo próprio plugin**, e não pelo Google Fonts: a
  landing é pública, e requisição a terceiro numa página pública entrega o IP de
  todo visitante a quem não precisa dele.

> **Editou o `styles.css` e nada mudou na tela? Suba o `version.php`.** O
> `purge_caches` **não** invalida CSS de plugin. É a armadilha que mais custa
> tempo aqui, e o sintoma é sempre o mesmo: "a correção não funcionou".

## Modo claro e escuro

A página nasce **escura**, resolvida no servidor a partir da preferência
`dark-mode-on` — a mesma chave que o `theme_ldg` e o `theme_moove` usam, então a
escolha vale nos dois sentidos.

O alternador existe porque **o `theme_boost` do 5.2 não tem nenhum**, e o
público desta página é o visitante anônimo, que também não tem preferência de
usuário para guardar: para ele, a escolha vai no `localStorage`, e um `<script>`
inline corrige o atributo antes da pintura para não piscar.

## Descoberta por buscador

`classes/seo.php` monta `description`, `robots`, `canonical`, Open Graph,
Twitter, `hreflang` por idioma instalado, e um grafo JSON-LD com `Organization`,
`WebSite`, `WebPage`, `FAQPage` e `Service` com as ofertas.

**Nada ali é inventado.** Preço e moeda saem do banco, o FAQ do schema é o mesmo
da tela, e todo campo de marca que ninguém preencheu simplesmente não é
publicado. Os campos de marca ficam em `seo::brand()`, vazios: razão social,
identificador fiscal, endereço, telefone, e-mail e perfis oficiais.

## Armadilhas

**`MESSAGE_DEFAULT_LOGGEDIN` não existe no Moodle 5.2** e derruba o upgrade em
`db/messages.php`. Use `MESSAGE_DEFAULT_ENABLED`.

**O elemento `recaptcha` do moodleform não se valida sozinho.** O formulário
precisa chamar `verify()` explicitamente — sem isso o captcha é decorativo, e o
sintoma é não haver sintoma nenhum.

**Aprovar é idempotente por `companyid`.** Duas submissões não podem produzir
duas categorias, e a checagem de estado vem **antes** de qualquer escrita.

**Nunca cite uma tag de mustache dentro de um comentário de mustache.** O
comentário termina no **primeiro** `}}`, e todo o resto do docblock — incluindo o
*Example context* — sai como parágrafo na página pública. Já aconteceu, e só a
captura de tela mostrou.

**`addGroup` com `$appendName = true` renomeia o campo em silêncio.** O `planid`
viraria `plangroup[planid]`, e `api::submit()` gravaria `null` em toda
candidatura.

**O `.d-flex` do Bootstrap é `display: flex !important`.** Uma grade declarada
por cima é ignorada, e o `getComputedStyle` mostra as duas coisas ao mesmo tempo.

**Com `cachejs` ligado o Moodle serve o AMD compilado.** Sem `amd/build/`, o
módulo não roda — botão que não faz nada, sem erro no console. `npx grunt amd`
gera, e os arquivos vão versionados.

## Testes

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite local_partners_testsuite
```

**56 testes**: os três caminhos de envio, a confirmação, a aprovação, a
privacidade, o contexto da landing e os dados estruturados.

E **20 cenários behat**, dos quais nove exigem navegador de verdade porque
**medem a tela** — coluna única no celular, três colunas no desktop, a barra
grudando abaixo do cabeçalho, o alvo de toque de 44px, e o campo-armadilha fora
da tela sob `boost`, `moove` e `ldg`:

```bash
moodev up --full
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --profile=chrome --tags "@local_partners"
```

A conferência visual contra o mockup, que número não decide, está em
[`docs/data-validation/local-partners-layout.md`](../../../docs/data-validation/local-partners-layout.md).
