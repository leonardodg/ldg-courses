#!/usr/bin/env python3
"""Prova o split no sandbox do Pagar.me, do zero, sem passar pelo Moodle.

Por que existe: o split e o coracao do modelo de marketplace, e cada gateway
mente de um jeito diferente sobre ele. No Mercado Pago o preapproval aceitou
marketplace_fee, devolveu 201 e descartou o campo. No Asaas o PUT /subscriptions
comum aceitou creditCard e nao guardou nada. Nos dois casos a resposta foi 2xx.

O que este script mede, e o motivo de cada medicao estar aqui:

    0. criar os dois recebedores          sem eles nao existe split
    1. a cobranca com split               o POST nao e prova
    2. ler a cobranca de volta            e ver que ela NAO conta o split
    3. por que o charge.splits nao serve  medido: vem null mesmo funcionando
    4. os payables                        a unica fonte da verdade
    5. os saldos                          fecha a conta

ESTADO EM 11/09/2026: o script roda ate o fim e PROVA o split.

    cobranca ... R$ 100,00 | paid
    vendedor ... amount R$ 75,00 | taxa R$ 4,49 | liquido R$ 70,51
    plataforma . amount R$ 25,00 | taxa R$ 0,00 | liquido R$ 25,00

O percentual do Pagar.me incide sobre o BRUTO - 25% de R$ 100,00 deram
exatamente R$ 25,00. E o oposto do Asaas, onde percentualValue incide sobre o
liquido.

Tres armadilhas que este script ja tropecou, e por isso trata:

  * `charge.splits` volta NULL mesmo quando o split acontece. Procurar ali
    concluiria que falhou. A verdade esta em GET /payables?recipient_id=.
  * O payable nasce cerca de 16 SEGUNDOS depois do pagamento. Ler uma vez so,
    logo apos a cobranca, mostra zero e parece falha.
  * `amount` do split precisa ser INTEIRO. Um 75.0 float e recusado com HTTP
    400 "The request is invalid." sem dizer qual campo.

O que ainda NAO da para medir aqui: Pix (a conta responde 400 "Sem ambiente
configurado para este tipo de transacao") e assinatura com split (o POST
/subscriptions recusa o campo em todos os formatos). Por isso a cobranca
abaixo e de cartao.

Como usar:

    set -a && . .devcontainer/secrets/pagarme-sandbox.env && set +a
    python3 docs/data-validation/scripts/provar-split-pagarme.py

Sem dependencia externa - so a biblioteca padrao do Python.
"""

import base64
import json
import os
import random
import sys
import time
import urllib.error
import urllib.request

# O Pagar.me v5 nao tem host de homologacao. Medido em 09/09/2026:
# sdx-api.pagar.me responde 404 "no Route matched with those values". O
# ambiente vem do PREFIXO DA CHAVE - sk_test_ e sandbox, sk_ e producao.
BASE = os.environ.get("PAGARME_BASE_URL", "https://api.pagar.me/core/v5").strip()
KEY = os.environ.get("PAGARME_SECRET_KEY", "").strip()
PRICE = 100.00
COMMISSION = 25.0

if not KEY:
    sys.exit("defina PAGARME_SECRET_KEY com a chave secreta do sandbox")
if not KEY.startswith("sk_test_"):
    sys.exit(
        f"a chave comeca com {KEY[:8]!r} e nao com 'sk_test_'.\n"
        "Este script cria cobrancas: com chave de producao seria dinheiro real."
    )


def erro(mensagem):
    """Encerra com mensagem, porque seguir daria um resultado que nao prova nada."""
    print("\nABORTADO: " + mensagem, file=sys.stderr)
    sys.exit(1)


