# paygw_mercadopago

Gateway Mercado Pago para o `core_payment`: **venda avulsa** por Checkout Pro com
`marketplace_fee`, e **assinatura recorrente** por cobrança de ciclo com
`application_fee`.

> **São duas coisas diferentes, e a diferença é medida, não estilística.** O
> `POST /preapproval` — a API de Assinaturas do Mercado Pago — **não tem campo de
> comissão nenhum**: aceita `marketplace_fee`, `application_fee` e `marketplace`,
> na raiz e dentro de `auto_recurring`, devolve `201` nos cinco formatos e
> descarta todos. Medido em 15/09/2026 com a aplicação do tipo certo. Por isso a
> assinatura com comissão aqui **não é um `preapproval`**: é uma sequência de
> cobranças em `/v1/payments`, disparadas por nós, sobre um cartão guardado no
> Mercado Pago. Ver [ADR-0012](../../../../docs/adr/0012-duas-assinaturas-e-so-uma-tem-split.md).

## Três aplicações, e o modelo declarado importa

No Mercado Pago "aplicação" não é detalhe de cadastro: o **modelo de integração
declarado no painel muda o comportamento da API em silêncio**. Declarar
"Checkout Transparente" faz o `marketplace_fee` da preferência ser ignorado sem
erro nenhum — venda sem comissão que não acusa nada.

| Tipo | Modelo no painel | Evento do webhook | Para que |
|---|---|---|---|
| `preferences` | API de Preferências | `payment` | venda avulsa, `marketplace_fee` |
| `subscriptions` | Assinaturas | `subscription_*` | assinatura **sem** comissão (mensalidade B2B) |
| `bricks` | Checkout Bricks | `payment` | ciclo de assinatura **com** comissão |

Cada uma exige o **seu próprio OAuth**: o token que um vendedor emite
autorizando Preferências não carrega Bricks. O `redirect_uri` é o mesmo nas três
— o tipo viaja na sessão, e não na URL, porque o Mercado Pago exige que o
endereço case exatamente com o cadastrado e três URIs seriam três chances de
errar uma letra. Ver [ADR-0013](../../../../docs/adr/0013-uma-aplicacao-por-tipo-de-integracao.md).

## Para que serve

O vendedor autoriza a nossa aplicação por **OAuth**, e é essa autorização que
permite o `marketplace_fee` voltar para a plataforma. A preferência de pagamento
é criada com o token do vendedor: o dinheiro nasce na conta dele, e a comissão é
retida na origem.

O webhook manda **só o ID**, nunca o status — e isso é de propósito do Mercado
Pago. Confiar no corpo da notificação permitiria a qualquer um POSTar "aprovado"
no nosso endpoint; o status é sempre consultado na API.

## Dependências

Nenhuma declarada. Com o `local_marketplace` instalado a comissão vem dele; sem
ele, cai no padrão de fábrica.

## O que precisa configurar

`/admin/settings.php?section=paymentgatewaymercadopago`

| Configuração | O que faz |
|---|---|
| `clientid` / `clientsecret` | credenciais **da aplicação**, não da conta. Uma por tipo; **iguais nos dois ambientes** |
| `publickey` / `publickeytest` | chave pública de cada aplicação, para montar os campos do cartão. Quem escolhe entre as duas é o `testmode` |
| `webhooksecret` | assinatura secreta **por aplicação** |
| `cardcapture` | onde o cartão é digitado: `brick`, `direct` ou `native` |
| `platformsite` | site do Mercado Pago da plataforma (MLB, MLA…) |
| `testmode` | usa `test_token` no OAuth **e** escolhe a chave pública |

**Sem HTTPS, `direct` e `native` não valem** — o código cai em `brick` sozinho e
a tela diz por quê. Não é aviso, é recusa: "seguro desde que a configuração
esteja certa" é o desenho que este projeto recusa. O custo de cada modo está em
[`pci-dss-captura-de-cartao.md`](../../../../docs/legal/pci-dss-captura-de-cartao.md).

Não há campo de comissão aqui. A comissão é regra do marketplace, e vive em
`local_marketplace/defaultfeepercent`; sem o marketplace instalado, o plugin cai
num padrão de fábrica de 25%. Existiu um `defaultfeepercent` nesta tela até
08/09/2026, mas **nenhuma linha de código o lia** — o valor já havia sido
migrado, e o texto de ajuda ainda dizia que a comissão incidia sobre o líquido,
o contrário do que a seção abaixo explica.

