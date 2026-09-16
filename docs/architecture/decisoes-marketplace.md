# Decisões de arquitetura — marketplace de cursos

Registro do que foi decidido e **por quê**, para que as restrições descobertas
não sejam re-descobertas. Cada seção traz a evidência que sustenta a decisão.

---

## 1. Base: Moodle 5.2, sem IOMAD

O IOMAD foi avaliado e recusado. Medido com
`git diff 152bf2ebbdd 0616692c833` (Moodle 5.0.9 → IOMAD 5.0.9):

```
171 arquivos do core MODIFICADOS
 14 arquivos do core REMOVIDOS   (admin/tool/mfa/factor/sms inteiro)
```

Não é um conjunto de plugins — é um fork. Prenderia o projeto ao Moodle 5.0 e
ao ciclo de release do IOMAD, e remover o fator MFA por SMS é regressão de
segurança não pedida.

O que ele entregaria já existe no 5.2: tema por categoria
(`allowcategorythemes`), payment account escopada por contexto
(`moodle/payment:manageaccounts` é `CONTEXT_COURSE`), e `enrol_fee` apontando
para a conta do vendedor.

**Aproveitado do IOMAD:** o *modelo de dados* da tabela `company` como
checklist de campos, e a técnica de trocar `$CFG->wwwroot` pelo header `Host`
(`iomad-base/lib/setuplib.php:680`) — adaptada para o `config.php`, que roda
antes do `setup.php` e não é arquivo do core.

---

## 2. Empresa com N vendedores, conta Mercado Pago da EMPRESA

A credencial de pagamento pertence à empresa, não à pessoa. Se fosse por
vendedor, o split de um curso escrito a quatro mãos não teria destinatário
definido.

---

## 3. Dois portões estruturais, sem aprovação manual

Qualquer um se cadastra e cria empresa. O que limita não é burocracia:

| Portão | Mecanismo |
|---|---|
| Vender | `company::can_sell()` exige `mpaccount` com status `linked` e token válido |
| Vídeo externo | papel `marketplaceseller` **sem** as capabilities de upload |

### Por que o portão de venda não é uma capability

Capability responde *"tem direito?"*. A pergunta aqui é *"está habilitada a
receber?"* — isso é **estado**, não permissão.

### Por que `CAP_PROHIBIT` e não `CAP_PREVENT`

O vendedor também carrega o papel de usuário autenticado, que **permite**
`repository/upload:view`. No Moodle, quando dois papéis se contradizem no
mesmo contexto, o ALLOW vence o PREVENT. Com `CAP_PREVENT` o portão era de
mentira — verificado em teste, o upload continuava permitido.

Negadas: `repository/upload:view`, `repository/url:view` (baixar de uma URL
para dentro do `moodledata` é o contorno óbvio da primeira) e
`moodle/course:ignorefilesizelimits`.

Não basta `maxbytes`: limita tamanho, não tipo, e um vídeo curto passaria.

---

## 4. Oferta como unidade de venda

O curso deixa de ser o que se vende e passa a ser o que se **libera**, porque
o mesmo curso é vendido em vários formatos:

| Formato | `offertype` / `accessmode` |
|---|---|
| Avulso vitalício | `single` / `lifetime` |
| Acesso por prazo | `single` / `days` |
| Combo de cursos | `bundle` / `lifetime` |
| Assinatura do catálogo | `catalog` / `recurring` |

Em `catalog` a lista de cursos **não** vem de `offer_course`: vem da categoria
da empresa, para que curso publicado depois da compra entre para quem assina.

`entitlement` é a fonte da verdade do acesso: o pagamento diz o que aconteceu
uma vez, o direito diz o que vale agora. `is_active()` confere a data além do
status, senão um direito vencido valeria até o cron rodar.

**Cancelamento revoga na hora** (decisão de negócio). Isolado em
`entitlement::revoke()` — mudar para "acessa até o fim do período pago" é
gravar `timeend` em vez de marcar `cancelled`.

---

## 5. Mercado Pago: Checkout Pro, e por que a assinatura nativa não serve

> **Superada em 2026-08-27** por [ADR-0001](../adr/0001-gateways-alem-do-mercado-pago.md),
> [ADR-0002](../adr/0002-conta-de-pagamento-por-pais.md) e
> [ADR-0003](../adr/0003-quem-cria-a-cobranca-emite-a-nota.md). O que está abaixo
> continua verdadeiro **sobre o Mercado Pago**, e por isso fica: são os limites
> que motivaram procurar outro gateway. O que mudou é que ele deixou de ser o
> único — entraram Asaas e Pagar.me, e o núcleo deixou de conhecer o nome de
> qualquer um deles.

### O split só existe em três checkouts

A documentação é explícita: split funciona com **Checkout Pro, Transparente e
Bricks**. Fora desses três, não é possível.

- Checkout Pro → `marketplace_fee` na preferência
- Transparente / Bricks → `application_fee` no `/payments`
- Exige **token OAuth por vendedor**
- Funciona com Pix, cartão, débito e boleto
- Ordem de dedução fixa: taxa do MP primeiro, comissão da plataforma sobre o
  restante — **o relatório precisa usar o valor devolvido pelo gateway, não
  25% do bruto**

