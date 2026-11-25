# ADR-0002 — País ISO na oferta, e uma conta de pagamento por país

**Situação:** Aceita · **Data:** 2026-08-27

## Contexto

Uma empresa que vende no Brasil e na Argentina precisa de **duas contas**: uma
conta de gateway é presa a um país e só recebe na moeda dele. Não há câmbio no
caminho do split.

O `core_payment` escopa conta por **contexto**, e todas as contas de uma empresa
vivem no mesmo — o da categoria dela. O código resolvia assim:

```php
$account = \core_payment\account::get_record([
    'contextid' => $context->id,
    'archived' => 0,
]);
```

`get_record()` devolve **a primeira que aparecer**. Com duas contas, a empresa
passava a receber em ordem de id. O mesmo valia para a moeda, lida do "primeiro
gateway habilitado" — com três gateways na mesma conta, a resposta dependia da
ordem dos ids, o que é o mesmo que dizer que não havia resposta.

Existia trabalho começado num `git stash` (`multipais: siteid na oferta + tabela
de contas por pais`) que usava o **`siteid` do Mercado Pago** — `MLB`, `MLA` — como
chave de país.

O limite externo que molda tudo isto: **`core_payment::get_payable()` não recebe
o usuário.** Valor, moeda e conta são função pura do `itemid`. Uma oferta não
pode ser BRL para um aluno e ARS para outro.

## Decisão

A oferta ganha `country`, em **ISO-3166 alpha-2**. Nasce
`local_marketplace_account` — empresa × país × conta — com índice único em
`(companyid, country)`.

A moeda deixa de ser escolhida e passa a ser **derivada** do país, em
`before_validate()` do persistent. Nenhum caminho de gravação — tela, CLI, teste,
upgrade — consegue produzir "oferta em BRL vendendo na Argentina", que não é
configuração errada e sim configuração impossível.

A chave é ISO e **não** o código de um fornecedor. Asaas e Pagar.me só existem no
Brasil e não têm código de site nenhum; se o núcleo falasse `MLB`, o
`paygw_asaas` teria que aprender o vocabulário do concorrente para responder uma
pergunta que não é do Mercado Pago. A tradução ISO → código do fornecedor fica
confinada no cliente HTTP de cada gateway.

Plano por país continua sendo **oferta separada** — consequência direta de
`get_payable()` não conhecer o comprador.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| `siteid` do Mercado Pago como chave, aproveitando o stash | Amarra o núcleo ao vocabulário de um fornecedor para sempre. Asaas e Pagar.me não têm equivalente |
| País na **empresa**, não na oferta | Impede a mesma empresa de vender em dois países, que é justamente o caso que motivou o trabalho |
| País no **comprador** | `get_payable()` não recebe o usuário. Não é preferência, é o contrato do core |
| Moeda escolhida no formulário, validada contra a conta | Foi o que existia. Fazia o cadastro depender de o vendedor já ter concluído o vínculo, e dava respostas diferentes conforme a ordem dos gateways |
| Uma conta só, com sub-contas por país dentro do gateway | Cada gateway resolve isso de um jeito, e alguns não resolvem. O núcleo voltaria a depender do fornecedor |

## Consequências

**Fica mais fácil:** a empresa abre conta em quantos países quiser; a moeda nunca
diverge do país; o checkout só oferece gateways que operam naquele mercado.

**Fica mais difícil:**

- o vendedor precisa entender que vender em outro país é **outra oferta**, e não
  outro preço na mesma
- o `idnumber` da conta ganha sufixo de país; contas antigas foram adotadas pelo
  upgrade lendo o `siteid` que o vínculo do Mercado Pago já gravava
- a lista de países é a interseção do que os gateways integrados atendem, e
  cresce só quando um gateway novo abre um mercado — um país sem gateway
  produziria oferta que ninguém consegue pagar
- `company::get_payment_account()` passou a exigir o país. Todo chamador teve que
  dizer de qual mercado está falando, e isso é bom: os que não sabiam estavam
  errados

## Como saber que erramos

Se aparecer demanda real de **uma oferta com preço em duas moedas** — e não duas
ofertas —, o modelo está errado e o problema volta para o colo do
`get_payable()`.

Se a lista de países virar um campo livre em vez de uma interseção do que os
gateways atendem, alguém contornou a decisão.