No painel do Mercado Pago, a integração precisa ser declarada como **"API de
Preferências"** — o `marketplace_fee` vai na preferência, e não no pagamento.

## A comissão aqui é sempre sobre o bruto, e não por escolha

O `marketplace_fee` é **valor absoluto**, e a taxa do Mercado Pago só é conhecida
depois que o pagamento acontece. Não há como cobrar um percentual de um número
que ainda não existe.

Consequência: quando o marketplace está configurado com base **líquida**, a venda
por aqui sai sobre o **bruto** e a linha grava `feebase = 'gross'`. A
configuração diz a intenção, a venda diz o fato — expor a divergência é melhor
que gravar a intenção e deixar o relatório mentir. Ver
[ADR-0007](../../../../docs/adr/0007-comissao-sobre-o-bruto.md).

**Não confunda com a ordem de dedução.** A taxa do Mercado Pago sai primeiro, do
lado do vendedor, e o `marketplace_fee` sai do que sobra. Isso não muda quanto a
plataforma recebe — muda quem absorve a taxa. Se não sobrar saldo para o
`marketplace_fee`, quem recusa é o Mercado Pago.

## Limitações conhecidas do gateway

**Não há recorrência com split.** `preapproval` não aceita `marketplace_fee`, e o
Transparente com cartão salvo exige CVV a cada cobrança. Assinatura neste projeto
é **acesso com prazo mais aviso de vencimento**, nunca débito automático. Foi o
que motivou procurar um segundo gateway — ver
[ADR-0001](../../../../docs/adr/0001-gateways-alem-do-mercado-pago.md).

**São três partes no split:** comprador, vendedor e a **aplicação**. Misturar
ambientes — aplicação de produção com vendedor de teste — é recusado com "uma das
partes é de teste". O `test_token` no OAuth resolve.

## Armadilhas

**Empresa aparece "sem meio de pagamento" mesmo após vincular:**
`account::is_available()` exige o gateway **habilitado**, e não só o token
presente.

**A taxa do MP não é reportada de volta.** Ela varia por meio de pagamento e
prazo de repasse. O relatório não a exibe, e não deve inventá-la: o líquido do
vendedor é o do extrato dele.

**O split foi provado em 08/09/2026**, com duas contas distintas e dinheiro real.
Pagamento `178004552586`, Pix: R$ 5,00 brutos − R$ 0,05 de taxa do Mercado Pago −
R$ 1,25 de `application_fee` = R$ 3,70 para o vendedor, e os R$ 1,25 no extrato
da plataforma. O `collector_id` foi o vendedor, e não o dono da aplicação — que é
a condição sem a qual o número não significa nada. Roteiro em
[`docs/data-validation/mercadopago-split.md`](../../../../docs/data-validation/mercadopago-split.md).

**O vendedor não precisa de CNPJ.** A conta que vendeu naquela rodada é pessoa
física — `/users/me` devolve `identification.type` vazio e sem a tag `business`.
Quem precisa ser pessoa jurídica é a **plataforma**, dona da aplicação, porque é
ela que recebe a comissão. Continua sem prova o caso inverso, vendedor pessoa
jurídica, que é o convencional e não aparenta risco.

Foi essa rodada que confirmou a ordem de dedução descrita acima: a taxa do MP
saiu primeiro, e o `marketplace_fee` saiu do que sobrou.

**O sandbox não serve para provar isto.** Com `purpose: wallet_purchase` o
Checkout Pro entra em loop de redirecionamento no login de carteira; sem ele, a
tela devolve erro. Nos dois casos `payments/search` devolve zero pagamentos — não
há o que conferir. Não perca tempo ali: a prova é com conta real e valor mínimo.

**Continue desconfiando de sucesso sem erro.** O `marketplace_fee` já pareceu
funcionar sem transferir nada, quando vendedor e marketplace eram a mesma conta.
A pergunta que decide é sempre `collector_id != dono da aplicacao`, e é por isso
que o script da prova aborta sozinho quando os dois coincidem.

