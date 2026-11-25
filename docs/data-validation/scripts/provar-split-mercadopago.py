#!/usr/bin/env python3
"""Prova o marketplace_fee do Mercado Pago entre duas contas, sem passar pelo Moodle.

Por que existe: o split do Mercado Pago nunca foi visto transferindo nada neste
projeto. Ele "funcionou" uma vez com vendedor e marketplace na MESMA conta - a
preferencia foi aceita, o pagamento aprovou, e nenhum centavo mudou de dono.
Sucesso sem erro nao e prova, e este script existe para nao aceitar de novo.

A guarda central esta em `conferir`: se o `collector_id` do pagamento for igual
ao dono da aplicacao, o script ABORTA. E o equivalente do "as carteiras sao
iguais" do provar-split-asaas.py.

Sao TRES partes no split: comprador, vendedor e a APLICACAO. A comissao volta
para o dono da aplicacao, e nao para quem criou a preferencia - por isso a
aplicacao precisa viver na conta da plataforma.

Como usar, na ordem:

    export MP_CLIENT_ID=...          # aplicacao DA PLATAFORMA
    export MP_CLIENT_SECRET=...
    export MP_ACCESS_TOKEN=...       # token de producao da conta da plataforma
    export MP_REDIRECT_URI=https://mp.leodg.dev/payment/gateway/mercadopago/oauth_callback.php

    python3 provar-split-mercadopago.py usuarios     # cria vendedor e comprador de teste
    python3 provar-split-mercadopago.py autorizar    # imprime a URL de OAuth do vendedor
    export MP_CODE=...                               # o code que voltou no callback
    export MP_VERIFIER=...                           # o verifier que o passo anterior imprimiu
    python3 provar-split-mercadopago.py trocar       # troca o code pelo token do vendedor
    export MP_SELLER_TOKEN=...
    python3 provar-split-mercadopago.py cobrar       # preferencia com marketplace_fee
    # pague no init_point, logado como o COMPRADOR de teste
    python3 provar-split-mercadopago.py conferir <payment_id>

O OAuth e o pagamento exigem navegador, e nao ha como automatizar: o Mercado
Pago pede login das duas pontas. O script cobre tudo o que e API.
"""

import base64
import hashlib
import json
import os
import secrets
import sys
import urllib.error
import urllib.parse
import urllib.request

API = "https://api.mercadopago.com"

# O OAuth nao tem dominio unico: o vendedor autoriza no dominio do PAIS dele.
AUTH_DOMAIN = {
    "MLA": "auth.mercadopago.com.ar",
    "MLB": "auth.mercadopago.com.br",
    "MLC": "auth.mercadopago.cl",
    "MCO": "auth.mercadopago.com.co",
    "MLM": "auth.mercadopago.com.mx",
    "MPE": "auth.mercadopago.com.pe",
    "MLU": "auth.mercadopago.com.uy",
}

SITE = os.environ.get("MP_SITE_ID", "MLB")
VALOR = float(os.environ.get("MP_VALOR", "100"))
PERCENTUAL = float(os.environ.get("MP_PERCENTUAL", "25"))
MOODLE_SITE = os.environ.get("MOODLE_SITE", "https://mp.leodg.dev")

# Espelha o testmode do site. Fora de teste NAO se manda wallet_purchase.
TESTMODE = os.environ.get("MP_TESTMODE", "0") == "1"


def erro(mensagem):
    """Encerra com mensagem, porque seguir daria um resultado que nao prova nada."""
    print("ABORTADO: " + mensagem, file=sys.stderr)
    sys.exit(1)


def env(nome):
    """Le uma variavel obrigatoria."""
    valor = os.environ.get(nome, "").strip()
    if not valor:
        erro("falta a variavel de ambiente " + nome)
    return valor


def chamar(metodo, caminho, token=None, corpo=None):
    """Chamada a API, com o erro do Mercado Pago preservado."""
    url = caminho if caminho.startswith("http") else API + caminho
    dados = json.dumps(corpo).encode() if corpo is not None else None
    pedido = urllib.request.Request(url, data=dados, method=metodo)
    pedido.add_header("Content-Type", "application/json")
    if token:
        pedido.add_header("Authorization", "Bearer " + token)

    try:
        with urllib.request.urlopen(pedido, timeout=30) as resposta:
            return json.loads(resposta.read().decode())
    except urllib.error.HTTPError as e:
        detalhe = e.read().decode()
        erro("HTTP %s em %s %s\n%s" % (e.code, metodo, url, detalhe))


def dono_da_aplicacao(token):
    """Id da conta a que a aplicacao pertence - quem recebe a comissao."""
    return int(chamar("GET", "/users/me", token)["id"])


