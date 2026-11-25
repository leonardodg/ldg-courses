# ADR-0010 — Vendedor pessoa física é aceito no Mercado Pago

**Situação:** Aceita · **Data:** 2026-09-08

## Contexto

O split do Mercado Pago passou meses **sem prova**. Numa tentativa anterior,
vendedor e marketplace eram a mesma conta: o `marketplace_fee` foi aceito, o
pagamento aprovou, e nada foi transferido — sem erro nenhum, que é o pior tipo de
falso positivo.

Ao montar a prova definitiva, ficou uma pergunta em aberto que ninguém sabia
responder: **o vendedor precisa ser pessoa jurídica?** No Asaas a resposta é sim
para a plataforma — uma conta CPF não cria subcontas. Supôs-se que o Mercado Pago
teria restrição parecida do lado do vendedor, e o plano previa uma rodada
dedicada a testar isso com uma conta CPF, depois de uma rodada de controle com
CNPJ.

Em 2026-09-08 a rodada de controle rodou e o split funcionou:

    pagamento .. 178004552586 | approved/accredited | pix
    bruto ...... R$ 5,00 | taxa do MP R$ 0,05 | liquido do vendedor R$ 3,70
    SPLIT ...... application_fee | R$ 1,25 | collector 1233186727 != app 3675841384
    extrato .... R$ 1,25 na conta da plataforma | dinheiro a liberar

Só **depois** se descobriu que a conta vendedora (`1233186727`), tratada como
"empresa nova", é **pessoa física**. A verificação é objetiva, em `/users/me`:

| Conta | `identification.type` | tags |
|---|---|---|
| Plataforma `3675841384` | `CNPJ` | inclui `business` |
| Vendedor `1233186727` | vazio | **sem** `business` |

Ou seja: a rodada que servia de controle respondeu, por acidente, a pergunta
reservada para a rodada seguinte — e respondeu com dinheiro real.

## Decisão

**O vendedor no Mercado Pago pode ser pessoa física.** Não vamos exigir CNPJ de
quem vende, nem no cadastro de empresa nem no vínculo do gateway.

O CNPJ continua **obrigatório para a plataforma**, dona da aplicação: é ela quem
recebe o `application_fee`, e é a conta `business` que sustenta o modelo de
marketplace.

A rodada 2 do roteiro, que testaria vendedor CPF, fica **cancelada por
redundância**.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Exigir CNPJ do vendedor por precaução | Restringiria quem pode vender sem nenhum fato que sustente a restrição — e agora há um fato que a contradiz |
| Repetir a rodada com a outra conta pessoa física | Mesma configuração, mesmo resultado esperado. Gasta dinheiro real para reconfirmar o já provado |
| Deixar a pergunta em aberto no roteiro | O plano previa uma rodada só para isso. Manter "pendente" o que já foi respondido faz a próxima pessoa repetir o gasto |

## Consequências

**Mais fácil:** o funil de vendedores fica maior. Professor autônomo, MEI ainda
sem CNPJ ativo, pessoa física que quer publicar um curso — todos podem receber,
e a comissão da plataforma continua saindo na origem.

**Mais difícil:** a fronteira fiscal fica menos nítida. O `docs/adr/0003` fixa
que quem cria a cobrança emite a nota; com vendedor pessoa física, a obrigação
acessória do outro lado muda de natureza, e a plataforma não deve palpitar sobre
isso. Vale um aviso no cadastro, não uma trava.

**Continua sem prova:** o caso **inverso** — vendedor pessoa jurídica —, que é o
convencional e não aparenta risco, mas nunca foi exercitado aqui. Se o Mercado
Pago aceita a conta mais restrita, espera-se que aceite a menos restrita.

## Como saber que erramos

Um `400` na criação da preferência, ou uma recusa no pagamento, mencionando o
documento do vendedor ou o tipo da conta. Hoje a mensagem que aparece quando algo
está errado nas partes é *"uma das partes é de teste"*, que fala de ambiente e
não de documento — são coisas diferentes, e confundir as duas custaria tempo.

Sinal secundário: o Mercado Pago passar a exigir conta `business` para receber
`application_fee`. Isso apareceria como comissão que some do `fee_details` sem
erro — e é exatamente por isso que o script da prova aborta quando não encontra
`application_fee`, em vez de reportar sucesso.

## Método, que vale mais que a decisão

O tipo de cada conta foi assumido pelo rótulo do cadastro, e não verificado.
A rodada foi desenhada em cima dessa suposição. Deu certo por sorte: se a prova
tivesse **falhado**, o resultado teria sido lido como "CNPJ é obrigatório" —
conclusão oposta à verdadeira, e cara de desfazer.

**Confira o tipo da conta com `/users/me` antes de desenhar a rodada**, não
depois de concluí-la.
