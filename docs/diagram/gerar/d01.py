#!/usr/bin/env python3
"""01-er-mestre - as 16 tabelas proprias e as 8 do core que elas tocam."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, entity, conn, alabel, legend, zone

VW, VH = 2160, 1440
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '01-er-mestre.html')
zonas, caixas, arestas, rotulos = [], [], [], []

E = {}      # nome -> (x, y, w, h)


def put(nome, x, y, w, campos, tag='ENTITY', focal=False, external=False):
    svg, h = entity(x, y, w, nome, campos, tag=tag, focal=focal, external=external)
    caixas.append(svg)
    E[nome] = (x, y, w, h)
    return h


def cx(n):
    x, y, w, h = E[n]
    return x + w / 2


def cy(n):
    x, y, w, h = E[n]
    return y + h / 2


# ======================= ZONA A - COMERCIAL ==============================
zonas.append(zone(40, 88, 620, 620, 'comercial · local_marketplace'))
put('local_marketplace_plan_tier', 64, 132, 248,
    ['# id', '> planid -> _plan', 'maxprice  NULO = faixa sem teto',
     'maxresolution  720p..4k', '+ 4 outras colunas'])
put('local_marketplace_plan', 64, 300, 248,
    ['# id', 'shortname  UQ', 'commissionpct · commissionbase',
     'monthlyfee · country · currency', 'hostingmodel  native|byos',
     '+ 10 outras colunas'])
put('local_marketplace_account', 64, 508, 248,
    ['# id', '> companyid -> _company', '> accountid -> payment_accounts  UQ',
     'country  UMA CONTA POR PAIS', '+ 3 outras colunas'])
put('local_marketplace_member', 352, 132, 284,
    ['# id', '> companyid -> _company', '> userid -> user',
     'memberrole  owner|seller', '+ 3 outras colunas'])
put('local_marketplace_company', 352, 300, 284,
    ['# id', '> planid -> _plan  NULO = fora de plano',
     '> categoryid -> course_categories', 'shortname UQ · hostname UQ',
     'commissionpct · commissionbase', '+ 11 outras colunas'], focal=True)

# ======================= ZONA B - VENDA E ACESSO =========================
zonas.append(zone(700, 88, 680, 620, 'venda e acesso · local_marketplace'))
put('local_marketplace_offer_course', 724, 132, 284,
    ['# id', '> offerid -> _offer', '> courseid -> course',
     'N:N  combo libera varios cursos', 'offerid-courseid  UQ'])
put('local_marketplace_offer', 724, 300, 284,
    ['# id', '> companyid -> _company', 'offertype  single|bundle|catalog',
     'price · country · currency', 'accessmode  lifetime|days|recurring',
     '+ 11 outras colunas'])
put('local_marketplace_course', 724, 508, 284,
    ['# id', '> courseid -> course  UQ', '> companyid -> _company',
     'hostingtype  external|platform', '+ 5 outras colunas'])
put('local_marketplace_entitlement', 1072, 132, 284,
    ['# id', '> userid -> user', '> offerid -> _offer',
     '> companyid -> _company  desnormalizado', 'timeend  0 = vitalicio · cycles · norenew',
     '+ 6 outras colunas'])
put('local_marketplace_sale', 1072, 340, 284,
    ['# id', '> paymentid -> payments  UQ', '> offerid -> _offer',
     '> companyid -> _company', 'feepercent · feebase · feesource',
     '+ 6 outras colunas'])

# ======================= ZONA C - GATEWAYS ===============================
zonas.append(zone(1420, 88, 700, 620, 'gateways · paygw_*'))
put('paygw_mercadopago', 1444, 132, 320,
    ['# id', '> userid -> user', '> itemid -> _offer', '> accountid -> payment_accounts',
     'preferenceid · externalreference UQ', '+ 14 outras colunas'])
put('paygw_asaas', 1444, 312, 320,
    ['# id', '> userid -> user', '> itemid -> _offer', '> accountid -> payment_accounts',
     'subscriptionid  liga os ciclos', '+ 17 outras colunas'])
put('paygw_pagarme', 1444, 492, 320,
    ['# id', '> userid -> user', '> itemid -> _offer', '> accountid -> payment_accounts',
     'subscriptionid · chargeid · orderid', '+ 20 outras colunas'])

# ======================= ZONA D - CAPTACAO ===============================
zonas.append(zone(40, 760, 340, 216, 'captacao · local_partners'))
put('local_partners_application', 64, 808, 284,
    ['# id', '. planid -> _plan   (sem FK)', '. companyid -> _company  (sem FK)',
     '. userid / reviewerid -> user', 'status  unconfirmed|pending|approved|rejected',
     '+ 19 outras colunas'])

# ======================= ZONA F - CONTEUDO ===============================
zonas.append(zone(1020, 952, 568, 200, 'conteudo · mod_ldgvideo + format_ldg'))
put('ldgvideo', 1044, 1000, 248,
    ['# id', '. course -> course', 'videourl  o endereco, nunca o arquivo',
     'aspectratio  16:9|9:16|4:3', '+ 5 outras colunas'])
put('format_ldg_lesson', 1316, 1000, 248,
    ['# id', '. cmid -> course_modules  UQ', 'duration  NULO = desconhecida',
     'sem campo de usuario nenhum', 'null_provider de privacidade'])

# ======================= CORE DO MOODLE ==================================
put('course_categories', 424, 808, 248,
    ['# id', 'name · parent · path', 'theme  o tema da empresa',
     'a empresa E uma categoria'], tag='CORE', external=True)
put('user', 712, 808, 284,
    ['# id', 'referenciada por 9 tabelas nossas:', 'member · entitlement · os 3 paygw',
     'application (userid e reviewerid)', 'payments · user_enrolments'],
    tag='CORE', external=True)
put('payments', 1444, 808, 320,
    ['# id', 'component · paymentarea · itemid',
     'userid · amount · currency · gateway', '. accountid -> payment_accounts',
     'FONTE DA VERDADE FINANCEIRA'], tag='CORE', external=True)
put('payment_accounts', 1808, 808, 280,
    ['# id', 'contextid = categoria da empresa', 'enabled',
     'referenciada por _account e', 'pelos 3 paygw'], tag='CORE', external=True)
put('course', 424, 1000, 248,
    ['# id', '. category -> course_categories', 'referenciada por _offer_course,',
     '_course, ldgvideo e enrol'], tag='CORE', external=True)
put('course_modules', 712, 1000, 284,
    ['# id', '. instance -> ldgvideo', 'availability  JSON, e onde o',
     'availability_marketplace guarda', '{"type":"marketplace","offerid":N}'],
    tag='CORE', external=True)
put('enrol', 1640, 1000, 248,
    ['# id', '. courseid -> course', "enrol = 'marketplace'",
     'o enrol_marketplace vive aqui'], tag='CORE', external=True)
put('user_enrolments', 1640, 1160, 248,
    ['# id', '. enrolid -> enrol', '. userid -> user',
     'timeend  projecao do direito'], tag='CORE', external=True)

# ======================= ARESTAS =========================================
# Zona A: tudo reto
arestas.append(conn([(cx('local_marketplace_plan_tier'), 132 + E['local_marketplace_plan_tier'][3]),
                     (cx('local_marketplace_plan_tier'), 300)]))
arestas.append(conn([(cx('local_marketplace_member'), 132 + E['local_marketplace_member'][3]),
                     (cx('local_marketplace_member'), 300)]))
# company.planid -> plan
arestas.append(conn([(352, cy('local_marketplace_company')), (332, cy('local_marketplace_company')),
                     (332, cy('local_marketplace_plan')), (312, cy('local_marketplace_plan'))]))
# account.companyid -> company
arestas.append(conn([(312, cy('local_marketplace_account')), (332, cy('local_marketplace_account')),
                     (332, 400), (352, 400)]))
# company.categoryid -> course_categories
arestas.append(conn([(494, 300 + E['local_marketplace_company'][3]), (494, 808)]))

# Zona B
arestas.append(conn([(cx('local_marketplace_offer_course'), 132 + E['local_marketplace_offer_course'][3]),
                     (cx('local_marketplace_offer_course'), 300)]))
arestas.append(conn([(cx('local_marketplace_course'), 508), (cx('local_marketplace_course'),
                     300 + E['local_marketplace_offer'][3])]))
# entitlement.offerid -> offer
arestas.append(conn([(1072, cy('local_marketplace_entitlement')), (1040, cy('local_marketplace_entitlement')),
                     (1040, 356), (1008, 356)]))
# sale.offerid -> offer
arestas.append(conn([(1072, cy('local_marketplace_sale')), (1024, cy('local_marketplace_sale')),
                     (1024, 380), (1008, 380)]))
# offer.companyid -> company: a espinha entre as duas zonas
arestas.append(conn([(724, cy('local_marketplace_offer')), (636, cy('local_marketplace_offer'))],
                    color=T['accent'], marker='arrow-accent'))

# Zona C -> payments
arestas.append(conn([(1444, cy('paygw_mercadopago')), (1408, cy('paygw_mercadopago')),
                     (1408, 852), (1444, 852)], dashed=True))
arestas.append(conn([(1764, cy('paygw_asaas')), (1796, cy('paygw_asaas')),
                     (1796, 880), (1764, 880)], dashed=True))
arestas.append(conn([(cx('paygw_pagarme'), 492 + E['paygw_pagarme'][3]),
                     (cx('paygw_pagarme'), 808)], dashed=True))
# sale.paymentid -> payments
arestas.append(conn([(cx('local_marketplace_sale'), 340 + E['local_marketplace_sale'][3]),
                     (cx('local_marketplace_sale'), 760), (1520, 760), (1520, 808)]))

# Conteudo
arestas.append(conn([(996, cy('ldgvideo')), (1044, cy('ldgvideo'))], dashed=True))
arestas.append(conn([(1316, 1000 + E['format_ldg_lesson'][3]), (1316, 1284),
                     (854, 1284), (854, 1000 + E['course_modules'][3])], dashed=True))
# user_enrolments.enrolid -> enrol
arestas.append(conn([(cx('user_enrolments'), 1160), (cx('user_enrolments'),
                     1000 + E['enrol'][3])]))

rotulos.append(alabel(680, cy('local_marketplace_offer') - 14, 'PERTENCE A', color=T['accent']))

corpo = '\n\n      '.join(zonas + arestas + rotulos + caixas)
corpo += '\n\n      ' + legend(40, 1320, 2080, [
    ('accent', 'A ESPINHA: EMPRESA -> OFERTA'),
    ('solid', 'FK DECLARADA NO INSTALL.XML'),
    ('dashed', 'VINCULO REAL SEM FK DECLARADA'),
    ('swatch-focal', 'RAIZ AGREGADA'),
    ('swatch-ext', 'TABELA DO CORE DO MOODLE'),
], cols=410)

write_doc(
    OUT,
    'ER / data model · plugins LeoDG',
    'Mapa do banco: 16 tabelas proprias e 8 do core',
    'Mapa completo do modelo de dados dos onze plugins desenvolvidos para a '
    'plataforma, em quatro clusters - comercial, venda e acesso, gateways de '
    'pagamento e conteudo - mais as tabelas do core do Moodle que eles '
    'referenciam.',
    VW, VH, corpo, minw=1400,
    footer='Fonte: os sete db/install.xml, lidos em 14/09/2026. Dentro de cada caixa, "#" e chave '
           'primaria, ">" e FK declarada no XMLDB e "." e vinculo real SEM FK - cada omissao dessas '
           'tem motivo registrado no proprio XMLDB. Nem toda relacao vira linha: com 24 entidades e '
           'mais de 40 referencias, desenhar uma seta por FK seria o anti-padrao que o type-er.md '
           'nomeia. As linhas carregam a estrutura; as referencias de fan-in (user, com 9, e '
           'payment_accounts, com 5) estao listadas dentro da propria caixa. O detalhe coluna a '
           'coluna esta nos diagramas 02 a 05.')