def cmd_usuarios():
    """Cria o vendedor e o comprador de teste."""
    token = env("MP_ACCESS_TOKEN")
    dono = dono_da_aplicacao(token)
    print("dono da aplicacao (plataforma): %s" % dono)

    for papel in ("vendedor", "comprador"):
        usuario = chamar("POST", "/users/test_user", token, {"site_id": SITE})
        print("\n%s de teste" % papel)
        print("  id       %s" % usuario["id"])
        print("  usuario  %s" % usuario["nickname"])
        print("  senha    %s" % usuario["password"])
        if int(usuario["id"]) == dono:
            erro("o usuario de teste saiu com o id do dono da aplicacao")

    print("\nGuarde os dois. O vendedor autoriza a aplicacao; o comprador paga.")


def cmd_autorizar():
    """Imprime a URL que o VENDEDOR abre para autorizar a aplicacao."""
    verifier = base64.urlsafe_b64encode(secrets.token_bytes(48)).decode().rstrip("=")
    challenge = base64.urlsafe_b64encode(
        hashlib.sha256(verifier.encode()).digest()
    ).decode().rstrip("=")

    parametros = urllib.parse.urlencode({
        "client_id": env("MP_CLIENT_ID"),
        "response_type": "code",
        "platform_id": "mp",
        "redirect_uri": env("MP_REDIRECT_URI"),
        "state": "prova-split",
        "code_challenge": challenge,
        "code_challenge_method": "S256",
    })

    print("export MP_VERIFIER=%s\n" % verifier)
    print("Abra ESTA url logado como o VENDEDOR de teste:\n")
    print("https://%s/authorization?%s" % (AUTH_DOMAIN.get(SITE, AUTH_DOMAIN["MLB"]), parametros))
    print("\nO redirect_uri precisa estar cadastrado na aplicacao DA PLATAFORMA.")
    print("Cadastrado na aplicacao do vendedor, o retorno e 'invalid redirect_uri'")
    print("sem dizer qual aplicacao foi consultada.")


def cmd_trocar():
    """Troca o codigo de autorizacao pelo token do vendedor."""
    resposta = chamar("POST", "/oauth/token", None, {
        "grant_type": "authorization_code",
        "client_id": env("MP_CLIENT_ID"),
        "client_secret": env("MP_CLIENT_SECRET"),
        "code": env("MP_CODE"),
        "redirect_uri": env("MP_REDIRECT_URI"),
        "code_verifier": env("MP_VERIFIER"),
        # Sem isto a aplicacao entra como producao mesmo com vendedor e
        # comprador de teste, e o checkout morre com "uma das partes e de
        # teste" sem dizer qual delas.
        "test_token": "true",
    })

    print("export MP_SELLER_TOKEN=%s" % resposta["access_token"])
    print("\nvendedor user_id: %s" % resposta.get("user_id"))
    print("expira em %s segundos" % resposta.get("expires_in"))


