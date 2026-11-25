# Configuração inicial da plataforma

Ordem em que as coisas precisam ser configuradas numa instalação nova, e por quê
a ordem importa.

Cada plugin tem o próprio `README.md` com o detalhe dos settings. Este documento
é o **encadeamento** entre eles — o que costuma dar errado não é um setting, é
configurar na ordem errada e não entender o sintoma.

---

## Ordem

```
1. Moodle no ar, com HTTPS e e-mail funcionando
2. Chave de cifragem
3. local_marketplace         → comissão, base, país
4. Um gateway                → paygw_asaas ou paygw_mercadopago
5. theme_ldg                 → marca e modo de cor
6. local_partners            → landing e captação
7. enrol + availability + block
8. Tarefas agendadas
```

Os passos 1 e 2 vêm antes de tudo porque **falham em silêncio**: sem eles, os
plugins instalam e configuram normalmente, e o problema só aparece quando alguém
tenta usar.

---

## 1. Antes dos plugins

### HTTPS de verdade

Os gateways recusam callback em HTTP, e o Asaas exige que o **domínio da
plataforma** esteja cadastrado na conta do vendedor.

### E-mail saindo

`/admin/settings.php?section=outgoingmailconfig`

**Teste antes de seguir.** Com SMTP quebrado e a confirmação de e-mail do
`local_partners` ligada, **a fila de candidaturas trava inteira** — todas ficam
`unconfirmed` e ninguém recebe o link. O sintoma é "o formulário não funciona".

### Cadastro próprio, se a landing for pública

`/admin/settings.php?section=manageauths` → habilitar *Self registration* se
quiser que a candidatura anônima gere conta.

## 2. Chave de cifragem

```bash
php /var/www/html/admin/cli/generate_key.php
```

Sem ela, o vínculo da credencial do Asaas **recusa salvar**, e a mensagem não diz
que o problema é a chave.

## 3. `local_marketplace`

`/admin/settings.php?section=local_marketplace_settings`

| Setting | Decisão |
|---|---|
| `defaultfeepercent` | comissão do site — último degrau da cadeia |
| `commissionbase` | `gross` (padrão) ou `net` |
| `defaultcountry` | país da primeira conta de uma empresa nova |

**Decida a base agora.** Ela vale para toda venda que não tenha base própria, e
mudá-la depois **não afeta vendas já feitas** — os termos são fotografados na
venda. Isso é proteção, não limitação: o passado não se reescreve.

Confira também `$CFG->allowcategorythemes` ligado (o `install.php` garante em
instalação nova; ambiente antigo pode estar sem).

## 4. Um gateway

Escolha pelo menos um. Sem gateway a plataforma funciona só para curso gratuito.

### Asaas — recomendado, é onde o split foi provado

`/admin/settings.php?section=paymentgatewayasaas`

> A seção é `paymentgateway<nome>`, não `paygw_<nome>`. Errar dá `Section error`.

1. `environment`: comece em **sandbox**.
2. `platformwalletid_<ambiente>`: a carteira que recebe a comissão.
3. `webhooktoken_<ambiente>`: gere um token e cadastre o **mesmo** no painel do
   Asaas. Divergente, o webhook responde 401 em toda entrega.
4. `billingtype` e `duedays`.
5. `documentfield`: o campo do perfil que carrega o CPF do aluno.

**Na conta de cada vendedor**, no painel do Asaas: cadastrar o **domínio da
plataforma** em *Minha Conta → Informações*. Sem isso, o Asaas recusa a cobrança
inteira — não é o split que falha, é a venda.

### Mercado Pago

`/admin/settings.php?section=paymentgatewaymercadopago`

Precisa de `clientid` e `clientsecret` **da aplicação**, e a integração declarada
no painel como **"API de Preferências"**. Cada vendedor autoriza por OAuth.

> O split do Mercado Pago **nunca foi provado** neste projeto. Ver o README dele.

## 5. `theme_ldg`

1. `/admin/themeselector.php` → selecionar **LDG**.
2. `/admin/settings.php?section=themesettingldg` → logo, favicon, cores, fonte.
3. **Purgue os caches.** Sem isso a logo antiga continua servida, e parece que o
   upload falhou.

Se o site ficar visualmente quebrado logo após selecionar, o problema é
`$THEME->parents` ou `rendererfactory` — não é o design. Ver o README do tema.

## 6. `local_partners`

`/admin/settings.php?section=local_partners_settings`

| Setting | Recomendação |
|---|---|
| `enablelanding` | ligado |
| `frontpagemode` | ligado, se a landing for a home |
| `requireemailconfirmation` | ligado — **exige o passo 1** |
| `enablerecaptcha` | ligado, se houver chave do site |
| `maxperhour` | 3 |
| `unconfirmedretentiondays` | 7 |

### Para a landing virar a home são **três** coisas

1. `frontpagemode` ligado aqui.
2. `theme_ldg` como tema do site.
3. **`enablemyhome` ligado** em `/admin/settings.php?section=navigation`.

A terceira é a que engana: com ela desligada, o core **redireciona o visitante
anônimo para fora de `/` antes de qualquer código de tema rodar**. A landing
nunca chega a ser renderizada, e parece que o plugin não funciona.

