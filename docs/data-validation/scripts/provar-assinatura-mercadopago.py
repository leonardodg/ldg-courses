#!/usr/bin/env python3
"""Mede o que a assinatura do Mercado Pago faz com a comissao da plataforma.

Irmao do provar-split-mercadopago.py, e existe por uma razao precisa. O
ADR-0001 afirma que POST /preapproval aceita marketplace_fee e DESCARTA em
silencio. A medicao e real, mas foi feita com a aplicacao de Checkout
Transparente - e no Mercado Pago o modelo declarado da aplicacao muda o
comportamento sem avisar. Existe agora uma aplicacao do tipo Assinaturas, e a
pergunta volta a ficar em aberto para o tipo certo.

O SDK oficial (github.com/mercadopago/sdk-php, master lido em 15/09/2026) ja
adianta a resposta provavel: Resources/PreApproval.php e
Resources/PreApproval/AutoRecurring.php nao declaram campo de taxa nenhum. Isso
NAO dispensa a medicao - modelo de SDK atrasa em relacao a API, e a pergunta
aqui e justamente se o tipo da aplicacao muda algo -, mas rebaixa M2 de
descoberta para confirmacao.

O QUE ESTE SCRIPT NAO ACEITA COMO PROVA
---------------------------------------
Eco no GET nao e prova. O preapproval pode devolver o campo e nao dividir nada;
pode engolir o campo e dividir assim mesmo. Quem responde e o PAGAMENTO do
ciclo, em fee_details - e e por isso que `conferir` existe e aborta quando o
collector_id e o dono da aplicacao. Sucesso sem erro foi exatamente como o
split pareceu funcionar da primeira vez, com vendedor e marketplace na mesma
conta.

SAO DUAS ASSINATURAS NO PROJETO, E SO UMA PRECISA DISTO
-------------------------------------------------------
B2B (a empresa paga a mensalidade da plataforma) nao tem split: a LDG e a
vendedora e fica com 100%. Para ela, preapproval basta hoje.
B2C (a parceira vende ao aluno) precisa de split, e e o que este script mede.

COMO USAR
---------
    # aplicacao de Assinaturas, da conta da plataforma
    export MP_SUB_CLIENT_ID=... MP_SUB_CLIENT_SECRET=... MP_SUB_TOKEN=...
    # aplicacao de Checkout Bricks, da mesma conta
    export MP_BRICKS_CLIENT_ID=... MP_BRICKS_CLIENT_SECRET=... MP_BRICKS_TOKEN=...
    # aplicacao de Preferencias, a que ja esta em producao
    export MP_PREF_TOKEN=...

    python3 provar-assinatura-mercadopago.py contas        # M1
    python3 provar-assinatura-mercadopago.py preapproval   # M2
    python3 provar-assinatura-mercadopago.py escopo        # M5
    python3 provar-assinatura-mercadopago.py advanced      # M7

    # as que precisam do vendedor autorizado por OAuth:
    export MP_SELLER_TOKEN=...
    python3 provar-assinatura-mercadopago.py assinatura    # M3, cria e imprime o init_point
    python3 provar-assinatura-mercadopago.py conferir <payment_id>

O OAuth e o pagamento exigem navegador: o Mercado Pago pede login das duas
pontas, e nao ha como automatizar. Use os subcomandos `autorizar` e `trocar` do
provar-split-mercadopago.py, que ja fazem o PKCE - passando o client_id e o
client_secret DA APLICACAO que se quer medir.
"""

import json
import os
import secrets
import sys
import urllib.error
import urllib.parse
import urllib.request

API = "https://api.mercadopago.com"

MOODLE_SITE = os.environ.get("MOODLE_SITE", "https://mp.leodg.dev")
MOEDA = os.environ.get("MP_MOEDA", "BRL")
VALOR = float(os.environ.get("MP_VALOR", "5"))
PERCENTUAL = float(os.environ.get("MP_PERCENTUAL", "25"))

