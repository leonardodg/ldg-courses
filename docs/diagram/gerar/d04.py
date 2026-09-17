#!/usr/bin/env python3
"""04-er-gateways - as tres pontes para o gateway e o que elas fazem na LGPD."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, table, box, conn, alabel, legend

VW, VH = 1440, 920
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '04-er-gateways.html')
p = []

mp_svg, mp_cy, _ = table(
    40, 56, 340, 'paygw_mercadopago',
    [('id', ['PK'], 'int(10)'),
     ('externalreference', ['UQ', 'NN'], 'char(64)'),
     ('preferenceid', ['NN'], 'char(128)'),
     ('userid', ['FK', 'NN'], 'int(10)'),
     ('feeamount', ['NN'], 'number(10,2)'),
     ('feebase', ['NN'], 'char(10)'),
     ('paymentid', [], 'int(10)'),
     ('+ 12 outras colunas', [], '')],
    indexes=['externalreference (unique)', 'mppaymentid'],
    note='privacy: APAGA a linha do usuario', focal=True, chip_x=40 + 168)

as_svg, as_cy, _ = table(
    40, 396, 340, 'paygw_asaas',
    [('id', ['PK'], 'int(10)'),
     ('externalreference', ['UQ', 'NN'], 'char(64)'),
     ('asaaspaymentid', [], 'char(64)'),
     ('subscriptionid', [], 'char(64)'),
     ('userid', ['FK', 'NN'], 'int(10)'),
     ('feebase', ['NN'], 'char(10)'),
     ('paymentid', [], 'int(10)'),
     ('+ 15 outras colunas', [], '')],
    indexes=['externalreference (unique)', 'subscriptionid'],
    note='privacy: RETEM - obrigacao fiscal', chip_x=40 + 168)

pg_svg, pg_cy, _ = table(
    1060, 230, 340, 'paygw_pagarme',
    [('id', ['PK'], 'int(10)'),
     ('externalreference', ['UQ', 'NN'], 'char(64)'),
     ('chargeid', [], 'char(64)'),
     ('subscriptionid', [], 'char(64)'),
     ('userid', ['FK', 'NN'], 'int(10)'),
     ('feebase', ['NN'], 'char(10)'),
     ('paymentid', [], 'int(10)'),
     ('+ 18 outras colunas', [], '')],
    indexes=['externalreference (unique)', 'subscriptionid'],
    note='privacy: RETEM - obrigacao fiscal', chip_x=1060 + 168)

pay_svg, pay_cy, _ = table(
    560, 360, 320, 'payments',
    [('id', ['PK'], 'int(10)'),
     ('component', ['NN'], 'char(100)'),
     ('paymentarea', ['NN'], 'char(50)'),
     ('itemid', ['NN'], 'int(10)'),
     ('userid', ['NN'], 'int(10)'),
     ('accountid', ['NN'], 'int(10)'),
     ('+ 5 outras colunas', [], '')],
    tag='CORE', chip_x=560 + 140)

pa_svg, pa_cy, _ = table(
    560, 640, 320, 'payment_accounts',
    [('id', ['PK'], 'int(10)'),
     ('name', ['NN'], 'char(255)'),
     ('contextid', ['NN'], 'int(10)'),
     ('enabled', ['NN'], 'int(1)'),
     ('+ 4 outras colunas', [], '')],
    tag='CORE', note='contexto = categoria da empresa', chip_x=560 + 140)

# --- arestas: paymentid nasce NULO e so e escrito na confirmacao ---------
p.append(conn([(380, mp_cy['paymentid']), (500, mp_cy['paymentid']),
               (500, pay_cy['id'] - 8), (560, pay_cy['id'] - 8)], dashed=True))
p.append(conn([(380, as_cy['paymentid']), (440, as_cy['paymentid']),
               (440, pay_cy['id'] + 8), (560, pay_cy['id'] + 8)], dashed=True))
p.append(conn([(1060, pg_cy['paymentid']), (980, pg_cy['paymentid']),
               (980, pay_cy['id']), (880, pay_cy['id'])], dashed=True))
# payments.accountid -> payment_accounts.id
p.append(conn([(880, pay_cy['accountid']), (920, pay_cy['accountid']),
               (920, pa_cy['id']), (880, pa_cy['id'])], dashed=True))

for t in (mp_svg, as_svg, pg_svg, pay_svg, pa_svg):
    p.append(t)

p.append(legend(40, 828, 1360, [
    ('dashed', 'VINCULO SEM FK DECLARADA'),
    ('swatch-focal', 'DIVERGE DAS IRMAS NA EXCLUSAO'),
    ('swatch-ext', 'TABELA DO CORE DO MOODLE'),
], cols=440))

write_doc(
    OUT,
    'Database schema · plugins LeoDG',
    'Gateways: tres pontes para o mesmo registro de pagamento',
    'Esquema fisico das tres tabelas de transacao - Mercado Pago, Asaas e '
    'Pagar.me - que ligam a cobranca criada no gateway ao registro de pagamento '
    'do core, e a conta que recebe.',
    VW, VH, '\n\n      '.join(p), minw=1100,
    footer='Fonte: os tres public/payment/gateway/*/db/install.xml. Nenhuma das tres declara FK '
           'para payments: a coluna paymentid nasce nula e so e escrita na confirmacao - e o que '
           'impede entrega dupla. Divergencia registrada: o paygw_mercadopago APAGA a linha na '
           'exclusao por privacidade; o paygw_asaas e o paygw_pagarme a RETEM, e o comentario de '
           'classe de ambos justifica - registro de pagamento e obrigacao fiscal do vendedor. Os '
           'tres tambem guardam accountid apontando para payment_accounts, sem FK declarada.')