**O destino do redirecionamento é a tela de login, e é por isso que ele engana
duas vezes**: quem vê o sintoma procura em *Segurança → Políticas do site*, mexe
no `forcelogin` e não resolve nada, porque não é o `forcelogin`. O trecho é este,
em `public/index.php:79`:

```php
if (empty($CFG->enablemyhome)) {
    if (!isloggedin()) {
        // Non-logged-in users must log in first (forcelogin may be off, but the
        // page they are headed for is disabled, so send them to the login page).
        redirect(get_login_url());
    }
```

O teste, de uma linha, sem abrir o navegador:

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://SEU-SITE/
```

`200` e vazio: a landing está servindo. `303` para `/login/index.php`: é o
Painel. **Isto derruba o SEO inteiro junto** — canônica, `hreflang` e a entrada
principal do sitemap apontam todos para a raiz. Ver
[`../dev/seo-canonical-e-hreflang.md`](../dev/seo-canonical-e-hreflang.md).

### reCAPTCHA

As chaves são **do site**, em `/admin/settings.php?section=manageauths`. O
interruptor do plugin só decide se *este* formulário usa o captcha do core.

### Papel do revisor

Atribua `local/partners:review` a quem vai analisar a fila. Ela não vem em
nenhum papel por herança, de propósito: aprovar cria uma categoria, que é objeto
global.

## 7. Os três satélites

`enrol_marketplace`, `availability_marketplace` e `block_marketplace` **não têm
configuração**. Só o bloco precisa de uma ação:

`/my/indexsys.php` → adicionar o bloco *Marketplace* ao Dashboard padrão. Sem
ele, o aluno não tem onde ver o que vence — e **não há cobrança automática**, ele
precisa agir.

## 8. `format_ldg`, por curso

O formato de curso **não tem configuração de site** — ele se escolhe curso a
curso, em *Configurações do curso → Formato → **Portal LDG***.

Duas coisas precisam estar ligadas para o portal fazer sentido:

| O quê | Onde | Sem isso |
|---|---|---|
| Conclusão de atividades | no curso, e em cada atividade | não há progresso para mostrar, e as barras somem — o que é diferente de aparecerem zeradas |
| `theme_ldg` como tema do site | Aparência → Temas | **o formato aparece sem estilo nenhum** |

**O estilo do formato vive no tema**, em `theme/ldg/scss/ldg/_format.scss`. O
formato entrega marcação semântica e quem pinta é o tema — com outro tema ele
funciona, mas sem colunas, sem cores e sem barra desenhada.

**O índice lateral fica desligado nos cursos com este formato.** É deliberado: a
lista de aulas já cumpre esse papel na mesma tela. Se alguém reclamar que "sumiu
o índice do curso", é isto, e não um defeito.

**Com a edição ligada a tela muda** — a coluna principal volta a ser a pilha de
seções do core, porque arrastar atividade não funciona dentro de um quadro
embutido. Para ver o curso como o aluno vê, desligue a edição.

Detalhes e armadilhas: `public/course/format/ldg/README.md`.

## 9. Tarefas agendadas

`/admin/tool/task/scheduledtasks.php` — confira as duas:

| Tarefa | Se estiver desabilitada |
|---|---|
| `enrol_marketplace\task\sync_entitlements` | aluno com acesso vencido **continua matriculado** |
| `local_partners\task\purge_unconfirmed` | candidatura nunca confirmada acumula nome, telefone e IP para sempre |

E confirme que o cron do sistema está rodando de fato.

---

## Verificação final

| # | Teste | Esperado |
|---|---|---|
| 1 | `/` deslogado | landing de parceria |
| 2 | `/` logado | dashboard |
| 3 | `/local/partners/apply.php` | formulário, com honeypot e sesskey |
| 4 | Enviar candidatura anônima | e-mail com link chega |
| 5 | Abrir o link | candidatura entra na fila |
| 6 | Aprovar | empresa, categoria e papel criados |
| 7 | Comprar em sandbox | cobrança criada, split visível |
| 8 | Webhook | matrícula acontece |
| 9 | `/local/marketplace/report.php?company=<shortname>` | venda com os termos aplicados |
| 10 | `/admin/tool/dataprivacy/dataregistry.php` | nenhum plugin "não implementado" |

O 7 e o 8 são os que importam: **o webhook é a fonte da verdade**, e a volta do
navegador não confirma nada.

---

## Sintomas e causas

| Sintoma | Causa |
|---|---|
| `Section error` no gateway | seção é `paymentgateway<nome>` |
| Asaas recusa a cobrança | domínio da plataforma não cadastrado na conta do vendedor, ou aluno sem CPF |
| Webhook em 401 | token divergente entre painel e Moodle |
| Empresa "sem meio de pagamento" após vincular | o gateway precisa estar **habilitado**, não só ter token |
| Landing não aparece em `/` | `enablemyhome` desligado, ou tema não é o `theme_ldg` |
| **`/` redireciona para a tela de login**, e nada nas configurações de login resolve | `enablemyhome` desligado. **Não é o `forcelogin`** — o core manda o anônimo para o login porque o Painel, e não a home, é o destino que ele calculou. Ver abaixo |
| Fila de candidaturas vazia mesmo com envios | confirmação ligada e SMTP quebrado |
| Logo trocada continua a antiga | falta purgar cache |
| Vínculo do Asaas recusa salvar | falta a chave de cifragem |
| Aluno vencido ainda matriculado | tarefa `sync_entitlements` desabilitada |