## Testes

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_mercadopago_testsuite
```

24 testes: PKCE, moeda por país, URL de autorização, a camada HTTP e o
`marketplace_fee`.

A costura `make_curl()` foi replicada do `asaas_client` em 08/09/2026, e é o que
tornou testável o que antes só era exercitável batendo na API: o corpo enviado, o
mapeamento de erro e o `test_token` do OAuth. Ela é **estática**, diferente da do
Asaas, porque o `post_json()` do fluxo OAuth também é — e a chamada usa
`static::`, senão o late static binding não alcança a subclasse falsa e o teste
volta a bater na rede sem avisar.

Junto veio a extração de `fee_for()` e `build_preference_body()` do
`start_payment()`. O `marketplace_fee` é o único número deste plugin que move
dinheiro, e ficou sem teste enquanto só existia dentro de um método que precisa
de banco, sessão e rede para rodar.

Continua fora do teste automatizado: `process_notification()`,
`locate_transaction()` e as três páginas de OAuth.


---

## Assinatura: como funciona, e o que isso custa em operação

**A assinatura não começa no Mercado Pago.** Um ciclo só pode ser cobrado com
cartão guardado, e o cartão só vira token no navegador — medido: o
`/v1/card_tokens` devolve **403** para token de acesso, e só a `public_key`
tokeniza. Então o aluno vai para o `subscribe.php`, uma página nossa.

O fluxo do ciclo 1, e a ordem não é arbitrária:

1. o navegador produz **um** token (Brick ou campos do MP);
2. o servidor cria o cliente e **guarda o cartão**, consumindo o token;
3. o servidor gera um **segundo** token a partir do `card_id`, **sem CVV**;
4. cobra com `application_fee` e `payer.type=customer`.

O passo 3 é o que torna a cobrança automática possível, e derruba uma frase que
este projeto carregava como fato: `POST /v1/card_tokens` com apenas
`{"card_id": ...}` devolve token `active`. **Não pede código de segurança.**

E o passo 4 tem uma armadilha medida: **mandar o cliente junto de um token
recém-digitado faz a cobrança ser RECUSADA**, com `cc_rejected_other_reason` —
que chega disfarçado de problema com o cartão. O cliente só entra quando o token
nasceu do cartão dele. As duas formas estão em `build_payer()`.

### O que muda na operação

**Cron parado é assinatura que não cobra.** No Asaas o gateway cobra sozinho e a
tarefa de lá só concilia; aqui quem dispara é a `charge_due_cycles`. É falha de
que ninguém reclama, porque o sintoma é o vendedor recebendo menos — e não o
aluno perdendo acesso. **Monitore essa tarefa como se monitora dinheiro.**

**Cada ciclo é uma linha própria**, ligada pelo `subscriptionid`, e o ciclo
seguinte **copia os termos** do anterior — nunca resolve a comissão de novo.
Mudar a configuração não pode reescrever contrato em curso.

**`cancel_recurring` não cancela nada no Mercado Pago**, porque não há o que
cancelar: marca `subscriptionstatus` e a tarefa para de disparar. O cartão
guardado **não** é apagado — ele não cobra nada sozinho, e apagar obrigaria o
aluno a digitar tudo de novo se mudasse de ideia.

**`pending_invoice` devolve o `subscribe.php`**, e não uma fatura: quando a
cobrança automática falha não sobra documento para alguém pagar. O que resolve é
o aluno informar um cartão que funcione.

## O que continua sem prova

- **A travessia do `application_fee` entre contas distintas na assinatura.** O
  mecanismo está medido (pagamento `1352076103`, aprovado, com a comissão em
  `fee_details`), mas naquele pagamento o `collector_id` era o dono da
  aplicação. Prova de split no Mercado Pago é com conta real.
- **A cobrança do cartão guardado**: no arranjo degenerado do sandbox devolve
  `500 internal_error`.
- **A taxa de aprovação** de cobrança iniciada pelo estabelecimento. O suporte
  do MP avisa que pode ser pior que a do motor de assinaturas — e prova de
  tokenização sem CVV **não** é prova de aprovação.
- **`/v1/advanced_payments`**: `403` de política em quatro aplicações, teste e
  produção. Precisa de liberação comercial.

Roteiro e ids: [`mercadopago-assinatura.md`](../../../../docs/data-validation/mercadopago-assinatura.md).