# Um dia, e nao um mes: o ciclo precisa acontecer dentro da sessao de medicao.
# Esperar o segundo mes para descobrir que a comissao nao saiu e o oposto de
# medir.
FREQUENCIA = int(os.environ.get("MP_FREQUENCIA", "1"))
TIPO_FREQUENCIA = os.environ.get("MP_TIPO_FREQUENCIA", "days")

# Os candidatos a campo de split no preapproval. A lista existe porque "nao
# aceita" e "aceita e descarta" sao sintomas diferentes, e o segundo e pior:
# nao ha erro que segure o engano na porta.
CANDIDATOS = [
    ("marketplace_fee na raiz", {"marketplace_fee": None}),
    ("application_fee na raiz", {"application_fee": None}),
    ("marketplace na raiz", {"marketplace": "MP-MKT"}),
    ("marketplace_fee em auto_recurring", {"__auto__": {"marketplace_fee": None}}),
    ("application_fee em auto_recurring", {"__auto__": {"application_fee": None}}),
]


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
    """Chamada a API que NAO aborta em erro.

    Devolve (status, corpo). A recusa da API e um resultado da medicao, e nao
    uma falha do script: saber que o preapproval recusa um campo vale tanto
    quanto saber que ele aceita. O irmao deste script aborta em erro porque la
    todo passo precisa do anterior; aqui cada candidato e independente.
    """
    url = caminho if caminho.startswith("http") else API + caminho
    dados = json.dumps(corpo).encode() if corpo is not None else None
    pedido = urllib.request.Request(url, data=dados, method=metodo)
    pedido.add_header("Content-Type", "application/json")
    if token:
        pedido.add_header("Authorization", "Bearer " + token)
    # Sem isto o Mercado Pago recusa POST repetido de assinatura com 400 e uma
    # mensagem que nao diz que o problema e a repeticao.
    if metodo == "POST":
        pedido.add_header("X-Idempotency-Key", secrets.token_hex(16))

    try:
        with urllib.request.urlopen(pedido, timeout=30) as resposta:
            return resposta.status, json.loads(resposta.read().decode())
    except urllib.error.HTTPError as e:
        texto = e.read().decode()
        try:
            return e.code, json.loads(texto)
        except ValueError:
            return e.code, {"raw": texto}


def exigir(metodo, caminho, token=None, corpo=None):
    """Chamada que aborta fora de 2xx, para os passos que o seguinte depende."""
    status, resposta = chamar(metodo, caminho, token, corpo)
    if status < 200 or status >= 300:
        erro("HTTP %s em %s %s\n%s" % (status, metodo, caminho, json.dumps(resposta, indent=2)))
    return resposta


def identidade(token, rotulo):
    """Quem e a conta dona deste token, e de que tipo ela e.

    O tipo decide como o resultado e lido. Em 08/09/2026 uma conta rotulada
    "empresa" no cadastro era pessoa fisica pela API, e a prova teria sido lida
    ao contrario se tivesse falhado - ver ADR-0010.
    """
    status, dados = chamar("GET", "/users/me", token)
    if status != 200:
        print("  %-22s ERRO HTTP %s - %s" % (rotulo, status, dados.get("message", dados)))
        return None

    identificacao = dados.get("identification") or {}
    tags = dados.get("tags") or []

    print("  %-22s id=%-12s site=%-4s doc=%-5s %s" % (
        rotulo,
        dados.get("id"),
        dados.get("site_id"),
        identificacao.get("type") or "-",
        "business" if "business" in tags else "pessoa fisica",
    ))
    return int(dados.get("id") or 0)


def corpo_assinatura(comissao=None, extra=None, auto_extra=None, email=None):
    """Monta o corpo do preapproval, com os campos candidatos encaixados.

    O status 'pending' e o que devolve init_point sem exigir card_token_id: o
    pagador autoriza no navegador. Com 'authorized' o Mercado Pago exige o
    cartao ja tokenizado, o que nao da para fazer sem tela.
    """
    referencia = "prova-assinatura-" + secrets.token_hex(6)
    auto = {
        "frequency": FREQUENCIA,
        "frequency_type": TIPO_FREQUENCIA,
        "transaction_amount": VALOR,
        "currency_id": MOEDA,
    }
    if auto_extra:
        auto.update(auto_extra)

    corpo = {
        "reason": "Prova de assinatura com split",
        "external_reference": referencia,
        "payer_email": email or os.environ.get("MP_PAYER_EMAIL", "test_user@testuser.com"),
        "auto_recurring": auto,
        "back_url": MOODLE_SITE + "/payment/gateway/mercadopago/return.php?ref=" + referencia,
        "status": "pending",
    }
    if extra:
        corpo.update(extra)

    return referencia, corpo