def call(method, path, body=None):
    """Uma chamada a API, com o erro do Pagar.me legivel.

    A autenticacao e Basic com a chave secreta como usuario e senha vazia -
    nao e Bearer, e nao e header proprio como o access_token do Asaas.
    """
    token = base64.b64encode(f"{KEY}:".encode()).decode()
    request = urllib.request.Request(
        BASE + path,
        method=method,
        data=json.dumps(body).encode() if body is not None else None,
        headers={
            "Authorization": "Basic " + token,
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "ldg-courses split proof",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=40) as response:
            return json.loads(response.read() or "{}")
    except urllib.error.HTTPError as error:
        detalhe = error.read().decode(errors="replace")
        try:
            corpo = json.loads(detalhe)
            # O Pagar.me as vezes devolve uma string crua no lugar do objeto -
            # "The request is invalid." sem mais nada. Um json.loads sobre isso
            # da certo e devolve str, entao o isinstance nao e paranoia.
            if isinstance(corpo, dict):
                detalhe = corpo.get("message") or detalhe
                if corpo.get("errors"):
                    detalhe += " | " + json.dumps(corpo["errors"], ensure_ascii=False)
        except ValueError:
            pass
        sys.exit(f"\n  FALHOU {method} {path}\n  HTTP {error.code} - {detalhe}\n")


def cpf_sintetico():
    """CPF com digito verificador valido, para o sandbox aceitar.

    Numero inventado de proposito: nao pertence a ninguem e so existe no
    ambiente de homologacao.
    """
    base = [random.randint(0, 9) for _ in range(9)]
    for peso_inicial in (10, 11):
        total = sum(d * (peso_inicial - i) for i, d in enumerate(base))
        digito = (total * 10) % 11
        base.append(0 if digito == 10 else digito)
    return "".join(map(str, base))


def cnpj_sintetico():
    """CNPJ com digito verificador valido."""
    base = [random.randint(0, 9) for _ in range(8)] + [0, 0, 0, 1]
    for pesos in ([5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
                  [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]):
        resto = sum(d * p for d, p in zip(base, pesos)) % 11
        base.append(0 if resto < 2 else 11 - resto)
    return "".join(map(str, base))


def passo(numero, texto):
    print(f"\n[{numero}] {texto}")


def recebedor(nome, documento, tipo):
    """Corpo de POST /recipients.

    O type aceita 'individual' ou 'company' - 'corporation' e recusado com
    422, e a mensagem so aparece depois de o holder_type tambem estar certo.
    """
    return {
        "name": nome,
        "email": f"{documento[:6]}@exemplo.test",
        "description": nome,
        "document": documento,
        "type": tipo,
        "code": f"{nome.lower().replace(' ', '-')}-{documento[:6]}",
        "default_bank_account": {
            "holder_name": nome,
            "holder_type": "individual" if tipo == "individual" else "company",
            "holder_document": documento,
            "bank": "033",
            "branch_number": "1234",
            "branch_check_digit": "0",
            "account_number": "12345",
            "account_check_digit": "6",
            "type": "checking",
        },
        "transfer_settings": {
            "transfer_enabled": True,
            "transfer_interval": "Daily",
            "transfer_day": 0,
        },
    }


def veredito(cobranca):
    """O que realmente aconteceu com a cobranca.

    O HTTP 200 do POST /orders nao diz nada: uma order com recipient_id
    inexistente tambem volta 200. A verdade esta em tres lugares, e os tres
    precisam ser lidos - o status da cobranca, o gateway_response de dentro da
    ultima transacao, e o splits, que vem null quando o split nao pegou.
    """
    transacao = cobranca.get("last_transaction") or {}
    resposta = transacao.get("gateway_response") or {}
    mensagens = [e.get("message", "") for e in resposta.get("errors") or []]
    return cobranca.get("status"), resposta.get("code"), " | ".join(mensagens)


# --- 0. Os dois recebedores ------------------------------------------------

passo(0, "Criando os recebedores do vendedor e da plataforma")

# Cada vendedor tem conta Pagar.me propria, e a plataforma e um recebedor
# DENTRO da conta dele - ver ADR-0003. Aqui os dois nascem na mesma conta de
# sandbox, que e o mesmo desenho visto de dentro.
documento_vendedor = cnpj_sintetico()
documento_plataforma = cnpj_sintetico()

vendedor = call("POST", "/recipients",
                recebedor("Vendedor Sandbox", documento_vendedor, "company"))
plataforma = call("POST", "/recipients",
                  recebedor("Plataforma Sandbox", documento_plataforma, "company"))

id_vendedor = vendedor.get("id", "")
id_plataforma = plataforma.get("id", "")
print(f"  vendedor ......... {id_vendedor}")
print(f"  plataforma ....... {id_plataforma}")

# A guarda que da sentido ao numero: com um recebedor so, o split dividiria
# dinheiro entre a conta e ela mesma, e nada teria mudado de dono.
if not id_vendedor or not id_plataforma:
    erro("algum recebedor nao nasceu - sem os dois nao ha o que dividir")
if id_vendedor == id_plataforma:
    erro("os recebedores sao o mesmo - o split nao teria como ser provado")


# --- 1. A cobranca com split ------------------------------------------------

passo(1, "Criando a cobranca com split")

documento_aluno = cpf_sintetico()
endereco = {
    "line_1": "100, Rua de Teste, Centro",
    "zip_code": "01310000",
    "city": "Sao Paulo",
    "state": "SP",
    "country": "BR",
}
comprador = {
    "name": "Aluno Sandbox",
    "email": f"aluno{documento_aluno[:5]}@exemplo.test",
    "type": "individual",
    "document": documento_aluno,
    "document_type": "CPF",
    "address": endereco,
    "phones": {
        "mobile_phone": {"country_code": "55", "area_code": "11", "number": "999999999"},
    },
}

# O 'code' do item e obrigatorio, e a falta dele nao vira erro de requisicao:
# o POST devolve 200 e a cobranca nasce failed com "The item Code is required".
itens = [{
    "amount": int(round(PRICE * 100)),
    "description": "Curso de teste",
    "quantity": 1,
    "code": "curso-teste",
}]

# As duas regras somam 100%. A parte do vendedor e o resto, e e ele quem arca
# com a taxa - quem vende emite a nota, entao quem vende paga o custo.
#
# O int() NAO e enfeite: medido em 11/09/2026, um amount float (75.0) e
# recusado com HTTP 400 "The request is invalid." sem dizer qual campo. Com
# 75 inteiro a mesma requisicao passa.
split = [
    {
        "amount": int(100 - COMMISSION),
        "recipient_id": id_vendedor,
        "type": "percentage",
        "options": {
            "liable": True,
            "charge_processing_fee": True,
            "charge_remainder_fee": True,
        },
    },
    {
        "amount": int(COMMISSION),
        "recipient_id": id_plataforma,
        "type": "percentage",
        "options": {
            "liable": False,
            "charge_processing_fee": False,
            "charge_remainder_fee": False,
        },
    },
]

# Cartao, e nao Pix, por um motivo medido: em 11/09/2026 o Pix ainda respondia
# 400 "Sem ambiente configurado para este tipo de transacao" nesta conta,
# enquanto cartao e boleto ja processavam. O cartao de teste liquida na hora,
# o que torna a prova imediata - com Pix seria preciso pagar de verdade.
#
# Quando o Pix for liberado, troque este bloco: e o caminho principal do
# plugin, e a prova dele vale mais que a do cartao.
order = call("POST", "/orders", {
    "items": itens,
    "customer": comprador,
    "payments": [{
        "payment_method": "credit_card",
        "credit_card": {
            "installments": 1,
            "card": {
                "number": "4000000000000010",
                "holder_name": "Aluno Sandbox",
                "exp_month": 12,
                "exp_year": 30,
                "cvv": "123",
                "billing_address": endereco,
            },
        },
        "split": split,
    }],
})

id_order = order.get("id", "")
print(f"  order ............ {id_order}")


# --- 2. Ler de volta --------------------------------------------------------

passo(2, "Lendo a cobranca de volta - o POST nao e prova")

# O payable nao nasce junto com o pagamento: ele leva alguns segundos. Ler
# cedo demais mostra zero e parece falha de split.
time.sleep(5)

order = call("GET", f"/orders/{id_order}")
cobrancas = order.get("charges") or []
if not cobrancas:
    erro("a order nao tem cobranca - nao ha o que conferir")

cobranca = cobrancas[0]
situacao, codigo, mensagem = veredito(cobranca)
print(f"  cobranca ......... {cobranca.get('id')} | {situacao}")
print(f"  gateway .......... {codigo} {mensagem}")

if situacao == "failed":
    erro(f"a cobranca falhou no adquirente ({codigo}: {mensagem}).\n"
         "Sem cobranca que processa nao ha split, e o resto do script mediria o vazio.")

transacao = cobranca.get("last_transaction") or {}
if transacao.get("qr_code"):
    print(f"  qr_code .......... {str(transacao['qr_code'])[:60]}...")


# --- 3. O SPLIT, que NAO esta na cobranca -----------------------------------

passo(3, "O SPLIT - e por que nao adianta procurar na cobranca")

# MEDIDO em 11/09/2026: `charge.splits` volta null MESMO QUANDO o split
# acontece. Uma cobranca de R$ 100,00 com 25% pagou, o extrato dos dois
# recebedores se moveu, e o GET continuou dizendo null.
#
# E o caso mais perigoso ja visto neste projeto. O preapproval do Mercado Pago
# e o PUT /subscriptions do Asaas aceitavam o campo e o descartavam - errar
# para menos. Este faz o trabalho direito e nao conta - errar para mais. Quem
# olhasse so aqui concluiria que o split falhou quando ele funcionou.
if cobranca.get("splits"):
    print("  a cobranca trouxe splits - anote, porque em 11/09/2026 ela nao trazia:")
    print("  " + json.dumps(cobranca["splits"], ensure_ascii=False)[:300])
else:
    print("  charge.splits ... null   (esperado - nao e sinal de falha)")

print("\n  A prova esta no extrato. Seguindo.")


# --- 4. Os payables, que sao a verdade --------------------------------------

passo(4, "Os payables - onde o dinheiro realmente aparece")

# O filtro por charge_id NAO funciona: devolve lista vazia. O payable e
# listado por recebedor e traz o charge_id dentro, entao a separacao e feita
# aqui. O mesmo vale para ?subscription_id= em /charges, que e simplesmente
# ignorado - um id inventado devolve a conta inteira.
def comissao_de(identificador, cobranca_id, tentativas=12, intervalo=10):
    """Centavos que este recebedor tem a receber por esta cobranca.

    Tenta varias vezes porque o payable NAO nasce junto com o pagamento:
    medido em 11/09/2026, ele apareceu cerca de 16 segundos depois. Uma
    leitura unica logo apos a cobranca mostra zero e parece falha de split -
    foi o que aconteceu na primeira rodada deste script.
    """
    for tentativa in range(tentativas):
        pagina = call("GET", f"/payables?recipient_id={identificador}&size=100")
        total = 0
        linhas = []
        for p in pagina.get("data", []):
            if p.get("charge_id") != cobranca_id:
                continue
            linhas.append(p)
            # Estorno entra como payable NEGATIVO, type 'refund'. Somar tudo faz
            # a comissao voltar a zero depois de um estorno, que e o certo.
            total += int(p.get("amount", 0)) - int(p.get("fee", 0))
        if linhas:
            return total, linhas
        if tentativa == 0:
            print(f"    aguardando o payable nascer (ate {tentativas * intervalo}s)...")
        time.sleep(intervalo)

    return 0, []


id_cobranca = cobranca.get("id", "")
resultados = {}

for nome, identificador in (("vendedor", id_vendedor), ("plataforma", id_plataforma)):
    centavos, linhas = comissao_de(identificador, id_cobranca)
    resultados[nome] = centavos
    print(f"  {nome}")
    if not linhas:
        print("    nenhum payable ainda - eles demoram alguns segundos a aparecer")
    for p in linhas:
        print(f"    amount ........... R$ {int(p.get('amount', 0)) / 100:.2f}")
        print(f"    taxa ............. R$ {int(p.get('fee', 0)) / 100:.2f}")
        print(f"    tipo ............. {p.get('type')}")
        print(f"    situacao ......... {p.get('status')}")
    print(f"    LIQUIDO .......... R$ {centavos / 100:.2f}")
    print()

if resultados.get("plataforma", 0) <= 0:
    erro("a plataforma nao tem payable desta cobranca.\n"
         "Ou o split nao pegou, ou o payable ainda nao apareceu - espere alguns\n"
         "segundos e rode de novo antes de concluir que falhou.")


# --- 5. Os saldos -----------------------------------------------------------

passo(5, "Os saldos, para fechar a conta")

for nome, identificador in (("vendedor", id_vendedor), ("plataforma", id_plataforma)):
    saldo = call("GET", f"/recipients/{identificador}/balance")
    print(f"  {nome:11} disponivel R$ {saldo.get('available_amount', 0) / 100:.2f}"
          f" | a receber R$ {saldo.get('waiting_funds_amount', 0) / 100:.2f}")

esperado = int(round(PRICE * 100 * COMMISSION / 100))
obtido = resultados.get("plataforma", 0)

print("\n" + "=" * 68)
print(f"Comissao pedida .... R$ {esperado / 100:.2f}  ({COMMISSION}% de R$ {PRICE:.2f})")
print(f"Comissao recebida .. R$ {obtido / 100:.2f}")
if obtido == esperado:
    print("\nBATE. O percentual do Pagar.me incide sobre o BRUTO - ao contrario do")
    print("Asaas, onde percentualValue incide sobre o liquido.")
else:
    print("\nNAO BATE. Se o recebido for menor, o percentual incide sobre o liquido")
    print("e a base do build_split() precisa mudar. Anote o numero no roteiro.")
print("=" * 68)
