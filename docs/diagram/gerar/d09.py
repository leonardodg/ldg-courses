#!/usr/bin/env python3
"""09-configuracoes - onde cada ajuste mora e como a comissao e resolvida."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, entity, box, conn, alabel, legend, zone

VW, VH = 1720, 940
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '09-configuracoes.html')
z, n, a, r = [], [], [], []


def painel(x, y, w, nome, linhas, tag, focal=False):
    svg, h = entity(x, y, w, nome, linhas, tag=tag, focal=focal)
    n.append(svg)
    return h


# ===== NIVEL 1: o site, em {config_plugins} ==============================
z.append(zone(40, 88, 1000, 244, 'nivel 1 · site · {config_plugins}'))
painel(64, 136, 300, 'local_marketplace',
       ['defaultfeepercent  comissao padrao', 'commissionbase     gross|net',
        'defaultcountry     ISO alpha-2'], 'SITE')
painel(388, 136, 300, 'local_partners',
       ['enablelanding · frontpagemode', 'maxperhour   limite por IP',
        'requireemailconfirmation', 'unconfirmedretentiondays', 'enablerecaptcha',
        'searchconsoletoken · analyticsid', 'termsurl · privacyurl · cookiesurl'], 'SITE')
painel(712, 136, 300, 'ldgvideo',
       ['printintro   mostra a introducao', 'aspectratio  16:9|9:16|4:3'], 'SITE')

# ===== NIVEL 2: a conta de pagamento, em {payment_gateways}.config =======
z.append(zone(40, 372, 1000, 208, 'nivel 2 · por conta · {payment_gateways}.config'))
painel(64, 420, 300, 'paygw_mercadopago',
       ['mpuserid · siteid · currency', 'accesstoken · refreshtoken',
        'tokenexpires  renovado por cron', 'vem do OAuth, nao do formulario'], 'CONTA')
painel(388, 420, 300, 'paygw_asaas',
       ['apikey_       x sandbox|production', 'walletid_     carteira do split',
        'accountname_ · keytail_'], 'CONTA')
painel(712, 420, 300, 'paygw_pagarme',
       ['apikey_       x sandbox|production', 'platformrecipient_·sellerrecipient_',
        'accountname_ · keytail_'], 'CONTA')

# ===== NIVEL 3: negociado, nas proprias tabelas ==========================
z.append(zone(40, 620, 1000, 184, 'nivel 3 · negociado · nas tabelas'))
painel(64, 668, 300, 'local_marketplace_course',
       ['commissionpct   DEFAULT 25.00', 'commissionbase  NULO = herda'], 'TABELA')
painel(388, 668, 300, 'local_marketplace_company',
       ['commissionpct   NULO = nao negociado', 'commissionbase  NULO = herda'], 'TABELA')
painel(712, 668, 300, 'local_marketplace_plan',
       ['commissionpct   NOT NULL', 'commissionbase  NULO = herda'], 'TABELA')

# ===== A CADEIA ==========================================================
z.append(zone(1080, 88, 600, 716, 'api::resolve_commission() · a cadeia'))
n.append(box(1112, 136, 536, 60, 'policy do curso', sub='_course.commissionpct', tag='1'))
n.append(box(1112, 244, 536, 60, 'empresa', sub='_company.commissionpct', tag='2'))
n.append(box(1112, 352, 536, 60, 'plano da empresa', sub='_plan.commissionpct', tag='3'))
n.append(box(1112, 460, 536, 60, 'padrao do site', sub='defaultfeepercent', tag='4'))
n.append(box(1112, 604, 536, 92, 'feesource gravado na venda',
             sub='policy | company | plan | site', tag='FOTO', kind='focal'))

for y0 in (196, 304, 412):
    a.append(conn([(1380, y0), (1380, y0 + 48)]))
a.append(conn([(1380, 520), (1380, 604)], color=T['accent'], marker='arrow-accent'))

for y0 in (196, 304, 412):
    r.append(alabel(1396, y0 + 24, 'SE NULO', anchor='start'))
r.append(alabel(1396, 562, 'E A BASE SAI DO MESMO DEGRAU', color=T['accent'], anchor='start'))

corpo = '\n\n      '.join(z + a + r + n)
corpo += '\n\n      ' + legend(40, 844, 1640, [
    ('swatch-box', 'ONDE O AJUSTE MORA'),
    ('accent', 'O QUE A VENDA FOTOGRAFA'),
    ('swatch-focal', 'GRAVADO NA LINHA DA VENDA'),
], cols=520)

write_doc(
    OUT,
    'Layer stack · configuracao dos plugins',
    'Configuracao: tres niveis e a cadeia da comissao',
    'Onde vive cada ajuste dos plugins - o nivel do site em config_plugins, o '
    'nivel da conta de pagamento dentro do JSON de payment_gateways, e o nivel '
    'negociado nas proprias tabelas - mais a ordem em que a comissao e '
    'resolvida a cada venda.',
    VW, VH, corpo, minw=1200,
    footer='Fontes: os settings.php de cada plugin e as classes gateway.php/credentials.php. A '
           'credencial do vendedor NAO fica em config_plugins: ela vive na conta de pagamento do '
           'core, no contexto da categoria da empresa - e por isso que uma empresa que vende no '
           'Brasil e na Argentina precisa de duas contas. O environment viaja junto porque uma '
           'cobranca de homologacao nao pode ser consultada com chave de producao. Na cadeia, a '
           'BASE sai do mesmo degrau que deu a TAXA: coluna nula significa "herda a do site", que e '
           'diferente de escolher bruto. O feesource existe para o relatorio poder explicar de onde '
           'aqueles 9,9% vieram.')