def cmd_contas():
    """M1 - de quem e cada aplicacao, em producao e em teste.

    As credenciais de teste das duas aplicacoes novas nao sao simetricas: as do
    Bricks vem com prefixo TEST- e as de Assinaturas com APP_USR-, apontando
    para ids diferentes. Sao TRES partes no split - comprador, vendedor e a
    aplicacao -, e misturar ambientes devolve "uma das partes e de teste" sem
    dizer qual.
    """
    print("M1 - dono de cada token\n")

    donos = {}
    for variavel, rotulo in [
        ("MP_PREF_TOKEN", "preferencias"),
        ("MP_SUB_TOKEN", "assinaturas"),
        ("MP_BRICKS_TOKEN", "bricks"),
        ("MP_SUB_TEST_TOKEN", "assinaturas (teste)"),
        ("MP_BRICKS_TEST_TOKEN", "bricks (teste)"),
        ("MP_SELLER_TOKEN", "VENDEDOR"),
    ]:
        token = os.environ.get(variavel, "").strip()
        if not token:
            print("  %-22s (nao informado)" % rotulo)
            continue
        donos[rotulo] = identidade(token, rotulo)

    plataforma = donos.get("preferencias") or donos.get("assinaturas")
    vendedor = donos.get("VENDEDOR")

    print()
    if vendedor and plataforma and vendedor == plataforma:
        erro(
            "vendedor e plataforma sao a MESMA conta (%s).\n"
            "Qualquer comissao seria aceita e nao transferiria nada - foi\n"
            "exatamente assim que o split pareceu funcionar da primeira vez." % vendedor
        )

    producao = [r for r in ("preferencias", "assinaturas", "bricks") if r in donos]
    if len(set(donos[r] for r in producao)) > 1:
        print("ATENCAO: as aplicacoes de producao NAO pertencem a mesma conta.")
        print("A comissao volta para o dono da APLICACAO, entao isto muda quem recebe.")
    elif producao:
        print("ok as aplicacoes de producao pertencem a mesma conta: %s" % donos[producao[0]])


def cmd_preapproval():
    """M2 - o preapproval honra algum campo de split?

    Um candidato por vez, POST e GET logo depois. O criterio e o eco: o campo
    volta no GET? Mas o eco nao e prova de divisao - so de que a API guardou
    alguma coisa. A prova mora no pagamento do ciclo, em `conferir`.
    """
    token = env("MP_SELLER_TOKEN")
    comissao = round(VALOR * (PERCENTUAL / 100), 2)

    print("M2 - candidatos a campo de split no POST /preapproval")
    print("valor %.2f, comissao pretendida %.2f (%.2f%%)\n" % (VALOR, comissao, PERCENTUAL))

    for rotulo, forma in CANDIDATOS:
        extra = {}
        auto_extra = {}
        for chave, valor in forma.items():
            if chave == "__auto__":
                auto_extra = {k: (comissao if v is None else v) for k, v in valor.items()}
            else:
                extra[chave] = comissao if valor is None else valor

        referencia, corpo = corpo_assinatura(extra=extra, auto_extra=auto_extra)
        status, resposta = chamar("POST", "/preapproval", token, corpo)

        if status < 200 or status >= 300:
            mensagem = resposta.get("message") or resposta.get("error") or resposta
            print("  %-34s RECUSADO  HTTP %s - %s" % (rotulo, status, mensagem))
            continue

        idassinatura = resposta.get("id")
        _, devolvido = chamar("GET", "/preapproval/" + urllib.parse.quote(str(idassinatura)), token)

        # Procura por qualquer chave que lembre taxa, em toda a arvore: o nome
        # pode voltar diferente do que foi enviado.
        achados = chaves_de_taxa(devolvido)
        print("  %-34s ACEITO    %s  eco: %s" % (
            rotulo,
            idassinatura,
            ", ".join(achados) if achados else "NENHUM campo de taxa no GET",
        ))

    print("\nEco no GET nao e prova. Quem responde e o pagamento do ciclo:")
    print("  python3 %s assinatura      # cria uma para o aluno autorizar" % sys.argv[0])
    print("  python3 %s conferir <id>   # le fee_details do pagamento" % sys.argv[0])