def cmd_cobrar():
    """Cria a preferencia com marketplace_fee, usando o token do VENDEDOR."""
    plataforma = env("MP_ACCESS_TOKEN")
    vendedor = env("MP_SELLER_TOKEN")

    dono = dono_da_aplicacao(plataforma)
    quem_cobra = dono_da_aplicacao(vendedor)

    # A guarda que da sentido ao resto. Com as duas pontas na mesma conta, a
    # preferencia e aceita, o pagamento aprova, e nada e transferido.
    if dono == quem_cobra:
        erro(
            "vendedor e dono da aplicacao sao a MESMA conta (%s).\n"
            "O marketplace_fee seria aceito e nao transferiria nada - foi\n"
            "exatamente assim que o split pareceu funcionar da primeira vez." % dono
        )

    comissao = round(VALOR * (PERCENTUAL / 100), 2)
    referencia = "prova-split-" + secrets.token_hex(6)

    corpo = {
        "items": [{
            "title": "Prova do split",
            "quantity": 1,
            "unit_price": VALOR,
            "currency_id": os.environ.get("MP_MOEDA", "BRL"),
        }],
        "external_reference": referencia,
        "marketplace_fee": comissao,
        "back_urls": {
            "success": MOODLE_SITE + "/payment/gateway/mercadopago/return.php?ref=" + referencia,
            "pending": MOODLE_SITE + "/payment/gateway/mercadopago/return.php?ref=" + referencia,
            "failure": MOODLE_SITE + "/payment/gateway/mercadopago/return.php?ref=" + referencia,
        },
        "notification_url": MOODLE_SITE + "/payment/gateway/mercadopago/webhook.php",
        "auto_return": "approved",
    }

    # Espelha o payment_processor: o wallet_purchase entra SO em modo de teste.
    #
    # Em teste ele existe porque visitante nao e usuario de teste e o Mercado
    # Pago recusa a compra. Em producao ele cortaria pagamento sem cadastro,
    # boleto e dinheiro - conversao real, para resolver problema que so existe
    # no sandbox.
    #
    # Em 08/09/2026 os dois caminhos foram exercitados no sandbox e nenhum
    # cobrou: COM wallet_purchase o checkout entra em loop em /login/wallet/,
    # SEM ele a tela devolve erro. Zero pagamentos criados nas duas. O sandbox
    # do Checkout Pro com marketplace_fee nao tem caminho - a prova e com conta
    # real.
    if TESTMODE:
        corpo["purpose"] = "wallet_purchase"

    preferencia = chamar("POST", "/checkout/preferences", vendedor, corpo)

    print("plataforma (recebe a comissao): %s" % dono)
    print("vendedor   (cria a cobranca):   %s" % quem_cobra)
    print("bruto %.2f, comissao %.2f (%.2f%%)" % (VALOR, comissao, PERCENTUAL))
    print("referencia: %s" % referencia)
    # O sandbox_init_point SO vale em modo de teste, e a condicao e a mesma do
    # payment_processor. Preferi-lo sempre mandaria uma cobranca de producao
    # para sandbox.mercadopago.com.br, que nao a conhece.
    ponto = preferencia.get("sandbox_init_point") if TESTMODE else None

    print("\nPague AQUI:")
    print(ponto or preferencia["init_point"])
    print("\nDepois: python3 %s conferir <payment_id>" % sys.argv[0])


def cmd_conferir(paymentid):
    """Confere que o dinheiro se dividiu, e nao so que nao houve erro."""
    plataforma = env("MP_ACCESS_TOKEN")
    vendedor = env("MP_SELLER_TOKEN")

    dono = dono_da_aplicacao(plataforma)
    pagamento = chamar("GET", "/v1/payments/" + urllib.parse.quote(paymentid, safe=""), vendedor)

    coletor = int(pagamento.get("collector_id") or 0)
    status = pagamento.get("status")
    bruto = float(pagamento.get("transaction_amount") or 0)
    detalhes = pagamento.get("transaction_details") or {}
    liquido = float(detalhes.get("net_received_amount") or 0)

    print("status:      %s" % status)
    print("bruto:       %.2f" % bruto)
    print("collector:   %s" % coletor)
    print("dono do app: %s" % dono)

    if coletor == dono:
        erro(
            "o collector_id e o dono da aplicacao: vendedor e marketplace sao a\n"
            "MESMA conta. Nada foi transferido, e este resultado NAO prova split."
        )

    comissao = 0.0
    taxa_mp = 0.0
    for taxa in pagamento.get("fee_details") or []:
        valor = float(taxa.get("amount") or 0)
        if taxa.get("type") == "application_fee":
            comissao += valor
        else:
            taxa_mp += valor
        print("fee_details: %-20s %8.2f  (%s)" % (taxa.get("type"), valor, taxa.get("fee_payer")))

    if comissao <= 0:
        erro(
            "nao ha application_fee no pagamento. O marketplace_fee foi ignorado -\n"
            "confira se a integracao no painel esta declarada como API de Preferencias."
        )

    print("\ncomissao da plataforma: %.2f" % comissao)
    print("taxa do Mercado Pago:   %.2f" % taxa_mp)
    print("liquido do vendedor:    %.2f" % liquido)

    if status != "approved":
        print("\nAINDA NAO E PROVA: o pagamento esta '%s'." % status)
        return

    print("\nFalta o passo que so o extrato responde: confira o saldo das DUAS")
    print("contas. fee_details diz o que foi cobrado; extrato diz o que chegou.")


def main():
    """Despacha o subcomando."""
    comandos = {
        "usuarios": cmd_usuarios,
        "autorizar": cmd_autorizar,
        "trocar": cmd_trocar,
        "cobrar": cmd_cobrar,
    }

    if len(sys.argv) < 2:
        erro("uso: %s usuarios|autorizar|trocar|cobrar|conferir <payment_id>" % sys.argv[0])

    comando = sys.argv[1]
    if comando == "conferir":
        if len(sys.argv) < 3:
            erro("conferir precisa do id do pagamento")
        cmd_conferir(sys.argv[2])
    elif comando in comandos:
        comandos[comando]()
    else:
        erro("comando desconhecido: " + comando)


if __name__ == "__main__":
    main()
