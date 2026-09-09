#!/usr/bin/env python3
"""Prova o split no sandbox do Pagar.me, do zero, sem passar pelo Moodle.

Por que existe: o split e o coracao do modelo de marketplace, e cada gateway
mente de um jeito diferente sobre ele. No Mercado Pago o preapproval aceitou
marketplace_fee, devolveu 201 e descartou o campo. No Asaas o PUT /subscriptions
comum aceitou creditCard e nao guardou nada. Nos dois casos a resposta foi 2xx.

O que este script mede, e o motivo de cada medicao estar aqui:

    0. criar os dois recebedores          sem eles nao existe split
    1. o split chega na cobranca?         ler de volta, nao confiar no POST
    2. as regras precisam somar 100%?     decide se o vendedor precisa de re_
    3. percentage incide sobre o que?     bruto ou liquido muda a comissao
    4. quem paga a taxa?                  charge_processing_fee e liable
    5. o extrato bate?                    o GET diz o combinado, o extrato o que veio

Como usar:

    set -a && . .devcontainer/secrets/pagarme-sandbox.env && set +a
    python3 docs/data-validation/scripts/provar-split-pagarme.py

Sem dependencia externa - so a biblioteca padrao do Python.

ESTADO EM 09/09/2026: o script nao chega ao fim nesta conta. O passo 0 morre
com "This company it not allowed to create a recipient", e nenhuma forma de
pagamento processa - Pix, cartao e boleto voltam com "Erro desconhecido no
proxy". Ver ../pagarme-sandbox.md. O script fica pronto para o dia em que a
conta for liberada; nao o adapte para contornar a recusa, porque contornar
seria medir outra coisa.
"""

import base64
import json
import os
import random
import sys
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
            "User-Agent": "courses-free split proof",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=40) as response:
            return json.loads(response.read() or "{}")
    except urllib.error.HTTPError as error:
        detalhe = error.read().decode(errors="replace")
        try:
            corpo = json.loads(detalhe)
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
split = [
    {
        "amount": 100 - COMMISSION,
        "recipient_id": id_vendedor,
        "type": "percentage",
        "options": {
            "liable": True,
            "charge_processing_fee": True,
            "charge_remainder_fee": True,
        },
    },
    {
        "amount": COMMISSION,
        "recipient_id": id_plataforma,
        "type": "percentage",
        "options": {
            "liable": False,
            "charge_processing_fee": False,
            "charge_remainder_fee": False,
        },
    },
]

order = call("POST", "/orders", {
    "items": itens,
    "customer": comprador,
    "payments": [{
        "payment_method": "pix",
        "pix": {"expires_in": 3600},
        "split": split,
    }],
})

id_order = order.get("id", "")
print(f"  order ............ {id_order}")


# --- 2. Ler de volta --------------------------------------------------------

passo(2, "Lendo a cobranca de volta - o POST nao e prova")

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


# --- 3. O SPLIT -------------------------------------------------------------

passo(3, "O SPLIT")

splits = cobranca.get("splits") or []
if not splits:
    erro("a cobranca voltou SEM splits, e o POST tinha devolvido 200.\n"
         "E exatamente o caso do marketplace_fee no preapproval do Mercado Pago:\n"
         "campo aceito, campo descartado, nenhum erro no caminho.")

for regra in splits:
    da_plataforma = regra.get("recipient_id") == id_plataforma
    print(f"    recebedor ........ {regra.get('recipient_id')}")
    print(f"    e a plataforma? .. {'SIM' if da_plataforma else 'nao'}")
    print(f"    tipo ............. {regra.get('type')}")
    print(f"    valor ............ {regra.get('amount')}")
    print(f"    opcoes ........... {json.dumps(regra.get('options') or {}, ensure_ascii=False)}")
    print()

if not any(r.get("recipient_id") == id_plataforma for r in splits):
    erro("nenhuma regra aponta para a plataforma - a comissao nao foi para lugar nenhum")


# --- 4. Os extratos ---------------------------------------------------------

passo(4, "Os extratos - o GET diz o combinado, o extrato diz o que chegou")

for nome, identificador in (("vendedor", id_vendedor), ("plataforma", id_plataforma)):
    saldo = call("GET", f"/recipients/{identificador}/balances")
    print(f"  {nome:11} disponivel R$ {saldo.get('available_amount', 0) / 100:.2f}"
          f" | a receber R$ {saldo.get('waiting_funds_amount', 0) / 100:.2f}")

print("\n" + "=" * 68)
print("Se o split acima mostrar a plataforma com valor, E o extrato dela tiver")
print("se movido, o split do Pagar.me esta provado. Uma coisa sem a outra nao e")
print("prova: o combinado aparece no GET mesmo quando o dinheiro nao anda.")
print("=" * 68)