def chaves_de_taxa(no, prefixo=""):
    """Toda chave da arvore cujo nome sugira taxa ou marketplace.

    Procura em profundidade porque o Mercado Pago pode devolver o campo com
    outro nome, ou aninhado em summarized. Um GET que devolve exatamente o que
    foi enviado nao prova nada; um que devolve NADA parecido com taxa fecha a
    questao do eco.
    """
    achados = []
    if isinstance(no, dict):
        for chave, valor in no.items():
            caminho = prefixo + chave
            if any(p in chave.lower() for p in ("fee", "market", "commission", "charge")):
                achados.append("%s=%s" % (caminho, valor))
            achados.extend(chaves_de_taxa(valor, caminho + "."))
    elif isinstance(no, list):
        for i, item in enumerate(no):
            achados.extend(chaves_de_taxa(item, "%s[%d]." % (prefixo.rstrip("."), i)))
    return achados


def cmd_escopo():
    """M5 - o token do vendedor de uma aplicacao serve para a outra?

    Se um token servir para tudo, a multi-aplicacao encolhe: bastaria uma
    autorizacao por vendedor. Se nao servir, cada aplicacao exige o seu OAuth -
    e a tela precisa dizer qual falta.
    """
    print("M5 - escopo do token do vendedor por aplicacao\n")

    token = env("MP_SELLER_TOKEN")
    origem = os.environ.get("MP_SELLER_TOKEN_APP", "(nao informado)")
    print("token do vendedor emitido pela aplicacao: %s\n" % origem)

    _, corpo = corpo_assinatura()
    status, resposta = chamar("POST", "/preapproval", token, corpo)
    print("  POST /preapproval        HTTP %s  %s" % (
        status,
        resposta.get("id") or resposta.get("message") or "",
    ))

    status, resposta = chamar("POST", "/checkout/preferences", token, {
        "items": [{
            "title": "Prova de escopo",
            "quantity": 1,
            "unit_price": VALOR,
            "currency_id": MOEDA,
        }],
        "marketplace_fee": round(VALOR * (PERCENTUAL / 100), 2),
    })
    print("  POST /checkout/preferences  HTTP %s  %s" % (
        status,
        resposta.get("id") or resposta.get("message") or "",
    ))

    print("\nOs dois aceitando: um token por vendedor basta, e a Fase 3a encolhe.")
    print("Um recusando: cada aplicacao exige o seu OAuth, como o plano assume.")


def cmd_advanced():
    """M7 - o split 1:N do /v1/advanced_payments.

    Achado no SDK oficial, e em nenhuma busca: Resources/AdvancedPayment tem
    disbursements[], e cada Disbursement carrega collector_id, amount e
    application_fee proprio.

    CUIDADO AO LER O RESULTADO: quem cria o advanced payment e a PLATAFORMA, e
    o ADR-0003 diz que a cobranca nasce na conta do vendedor por regra fiscal -
    a plataforma nao emite nota por outra empresa. Este comando mede; a adocao
    depende de uma decisao fiscal que nao e tecnica.
    """
    plataforma = env("MP_BRICKS_TOKEN")
    vendedor = env("MP_SELLER_ID")
    comissao = round(VALOR * (PERCENTUAL / 100), 2)

    print("M7 - /v1/advanced_payments com dois disbursements\n")

    referencia = "prova-advanced-" + secrets.token_hex(6)
    corpo = {
        "application_id": os.environ.get("MP_BRICKS_CLIENT_ID", ""),
        "payments": [{
            "payment_method_id": "pix",
            "transaction_amount": VALOR,
            "description": "Prova de advanced payment",
        }],
        "disbursements": [{
            "amount": VALOR,
            "external_reference": referencia + "-vendedor",
            "collector_id": int(vendedor),
            "application_fee": comissao,
        }],
        "external_reference": referencia,
        "description": "Prova de split 1:N",
        "binary_mode": False,
        "capture": True,
    }

    status, resposta = chamar("POST", "/v1/advanced_payments", plataforma, corpo)
    print("  HTTP %s" % status)
    print(json.dumps(resposta, indent=2, ensure_ascii=False)[:2000])

    if 200 <= status < 300:
        print("\nACEITO. Antes de comemorar, leia o ADR-0003: quem cria isto e a")
        print("plataforma, e nao o vendedor. Adotar muda quem emite a nota.")


