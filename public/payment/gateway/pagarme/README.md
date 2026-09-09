# paygw_pagarme

## Para que serve

Cobra pelo Pagar.me — Pix, boleto e cartão — com a comissão da plataforma indo
por **split** na mesma cobrança.

O modelo é o do [ADR-0003](../../../../docs/adr/0003-quem-cria-a-cobranca-emite-a-nota.md):
a cobrança nasce na conta do **vendedor**, porque é ele quem emite a nota, e a
plataforma recebe a comissão como recebedor dentro daquela conta.

```
Vendedor (conta Pagar.me própria)  ──► CRIA a cobrança com a chave dele,
                                       fica com o líquido, emite a nota
Plataforma (recebedor rp_... DENTRO da conta do vendedor)
                                   ──► recebe a comissão via split[]
```

**Consequência que surpreende:** o `recipient_id` da plataforma **é diferente em
cada vendedor**, porque um recebedor é objeto interno a uma conta. Por isso ele
vive na configuração da conta de pagamento, e não nas settings do site — ao
contrário do `platformwalletid` do `paygw_asaas`, que é um só.

## Dependências

Nenhuma obrigatória. O plugin funciona com qualquer componente do
`core_payment`; quando o `local_marketplace` existe, ele consome
`commission_terms_for()`, `recurrence_for()` e `record_sale()`, todos guardados
por `class_exists()`.

## O que precisa configurar

### No plugin — `/admin/settings.php?section=paymentgatewaypagarme`

| Campo | Para quê |
|---|---|
| Ambiente | `sandbox` ou `production`. Decide qual credencial é lida |
| Usuário e senha do webhook | O Pagar.me autentica por **HTTP Basic**, não por header próprio. Os valores saem do painel dele |
| Forma de pagamento | `pix`, `boleto` ou `credit_card` |
| Validade do QR Code | Entre 15 e 60 minutos. Fora disso a API recusa |
| Campo de perfil com o CPF | O Pagar.me exige documento do comprador |
| Chave pública | A `pk_`, usada pelo navegador para tokenizar cartão |

### Na conta do vendedor

O vendedor cola a chave `sk_` dele e o `rp_` da plataforma dentro da conta dele.
Os dois são conferidos na API **antes** de o gateway ser habilitado — uma
credencial que só falha no checkout é uma credencial que falha na frente do
aluno.

### No painel do Pagar.me

Cadastre o endereço do webhook, que a própria tela de configuração imprime:

```
https://<seu-site>/payment/gateway/pagarme/webhook.php
```

### Cifragem

A chave do vendedor é guardada com `\core\encryption`, cujo segredo mora no
`moodledata`. Sem `admin/cli/generate_key.php` executado, o vínculo é recusado
com mensagem apontando o comando.

## A base de cálculo decide o tipo do split

| Base | Tipo enviado | Por quê |
|---|---|---|
| `gross` | `flat`, em centavos, calculado por nós | Determinista, e imune a dúvida sobre o que a API entende por percentual |
| `net` | `percentage` | Deixa o Pagar.me dividir |

São sempre **duas regras**, somando o valor inteiro. A do vendedor carrega
`liable`, `charge_processing_fee` e `charge_remainder_fee`: a documentação exige
que ao menos um recebedor responda pelas três coisas, e a regra fiscal já dizia
que é quem vende.

## Armadilhas

| Sintoma | Causa |
|---|---|
| `404 no Route matched with those values` | `sdx-api.pagar.me` **não existe**. Há um host só; o ambiente vem do prefixo da chave |
| `200` e nenhum QR Code | O `200` é do protocolo. Leia `charge.status` e `gateway_response` |
| *"The item Code is required."* | `items[].code` é obrigatório, e a ausência vira cobrança `failed` dentro de um `200` |
| *"This company it not allowed to create a recipient"* | Conta não habilitada para split. É pedido comercial, não erro de código |
| *"Erro desconhecido no proxy"* | Conta sem adquirente no ambiente de teste |
| `Section error` | A seção é `paymentgatewaypagarme`, não `paygw_pagarme` |
| Webhook sempre `failed` no painel | O `wwwroot` não bate com o host público, e o Moodle responde `303` antes de a requisição entrar no código |

## O que ainda não foi medido

Este plugin foi escrito **pela documentação**, porque a conta de homologação não
processa cobrança. Estas decisões são suposições até o primeiro teste real, e
estão marcadas no código com `NAO MEDIDO`:

- Sobre o que o `percentage` incide — bruto ou líquido. No Asaas é o líquido.
- Se as regras de split precisam somar 100%.
- Se o split vale em **cada ciclo** da assinatura ou só na primeira cobrança.
- Se o estorno reverte o split, e para quais formas de pagamento.
- Se estornar um ciclo cancela a assinatura.

O roteiro para medir tudo isso está em
[`docs/data-validation/pagarme-sandbox.md`](../../../../docs/data-validation/pagarme-sandbox.md),
com o script pronto em `docs/data-validation/scripts/provar-split-pagarme.py`.

## Testar

```bash
docker exec -u 1000:33 -w /var/www/html <stack>-moodle-1 \
  php vendor/bin/phpunit --testsuite paygw_pagarme_testsuite

docker exec -u 1000:33 <stack>-moodle-1 \
  phpcs --standard=moodle -p --report=summary /var/www/html/public/payment/gateway/pagarme
```
