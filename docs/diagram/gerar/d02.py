#!/usr/bin/env python3
"""02-er-comercial - empresa, plano, faixas, conta e vendedores."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, table, box, conn, alabel, legend

VW, VH = 1440, 820
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '02-er-comercial.html')
p = []

# --- tabelas -------------------------------------------------------------
plan_svg, plan_cy, _ = table(
    40, 56, 300, 'local_marketplace_plan',
    [('id', ['PK'], 'int(10)'),
     ('shortname', ['UQ', 'NN'], 'char(50)'),
     ('name', ['NN'], 'char(255)'),
     ('monthlyfee', ['NN'], 'number(10,2)'),
     ('commissionpct', ['NN'], 'number(5,2)'),
     ('commissionbase', [], 'char(10)'),
     ('country', ['NN'], 'char(2)'),
     ('+ 9 outras colunas', [], '')],
    indexes=['shortname (unique)', 'status-sortorder'], chip_x=40 + 120)

tier_svg, tier_cy, _ = table(
    40, 392, 300, 'local_marketplace_plan_tier',
    [('id', ['PK'], 'int(10)'),
     ('planid', ['FK', 'NN'], 'int(10)'),
     ('maxprice', [], 'number(10,2)'),
     ('maxresolution', ['NN'], 'char(10)'),
     ('sortorder', ['NN'], 'int(10)'),
     ('+ 3 outras colunas', [], '')],
    indexes=['planid-sortorder'], chip_x=40 + 120)

comp_svg, comp_cy, _ = table(
    440, 140, 320, 'local_marketplace_company',
    [('id', ['PK'], 'int(10)'),
     ('name', ['NN'], 'char(255)'),
     ('shortname', ['UQ', 'NN'], 'char(100)'),
     ('categoryid', ['FK'], 'int(10)'),
     ('commissionpct', [], 'number(5,2)'),
     ('commissionbase', [], 'char(10)'),
     ('planid', ['FK'], 'int(10)'),
     ('+ 10 outras colunas', [], '')],
    indexes=['shortname (unique)', 'hostname (unique)'],
    note='categoryid -> {course_categories}.id', chip_x=440 + 132)

memb_svg, memb_cy, _ = table(
    860, 56, 300, 'local_marketplace_member',
    [('id', ['PK'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('userid', ['FK', 'NN'], 'int(10)'),
     ('memberrole', ['NN'], 'char(20)'),
     ('+ 3 outras colunas', [], '')],
    indexes=['companyid-userid (unique)'], focal=True, chip_x=860 + 120)

acct_svg, acct_cy, _ = table(
    860, 360, 300, 'local_marketplace_account',
    [('id', ['PK'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('country', ['NN'], 'char(2)'),
     ('accountid', ['FK', 'UQ', 'NN'], 'int(10)'),
     ('+ 3 outras colunas', [], '')],
    indexes=['companyid-country (unique)'], chip_x=860 + 120)

lib_svg, lib_cy, _ = table(
    440, 460, 320, 'local_marketplace_library',
    [('id', ['PK'], 'int(10)'),
     ('companyid', ['FK', 'UQ', 'NN'], 'int(10)'),
     ('bunnylibraryid', ['UQ', 'NN'], 'int(10)'),
     ('apikey', ['NN'], 'text'),
     ('origin', ['NN'], 'char(20)'),
     ('+ 7 outras colunas', [], '')],
    indexes=['bunnylibraryid (unique)', 'webhooksecret (unique)'],
    note='origin decide autoridade: platform ou byos', chip_x=440 + 132)

# --- arestas primeiro (z-order) -----------------------------------------
# 1. company.planid -> plan.id
p.append(conn([(440, comp_cy['planid']), (390, comp_cy['planid']),
               (390, plan_cy['id'] - 8), (340, plan_cy['id'] - 8)]))
# 2. plan_tier.planid -> plan.id
p.append(conn([(340, tier_cy['planid']), (406, tier_cy['planid']),
               (406, plan_cy['id'] + 8), (340, plan_cy['id'] + 8)]))
# 3. member.companyid -> company.id
p.append(conn([(860, memb_cy['companyid']), (810, memb_cy['companyid']),
               (810, comp_cy['id'] - 8), (760, comp_cy['id'] - 8)]))
# 4. account.companyid -> company.id
p.append(conn([(860, acct_cy['companyid']), (794, acct_cy['companyid']),
               (794, comp_cy['id'] + 8), (760, comp_cy['id'] + 8)]))
# 5. member.userid -> {user}.id  - a unica exclusao em cascata que existe
p.append(conn([(1160, memb_cy['userid']), (1240, memb_cy['userid'])],
              color=T['accent'], marker='arrow-accent'))
# 6. account.accountid -> {payment_accounts}.id
p.append(conn([(1160, acct_cy['accountid']), (1240, acct_cy['accountid'])]))
# 7. library.companyid -> company.id (empresa nasce com 1 library, 1:1)
p.append(conn([(440, lib_cy['companyid']), (400, lib_cy['companyid']),
               (400, comp_cy['id']), (440, comp_cy['id'])]))

# --- rotulos: so onde acrescentam informacao ----------------------------
p.append(alabel(1200, memb_cy['userid'] - 14, 'PURGA LGPD', color=T['accent']))
p.append(alabel(1200, acct_cy['accountid'] - 14, 'UNIQUE'))

# --- caixas --------------------------------------------------------------
for t in (plan_svg, tier_svg, comp_svg, memb_svg, acct_svg, lib_svg):
    p.append(t)

# referencias externas ao plugin
p.append(box(1240, memb_cy['userid'] - 20, 160, 40, '{user}', sub='core', kind='external'))
p.append(box(1240, acct_cy['accountid'] - 20, 160, 40, '{payment_accounts}', sub='core',
             kind='external'))

# --- legenda -------------------------------------------------------------
p.append(legend(40, 744, 1360, [
    ('solid', 'FK DECLARADA NO INSTALL.XML'),
    ('accent', 'EXCLUSAO EM CASCATA (PRIVACY)'),
    ('swatch-focal', 'TABELA QUE A CASCATA APAGA'),
    ('swatch-ext', 'TABELA DO CORE DO MOODLE'),
], cols=330))

body = '\n\n      '.join(p)

write_doc(
    OUT,
    'Database schema · plugins LeoDG',
    'Subsistema comercial: empresa, plano e conta de recebimento',
    'Esquema fisico das seis tabelas que definem uma empresa vendedora no '
    'marketplace, o plano que ela contrata, as faixas de resolucao do plano, a '
    'conta de pagamento por pais, os vendedores ligados a ela e a library de '
    'video (Bunny) que a atende.',
    VW, VH, body, minw=1100,
    footer='Fonte: public/local/marketplace/db/install.xml. O Moodle nao declara ON DELETE no '
           'XMLDB - a integridade e de aplicacao. A unica exclusao em cascata do subsistema esta '
           'no privacy provider: apagar um usuario apaga member e entitlement, e nao apaga sale. '
           'Em local_marketplace_library, origin (platform|byos) decide quem tem autoridade para '
           'mexer na library - nativa (provisionada pela plataforma) ou BYOS (conectada pelo '
           'produtor); nao basta olhar o plano ATUAL da empresa, porque o plano pode ter mudado '
           'depois da conexao (ver ADR-0014 e docs/ai-plans/2026-09-25-bunny-multi-tenant-trava-resolucao.md).')