def cmd_assinatura():
    """M3 - cria a assinatura que vai gerar o pagamento a medir.

    O preapproval nao e onde o dinheiro se divide; o PAGAMENTO do ciclo e. Esta
    e a pergunta que o ADR-0001 nao fez.
    """
    token = env("MP_SELLER_TOKEN")
    comissao = round(VALOR * (PERCENTUAL / 100), 2)

    # Manda o campo que sobreviveu ao M2. Sem candidato configurado, manda o
    # marketplace_fee, que e o do ADR-0001 - o que se quer e ver se o PAGAMENTO
    # sai com application_fee, independentemente do eco.
    campo = os.environ.get("MP_CAMPO_SPLIT", "marketplace_fee")
    referencia, corpo = corpo_assinatura(extra={campo: comissao})

    resposta = exigir("POST", "/preapproval", token, corpo)

    print("assinatura:  %s" % resposta.get("id"))
    print("referencia:  %s" % referencia)
    print("campo enviado: %s = %.2f" % (campo, comissao))
    print("ciclo: a cada %s %s, %.2f %s" % (FREQUENCIA, TIPO_FREQUENCIA, VALOR, MOEDA))
    print("\nAutorize AQUI, logado como o COMPRADOR:")
    print(resposta.get("init_point"))
    print("\nDepois do primeiro ciclo cobrar:")
    print("  python3 %s conferir <payment_id>" % sys.argv[0])


def cmd_token_cartao():
    """Gera um card_token a partir de um cartao DE TESTE do Mercado Pago.

    EXISTE SO PARA MEDIR, e a distincao importa. Em producao o token nasce no
    navegador, no Card Payment Brick, e o PAN nunca toca o servidor do Moodle -
    e essa a restricao do projeto. Aqui os numeros sao os cartoes publicados
    pelo proprio Mercado Pago para teste, e mandar o PAN pela API e o unico
    jeito de exercitar o ciclo sem abrir tela.

    O nome do titular decide o desfecho no sandbox: APRO aprova, FUND recusa
    por saldo, SECU por codigo de seguranca. E por isso que o nome entra por
    variavel.
    """
    token = env("MP_SELLER_TOKEN")

    corpo = {
        "card_number": os.environ.get("MP_CARD_NUMBER", "5480832801033311"),
        "expiration_month": int(os.environ.get("MP_CARD_MES", "11")),
        "expiration_year": int(os.environ.get("MP_CARD_ANO", "2030")),
        "security_code": os.environ.get("MP_CARD_CVV", "123"),
        "cardholder": {
            "name": os.environ.get("MP_CARD_NOME", "APRO"),
            "identification": {
                "type": "CPF",
                "number": os.environ.get("MP_CARD_CPF", "12345678909"),
            },
        },
    }

    status, resposta = chamar("POST", "/v1/card_tokens", token, corpo)
    if status < 200 or status >= 300:
        erro("HTTP %s ao tokenizar: %s" % (status, json.dumps(resposta, indent=2)))

    print("export MP_CARD_TOKEN=%s" % resposta.get("id"))
    print("\nbandeira: %s  final: %s  titular: %s" % (
        resposta.get("payment_method_id") or "-",
        resposta.get("last_four_digits"),
        (resposta.get("cardholder") or {}).get("name"),
    ))
    print("O token e de uso UNICO e expira em minutos - gere um por cobranca.")


