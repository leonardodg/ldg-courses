#!/usr/bin/env python3
"""03-er-venda-acesso - oferta, direito de acesso, venda e o pagamento do core."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, table, box, conn, alabel, legend

VW, VH = 1560, 780
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '03-er-venda-acesso.html')
p = []

offer_svg, offer_cy, _ = table(
    400, 56, 300, 'local_marketplace_offer',
    [('id', ['PK'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('name', ['NN'], 'char(255)'),
     ('offertype', ['NN'], 'char(20)'),
     ('price', ['NN'], 'number(10,2)'),
     ('country', ['NN'], 'char(2)'),
     ('accessmode', ['NN'], 'char(20)'),
     ('+ 10 outras colunas', [], '')],
    indexes=['companyid-status', 'companyid-country'], chip_x=400 + 120)

oc_svg, oc_cy, _ = table(
    400, 392, 300, 'local_marketplace_offer_course',
    [('id', ['PK'], 'int(10)'),
     ('offerid', ['FK', 'NN'], 'int(10)'),
     ('courseid', ['FK', 'NN'], 'int(10)')],
    indexes=['offerid-courseid (unique)'], chip_x=400 + 120)

ent_svg, ent_cy, _ = table(
    780, 56, 300, 'local_marketplace_entitlement',
    [('id', ['PK'], 'int(10)'),
     ('userid', ['FK', 'NN'], 'int(10)'),
     ('offerid', ['FK', 'NN'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('timeend', ['NN'], 'int(10)'),
     ('status', ['NN'], 'char(20)'),
     ('cycles', ['NN'], 'int(10)'),
     ('+ 5 outras colunas', [], '')],
    indexes=['userid-status', 'userid-companyid'], focal=True, chip_x=780 + 120)

sale_svg, sale_cy, _ = table(
    780, 392, 300, 'local_marketplace_sale',
    [('id', ['PK'], 'int(10)'),
     ('paymentid', ['FK', 'UQ', 'NN'], 'int(10)'),
     ('offerid', ['FK', 'NN'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('feeamount', ['NN'], 'number(10,2)'),
     ('feepercent', ['NN'], 'number(5,2)'),
     ('feesource', ['NN'], 'char(10)'),
     ('+ 5 outras colunas', [], '')],
    indexes=['companyid-timecreated'], chip_x=780 + 120)

pay_svg, pay_cy, _ = table(
    1160, 416, 320, 'payments',
    [('id', ['PK'], 'int(10)'),
     ('component', ['NN'], 'char(100)'),
     ('paymentarea', ['NN'], 'char(50)'),
     ('itemid', ['NN'], 'int(10)'),
     ('userid', ['NN'], 'int(10)'),
     ('gateway', ['NN'], 'char(100)'),
     ('+ 5 outras colunas', [], '')],
    tag='CORE', note='valor, moeda e comprador vivem AQUI', chip_x=1160 + 140)

# --- arestas -------------------------------------------------------------
# offer_course.offerid -> offer.id  (pela esquerda, corredor proprio)
p.append(conn([(400, oc_cy['offerid']), (360, oc_cy['offerid']),
               (360, offer_cy['id']), (400, offer_cy['id'])]))
# entitlement.offerid -> offer.id
p.append(conn([(780, ent_cy['offerid']), (736, ent_cy['offerid']),
               (736, offer_cy['id'] - 8), (700, offer_cy['id'] - 8)]))
# sale.offerid -> offer.id
p.append(conn([(780, sale_cy['offerid']), (756, sale_cy['offerid']),
               (756, offer_cy['id'] + 8), (700, offer_cy['id'] + 8)]))
# sale.paymentid -> payments.id  (foreign-unique: uma venda por pagamento)
p.append(conn([(1080, sale_cy['paymentid']), (1160, pay_cy['id'])]))
# entitlement.userid -> {user}: a cascata de privacidade
p.append(conn([(1080, ent_cy['userid']), (1160, ent_cy['userid'])],
              color=T['accent'], marker='arrow-accent'))
# offer_course.courseid -> {course}
p.append(conn([(400, oc_cy['courseid']), (320, oc_cy['courseid'])]))

p.append(alabel(1120, ent_cy['userid'] - 14, 'PURGA LGPD', color=T['accent']))
p.append(alabel(1120, pay_cy['id'] - 14, 'UNIQUE'))

for t in (offer_svg, oc_svg, ent_svg, sale_svg, pay_svg):
    p.append(t)

p.append(box(1160, ent_cy['userid'] - 20, 160, 40, '{user}', sub='core', kind='external'))
p.append(box(160, oc_cy['courseid'] - 20, 160, 40, '{course}', sub='core', kind='external'))

p.append(legend(40, 688, 1480, [
    ('solid', 'FK DECLARADA NO INSTALL.XML'),
    ('accent', 'EXCLUSAO EM CASCATA (PRIVACY)'),
    ('swatch-focal', 'FONTE DA VERDADE DO ACESSO'),
    ('swatch-ext', 'TABELA DO CORE DO MOODLE'),
], cols=360))

write_doc(
    OUT,
    'Database schema · plugins LeoDG',
    'Venda e acesso: o que se vende e o que se libera',
    'Esquema fisico da oferta, dos cursos que ela libera, do direito de acesso '
    'do aluno, da venda com os termos de comissao fotografados, e do registro de '
    'pagamento do core que serve de fonte da verdade financeira.',
    VW, VH, '\n\n      '.join(p), minw=1100,
    footer='Fonte: public/local/marketplace/db/install.xml. O direito de acesso e a fonte unica '
           'da verdade: o enrol_marketplace e o availability_marketplace consultam entitlement, '
           'nunca a venda. A venda guarda so o que o core nao guarda - comissao retida e id da '
           'transacao no gateway; valor, moeda e comprador ficam em payments.')
