# paygw_asaas

Gateway Asaas para o `core_payment`, com **split** em Pix, boleto e cartão.

É o único gateway do projeto onde o split foi visto funcionando entre duas contas
distintas.

## Para que serve

A cobrança é criada **na conta do vendedor**, com a chave dele. Isso não é
escolha de arquitetura, é regra fiscal: a plataforma não emite nota por outra
empresa. O líquido fica com o vendedor, e o split leva só a comissão para a
carteira da plataforma. Ver
[ADR-0003](../../../../docs/adr/0003-quem-cria-a-cobranca-emite-a-nota.md).

O aluno paga na página do Asaas (`invoiceUrl`), e **o webhook é a fonte da
verdade** — nunca a volta do navegador. Com Pix a aprovação chega depois do
redirecionamento, e o aluno pode fechar a aba antes de voltar.

## Dependências

Nenhuma declarada. Funciona com qualquer componente do `core_payment`.

Com o `local_marketplace` instalado, a comissão vem dele
(`api::commission_terms_for()`); sem ele, cai no padrão de fábrica. É o que
mantém o gateway servindo a qualquer componente, e não só ao marketplace.

## O que precisa configurar

### No plugin — `/admin/settings.php?section=paymentgatewayasaas`

> **Atenção:** a seção é `paymentgateway<nome>`, e não `paygw_<nome>`. Errar dá
> `Section error`.

| Configuração | O que faz |
|---|---|
| `environment` | sandbox ou produção |
| `platformwalletid_<ambiente>` | carteira que **recebe a comissão** |
| `webhooktoken_<ambiente>` | token que autentica o webhook |
| `billingtype` | `PIX`, `BOLETO` ou `CREDIT_CARD` |
| `duedays` | prazo de vencimento |
| `documentfield` | campo do perfil que carrega o CPF do aluno |
| `usecallback` | devolve o aluno ao curso depois de pagar |

Os campos de carteira e token são **por ambiente**, lado a lado de propósito:
trocar de sandbox para produção não pode exigir reconfigurar nada, senão alguém
vai testar apontando para a conta real.

### Na conta do vendedor, no painel do Asaas

Duas coisas que **recusam a cobrança inteira** quando faltam:

1. **O domínio da plataforma** cadastrado em *Minha Conta → Informações*. Como a
   cobrança nasce na conta do vendedor mas a URL de retorno aponta para a
   plataforma, é o nosso domínio que precisa estar na conta dele.
2. **Pix liberado** — subconta pessoa física não libera, mesmo com
   `GET /myAccount/status` respondendo `APPROVED` em tudo. Com CNPJ libera.

### No perfil do aluno

**CPF preenchido.** O Asaas cria cliente sem documento mas recusa a cobrança
dele, e a mensagem é *"É necessário preencher o CPF ou CNPJ do cliente"*.

### Cifragem

A chave do vendedor é guardada cifrada. Instalação sem chave de cifragem faz o
vínculo recusar salvar:

```bash
php /var/www/html/admin/cli/generate_key.php
```

## A base de cálculo decide o campo do split

É a parte mais importante deste plugin:

| Base | Campo enviado | Por quê |
|---|---|---|
| bruto | `fixedValue`, calculado por nós | `percentualValue` é aplicado sobre o `netValue` |
| líquido | `percentualValue` | só o Asaas conhece a taxa da forma de pagamento |

Usar `percentualValue` para a base bruta **é o bug**: em R$ 100 a 25% ele
devolve R$ 24,38 em vez de R$ 25,00, e a plataforma paga parte da taxa do gateway
sem ter escolhido isso. Ver
[ADR-0007](../../../../docs/adr/0007-comissao-sobre-o-bruto.md).

**Limite que vale para as duas bases:** o Asaas recusa a cobrança **inteira** se
o split não couber no líquido. Com comissão de até 10% e taxa de até 3% sobra
folga larga; o caso que aperta é ticket muito baixo, em que a taxa fixa come
quase tudo — R$ 2,00 no Pix não tem de onde tirar comissão.

## Armadilhas

**Baixa manual não prova split.** `receiveInCash` faz o split sair `CANCELLED`
com o valor certo na tela. Dinheiro que não passou pelo gateway não tem como ser
dividido — e foi assim que o erro do Mercado Pago passou despercebido.

**A API exige `User-Agent`.** Sem o cabeçalho ela responde
`user_agent_not_informed` e recusa a requisição, com um 400 que não tem nada a
ver com o corpo. O `asaas_client` já manda; quem chamar por `curl` precisa
lembrar.

**Split para a própria carteira é recusado.** Provar split exige duas contas
distintas — com uma só, o Asaas responde *"Não é permitido split para sua própria
carteira"*.

**Webhook em 401** significa token vazio ou divergente entre o painel e a
configuração do Moodle.

**Chave de homologação tem prefixo `$aact_hmlg_`** e a base também muda. O
`environment_of_key()` deriva o ambiente do prefixo, e ambiente desconhecido cai
em **sandbox** — errar para o sandbox custa um teste que não funciona, errar para
produção custa uma cobrança real.

**Também cobra a assinatura SaaS da empresa** (`paymentarea = 'plan'`) desde
17/09/2026, sem split — a conta recebedora é a da própria plataforma
(`api::get_or_create_platform_account()`), e `errorsamewallet` abre exceção
só para ela via `api::is_platform_account()`. Ver o README do
`local_marketplace`.

## Testar em homologação

Roteiro completo em
[`docs/data-validation/asaas-sandbox.md`](../../../../docs/data-validation/asaas-sandbox.md).
Credenciais em `.devcontainer/secrets/asaas-sandbox.env`, que o `.gitignore`
cobre — **nunca** neste arquivo, que vai para um repositório público.

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_asaas_testsuite
```

69 testes. A camada HTTP é testável sem rede por causa da costura `make_curl()`.