def cmd_cartao():
    """M4 - cartao guardado no MP, cobranca nossa. O Plano B.

    O passo que este script NAO faz e o unico que importa de verdade: a SEGUNDA
    cobranca, dias depois, sem CVV. E ela, e so ela, que prova debito
    automatico. Rode `cobrar-de-novo` com o mesmo cartao depois.

    O card_token nasce no navegador, pelo Card Payment Brick. O PAN nao passa
    por aqui de proposito - nem pelo servidor do Moodle.
    """
    vendedor = env("MP_SELLER_TOKEN")
    cardtoken = env("MP_CARD_TOKEN")
    email = os.environ.get("MP_PAYER_EMAIL", "test_user@testuser.com")
    comissao = round(VALOR * (PERCENTUAL / 100), 2)

    print("M4 - cliente, cartao guardado e cobranca com application_fee\n")

    status, cliente = chamar("POST", "/v1/customers", vendedor, {"email": email})
    if status == 400 and "already exist" in json.dumps(cliente).lower():
        busca = exigir("GET", "/v1/customers/search?email=" + urllib.parse.quote(email), vendedor)
        cliente = (busca.get("results") or [{}])[0]
        print("  cliente ja existia: %s" % cliente.get("id"))
    elif 200 <= status < 300:
        print("  cliente criado: %s" % cliente.get("id"))
    else:
        erro("nao foi possivel obter o cliente: HTTP %s %s" % (status, cliente))

    clienteid = cliente.get("id")
    cartao = exigir("POST", "/v1/customers/%s/cards" % clienteid, vendedor, {"token": cardtoken})
    print("  cartao guardado NO MERCADO PAGO: %s (final %s)" % (
        cartao.get("id"),
        cartao.get("last_four_digits"),
    ))

    referencia = "prova-cartao-" + secrets.token_hex(6)
    status, pagamento = chamar("POST", "/v1/payments", vendedor, {
        "transaction_amount": VALOR,
        "token": cardtoken,
        "description": "Prova de cobranca com cartao guardado",
        "installments": 1,
        "payer": {"type": "customer", "id": clienteid, "email": email},
        "external_reference": referencia,
        "application_fee": comissao,
        "notification_url": MOODLE_SITE + "/payment/gateway/mercadopago/webhook.php",
    })

    print("\n  POST /v1/payments  HTTP %s  id=%s  status=%s" % (
        status,
        pagamento.get("id"),
        pagamento.get("status"),
    ))
    print("\nexport MP_CUSTOMER_ID=%s" % clienteid)
    print("export MP_CARD_ID=%s" % cartao.get("id"))
    print("\nO que falta, e e o que prova: a SEGUNDA cobranca, sem CVV.")
    print("  python3 %s cobrar-de-novo" % sys.argv[0])


def cmd_cobrar_de_novo():
    """M4, o passo decisivo - cobrar o cartao guardado sem CVV.

    Se o Mercado Pago exigir security_code aqui, nao ha debito automatico por
    este caminho, e o Plano B morre. E a mesma pergunta que derrubou o
    Transparente na rodada anterior.
    """
    vendedor = env("MP_SELLER_TOKEN")
    clienteid = env("MP_CUSTOMER_ID")
    cartaoid = env("MP_CARD_ID")
    email = os.environ.get("MP_PAYER_EMAIL", "test_user@testuser.com")
    comissao = round(VALOR * (PERCENTUAL / 100), 2)

    referencia = "prova-ciclo2-" + secrets.token_hex(6)
    status, pagamento = chamar("POST", "/v1/payments", vendedor, {
        "transaction_amount": VALOR,
        "description": "Ciclo 2, sem CVV",
        "installments": 1,
        "payment_method_id": os.environ.get("MP_PAYMENT_METHOD_ID", "master"),
        "token": os.environ.get("MP_CARD_TOKEN_SALVO", ""),
        "payer": {"type": "customer", "id": clienteid, "email": email},
        "external_reference": referencia,
        "application_fee": comissao,
    })

    print("HTTP %s  id=%s  status=%s  detalhe=%s" % (
        status,
        pagamento.get("id"),
        pagamento.get("status"),
        pagamento.get("status_detail"),
    ))

    texto = json.dumps(pagamento).lower()
    if "security_code" in texto or "cvv" in texto:
        print("\nO Mercado Pago EXIGIU o codigo de seguranca.")
        print("Nao ha debito automatico por este caminho - o Plano B cai.")
    elif 200 <= status < 300:
        print("\nCobrou sem CVV. Falta conferir a comissao:")
        print("  python3 %s conferir %s" % (sys.argv[0], pagamento.get("id")))