### A assinatura nativa (`preapproval`) NÃO divide o pagamento

O corpo do `POST /preapproval` aceita `preapproval_plan_id`, `reason`,
`payer_email`, `card_token_id` e `auto_recurring`. **Não há `marketplace_fee`
nem `application_fee`.** Somado à restrição dos três checkouts, a conclusão é
que uma assinatura via `preapproval` faria o valor inteiro cair numa única
conta, sem comissão e sem repasse automático.

**Decisão:** recorrência própria. A cada ciclo o cron gera uma preferência
Checkout Pro com `marketplace_fee` e avisa o aluno. O split funciona porque
cada cobrança é avulsa, o aluno escolhe Pix ou cartão, e nenhum dado de cartão
passa pela nossa base. Custo aceito: não é débito automático.

### Não existe débito automático com split no Mercado Pago

> **CORRIGIDO EM 16/09/2026 — a afirmação abaixo estava errada, e ela era a
> premissa que levou ao Asaas.**
>
> "Para pagar com cartão já salvo é preciso capturar o CVV de novo" **não se
> sustenta**. Medido: `POST /v1/card_tokens` com apenas `{"card_id": ...}`,
> **sem `security_code`**, devolve token com `status: active`.
>
> Com isso o triângulo abaixo **tem interseção**, e ela é a linha do meio:
>
> | | Débito automático | Split |
> |---|---|---|
> | `preapproval` (Assinaturas) | sim | **não** — confirmado em 15/09 |
> | **`/v1/payments` + cartão guardado** | **sim** | **sim** |
> | Checkout Pro | não | sim |
>
> A decisão de ter o Asaas **continua válida** — ele foi o único a provar split
> em cada ciclo, e continua sendo. O que muda é que o Mercado Pago deixou de
> estar fora da disputa. Ver [ADR-0012](../adr/0012-duas-assinaturas-e-so-uma-tem-split.md)
> e [`assinatura-no-mercado-pago.md`](assinatura-no-mercado-pago.md).
>
> **Método:** esta afirmação veio de leitura de documentação, e ficou registrada
> como fato por quase um mês. É o mesmo engano do ADR-0001, e a mesma lição:
> afirmação que decide arquitetura precisa de uma chamada à API anexada.


O Transparente foi avaliado como alternativa, já que suporta split via
`application_fee`. Mas a documentação de cartões salvos é explícita: para pagar
com cartão já salvo **é preciso capturar o CVV de novo**, porque o Mercado Pago
não armazena esse dado. Cartão salvo ali é *checkout mais rápido*, não
débito automático — o comprador precisa estar presente.

O triângulo não tem interseção:

| | Débito automático | Split |
|---|---|---|
| `preapproval` (Assinaturas) | sim | **não** |
| Checkout Pro | não | sim |
| Checkout Transparente | não (exige CVV) | sim |

A limitação é do produto, não da nossa arquitetura. Se o aluno vai precisar
agir a cada ciclo de qualquer forma, é melhor que seja num fluxo que divide o
pagamento automaticamente e aceita Pix.

### API de Preferências, não Orders API

O painel do Mercado Pago pergunta qual API a integração usa. A resposta é
**API de Preferências** (`/checkout/preferences`), que é onde o `marketplace_fee`
é documentado para o Checkout Pro.

A Orders API é a API unificada mais nova e provavelmente virará padrão, mas
**não há documentação de split para ela**. Isso não prova que não suporte —
prova que não está documentado, o que para integração financeira dá no mesmo.

Dívida conhecida: se a API de Preferências for depreciada, será preciso migrar.
Por isso as chamadas HTTP ficam isoladas em `paygw_mercadopago\mp_client`, para
que a migração seja num arquivo e não espalhada pelo plugin.

### Por que não reusar `enrol_mpcheckoutpro`

Plugin existente na diretoria oficial, avaliado e recusado:

```
marketplace_fee   0 ocorrências     não faz split
BRL               0 · COP 24×       orientado a Colômbia
pix               4 ocorrências     todas são pix_icon, a API de ícones
```

Além disso é um `enrol_`, não um `paygw_`: criaria um método de matrícula
paralelo, conflitando com o `enrol_marketplace`. Aproveitável dele: o SDK
oficial `mercadopago/dx-php` e o padrão de webhook.

---

## 6. Arquitetura de pagamento

`paygw_mercadopago` separado, sobre o `core_payment` do Moodle, com o
`local_marketplace` como *service provider*. Separa pagamento de regra de
negócio e deixa o gateway reutilizável.

---

## Pendências de negócio

- **Conteúdo hospedado na plataforma**: modelo de cobrança em aberto.
  Armazenamento e banda escalam com a audiência; um percentual fixo não.
  Enquanto não houver decisão, `course_policy` recusa `hostingtype=platform`.
- ~~**Confirmar com o suporte do Mercado Pago** que `preapproval` realmente não
  aceita split.~~ **Feito em 15-16/09/2026**, das duas formas: medido contra a
  API com a aplicação do tipo Assinaturas (cinco formatos, `201`, zero ecos) e
  confirmado pelo suporte, que respondeu que os endpoints de assinatura não
  expõem parâmetro de taxa do integrador.
