#!/usr/bin/env python3
"""06-arquitetura - quem ve o que, quem escreve onde, e o que sai do site."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, entity, conn, alabel, legend, zone

VW, VH = 2000, 800
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '06-arquitetura.html')
z, n, a, r = [], [], [], []


def painel(x, y, w, nome, linhas, tag, focal=False, external=False):
    svg, h = entity(x, y, w, nome, linhas, tag=tag, focal=focal, external=external)
    n.append(svg)
    return h


# ===== COLUNA A - quem entra, e por onde ================================
z.append(zone(40, 88, 460, 600, 'quem entra · uma coluna por permissao'))
painel(64, 120, 412, 'Visitante', [
    'partners/index.php   a landing', 'partners/apply.php   candidatura',
    'partners/confirm.php · thanks.php', 'partners/sitemap.php'], 'PUBLICO')
painel(64, 252, 412, 'Aluno', [
    'marketplace/offers.php   vitrine', 'marketplace/claim.php    oferta gratis',
    'marketplace/mysubscriptions.php', 'marketplace/cancel.php',
    'block_marketplace   no Dashboard'], 'LOGADO')
painel(64, 398, 412, 'Vendedor', [
    'marketplace/company.php  painel', 'marketplace/offer_edit.php',
    'marketplace/report.php   4 relatorios', 'marketplace/refund.php   estorno',
    'marketplace/resend.php   2a via'], 'CAPABILITY')
painel(64, 544, 412, 'Admin do site', [
    'admin/companies · company_edit · members', 'admin/plans.php · plan_edit.php',
    'partners/admin/applications · _view', 'settings.php dos 6 plugins  -> 09'], 'SITEADMIN')

# ===== COLUNA B - o que o site executa ==================================
z.append(zone(540, 88, 420, 600, 'o que o site executa'))
painel(564, 120, 372, 'core_payment', [
    'service_provider do marketplace', 'get_payable() nao recebe o usuario'],
    'CORE', external=True)
painel(564, 224, 372, 'Endpoints dos gateways', [
    'webhook.php   chega sem sessao', 'return.php    volta do checkout',
    'link.php · unlink.php', 'oauth_*.php   so no Mercado Pago',
    'pix.php · card.php  so no Pagar.me'], 'HTTP')
painel(564, 490, 372, 'Tarefas agendadas', [
    'notify_expiring   06:30, marcos 5 e 1', 'reconcile         nos 3 gateways',
    'refresh_tokens    so no Mercado Pago'], 'CRON')

# ===== COLUNA C - o banco ===============================================
painel(1000, 224, 400, 'Banco', [
    'local_marketplace_*        10 tabelas', 'paygw_mercadopago|asaas|pagarme',
    'local_partners_application', 'format_ldg_lesson · ldgvideo',
    'core: payments · payment_accounts', 'core: enrol · user_enrolments',
    'core: course_categories · course', '---- o direito manda no acesso ----'],
    'MYSQL', focal=True)

# ===== COLUNA D - fora do site, e o que pinta a tela ====================
painel(1520, 224, 440, 'APIs externas', [
    'Mercado Pago   Checkout Pro + split', 'Asaas          Pix|boleto|cartao + assinatura',
    'Pagar.me       Pix|boleto|cartao + assinatura'], 'HTTPS', external=True)
painel(1520, 350, 440, 'Apresentacao e acesso', [
    'theme_ldg          o tema',
    'format_ldg         portal do aluno',
    'mod_ldgvideo       aula em video',
    'enrol_marketplace  matricula por diferenca',
    'availability_marketplace  libera secao',
    'block_marketplace  avisos do aluno'], 'PLUGINS')

# ===== ARESTAS ==========================================================
a.append(conn([(476, 309), (510, 309), (510, 156), (540, 156)]))          # aluno -> checkout
a.append(conn([(750, 192), (750, 224)]))                                  # checkout -> endpoints
a.append(conn([(936, 265), (970, 265), (970, 190), (1740, 190), (1740, 224)],
              color=T['link'], marker='arrow-link'))                      # endpoints -> APIs
a.append(conn([(936, 297), (1000, 297)]))                                 # endpoints -> banco
a.append(conn([(1520, 281), (1490, 281), (1490, 340), (1400, 340)],
              color=T['accent'], marker='arrow-accent'))                  # webhook escreve
a.append(conn([(476, 455), (960, 455), (960, 344), (1000, 344)]))         # vendedor -> banco
a.append(conn([(936, 533), (976, 533), (976, 360), (1000, 360)]))         # cron -> banco
a.append(conn([(476, 594), (992, 594), (992, 376), (1000, 376)]))         # admin -> banco
a.append(conn([(1520, 414), (1490, 414), (1490, 368), (1400, 368)]))      # apresentacao le

r.append(alabel(1340, 176, 'CRIA A COBRANCA', color=T['link']))
r.append(alabel(1445, 326, 'ENTREGA', color=T['accent']))

corpo = '\n\n      '.join(z + a + r + n)
corpo += '\n\n      ' + legend(40, 720, 1920, [
    ('solid', 'LEITURA E ESCRITA NO BANCO'),
    ('link', 'CHAMADA HTTP PARA FORA'),
    ('accent', 'A ENTREGA, VINDA DO WEBHOOK'),
    ('swatch-ext', 'FORA DOS PLUGINS'),
], cols=470)

write_doc(
    OUT,
    'Architecture · plugins LeoDG',
    'Arquitetura: paginas, admin, banco e o que sai do site',
    'Arquitetura dos onze plugins: as paginas agrupadas pela permissao que cada '
    'uma exige, os endpoints que os gateways chamam, as tarefas agendadas, o '
    'banco no centro e as APIs externas.',
    VW, VH, corpo, minw=1300,
    footer='Caminhos relativos a public/local/, public/payment/gateway/<nome>/ e public/blocks/. A '
           'coluna da esquerda e uma escala de permissao, nao de importancia: partners/index.php e '
           'apply.php sao publicas de proposito - o visitante que elas querem atingir e justamente '
           'quem ainda nao tem conta. O banco esta no centro porque e o unico lugar onde os onze '
           'plugins se encontram: nenhum deles chama o outro por PHP. E o direito de acesso, dentro '
           'dele, e a fonte unica da verdade - a matricula e a liberacao de secao leem de la, nunca '
           'da venda. As configuracoes de cada plugin estao no diagrama 09.')