def cmd_conferir(paymentid):
    """A unica coisa neste arquivo que conta como prova.

    fee_details do pagamento, mais a guarda que da sentido ao resto: se o
    collector_id for o dono da aplicacao, vendedor e marketplace sao a mesma
    conta, nada foi transferido, e o resultado nao prova split.
    """
    vendedor = env("MP_SELLER_TOKEN")
    plataforma = os.environ.get("MP_SUB_TOKEN") or env("MP_BRICKS_TOKEN")

    status, dono = chamar("GET", "/users/me", plataforma)
    donoid = int(dono.get("id") or 0)

    pagamento = exigir("GET", "/v1/payments/" + urllib.parse.quote(paymentid, safe=""), vendedor)

    coletor = int(pagamento.get("collector_id") or 0)
    bruto = float(pagamento.get("transaction_amount") or 0)
    detalhes = pagamento.get("transaction_details") or {}
    liquido = float(detalhes.get("net_received_amount") or 0)

    print("status:      %s" % pagamento.get("status"))
    print("bruto:       %.2f" % bruto)
    print("collector:   %s" % coletor)
    print("dono do app: %s" % donoid)
    if pagamento.get("metadata", {}).get("preapproval_id") or pagamento.get("point_of_interaction"):
        print("origem:      %s" % (pagamento.get("operation_type") or "-"))

    if coletor == donoid:
        erro(
            "o collector_id e o dono da aplicacao: vendedor e marketplace sao a\n"
            "MESMA conta. Nada foi transferido, e este resultado NAO prova split."
        )

    comissao = 0.0
    taxamp = 0.0
    for taxa in pagamento.get("fee_details") or []:
        valor = float(taxa.get("amount") or 0)
        if taxa.get("type") == "application_fee":
            comissao += valor
        else:
            taxamp += valor
        print("fee_details: %-20s %8.2f  (%s)" % (taxa.get("type"), valor, taxa.get("fee_payer")))

    print()
    if comissao <= 0:
        print("NAO HA application_fee neste pagamento.")
        print("A comissao foi descartada em silencio - o sintoma do ADR-0001,")
        print("agora com a aplicacao do tipo certo. Registre e siga para o Plano B.")
        return

    print("comissao da plataforma: %.2f" % comissao)
    print("taxa do Mercado Pago:   %.2f" % taxamp)
    print("liquido do vendedor:    %.2f" % liquido)

    if pagamento.get("status") != "approved":
        print("\nAINDA NAO E PROVA: o pagamento esta '%s'." % pagamento.get("status"))
        return

    print("\nFalta o passo que so o extrato responde: confira o saldo das DUAS")
    print("contas. fee_details diz o que foi cobrado; extrato diz o que chegou.")


def main():
    """Despacha o subcomando."""
    comandos = {
        "contas": cmd_contas,
        "preapproval": cmd_preapproval,
        "escopo": cmd_escopo,
        "advanced": cmd_advanced,
        "assinatura": cmd_assinatura,
        "token-cartao": cmd_token_cartao,
        "cartao": cmd_cartao,
        "cobrar-de-novo": cmd_cobrar_de_novo,
    }

    if len(sys.argv) < 2:
        erro("uso: %s %s|conferir <payment_id>" % (sys.argv[0], "|".join(comandos)))

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
