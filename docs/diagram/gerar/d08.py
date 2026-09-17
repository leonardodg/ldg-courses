#!/usr/bin/env python3
"""08-fluxo-aluno - da vitrine ate a matricula, com e sem dinheiro no caminho."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, box, oval, diamond, conn, alabel, legend

VW, VH = 1400, 1340
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '08-fluxo-aluno.html')
a, n, r = [], [], []

n.append(oval(520, 56, 340, 48, 'Aluno na vitrine'))
n.append(box(520, 152, 340, 56, 'offers.php', sub='ofertas da empresa', tag='VITRINE'))

d1, p1 = diamond(690, 340, 300, 96, 'Oferta gratuita?', sub='price = 0')
n.append(d1)

n.append(box(160, 440, 300, 60, 'claim.php', sub='adesao sem gateway', tag='GRATIS'))

n.append(box(920, 440, 400, 60, 'Checkout do core_payment',
             sub='o aluno escolhe o gateway', tag='CORE'))
n.append(box(920, 560, 400, 60, 'paygw_* link/pix/card',
             sub='cobranca nasce NA CONTA DO VENDEDOR', tag='PLUGIN'))
n.append(box(920, 680, 400, 60, 'Gateway externo',
             sub='Pix · boleto · cartao', kind='external', tag='HTTP'))
n.append(box(920, 800, 400, 60, 'webhook.php', sub='chega sem sessao', tag='ASSINC'))

n.append(box(500, 920, 420, 76, 'deliver_order()',
             sub='{payments} + _sale + _entitlement', tag='ENTREGA', kind='focal'))
n.append(box(500, 1044, 420, 60, 'enrol_marketplace',
             sub='sync_user() matricula por diferenca', tag='MATRICULA'))
n.append(oval(520, 1168, 380, 48, 'Acesso ao curso liberado'))

# --- arestas -------------------------------------------------------------
a.append(conn([(690, 104), (690, 152)]))
a.append(conn([(690, 208), (690, 292)]))
a.append(conn([p1['esq'], (500, 340), (500, 470), (460, 470)]))
a.append(conn([p1['dir'], (880, 340), (880, 470), (920, 470)]))
a.append(conn([(1120, 500), (1120, 560)]))
a.append(conn([(1120, 620), (1120, 680)]))
a.append(conn([(1120, 740), (1120, 800)]))
# o webhook entra pelo TOPO da entrega; a oferta gratis entra pela ESQUERDA
a.append(conn([(1120, 860), (1120, 896), (710, 896), (710, 920)],
              color=T['accent'], marker='arrow-accent'))
a.append(conn([(310, 500), (310, 958), (500, 958)]))
a.append(conn([(710, 996), (710, 1044)]))
a.append(conn([(710, 1104), (710, 1168)]))

r.append(alabel(516, 400, 'GRATIS', anchor='start'))
r.append(alabel(896, 400, 'PAGA', anchor='start'))
r.append(alabel(910, 882, 'SO NA CONFIRMACAO', color=T['accent']))

corpo = '\n\n      '.join(a + r + n)
corpo += '\n\n      ' + legend(40, 1260, 1320, [
    ('solid', 'CAMINHO NORMAL'),
    ('accent', 'A ENTREGA, E SO UMA'),
    ('swatch-ext', 'FORA DO SITE'),
], cols=430)

write_doc(
    OUT,
    'Flowchart · local_marketplace + paygw_*',
    'Compra do aluno: da vitrine ate a matricula',
    'Fluxo da compra de uma oferta pelo aluno, do checkout ao webhook do '
    'gateway, ate a criacao do direito de acesso e a matricula por diferenca - '
    'com o caminho da oferta gratuita reentrando na mesma entrega.',
    VW, VH, corpo, minw=1000,
    footer='Fonte: public/local/marketplace/ e os tres public/payment/gateway/. A oferta gratuita '
           'nao passa pelo gateway - nao ha o que cobrar - mas passa pela MESMA entrega, para que '
           'direito, matricula e liberacao de topico se comportem igual. A cobranca nasce na conta '
           'do VENDEDOR por regra fiscal, e o split leva so a comissao. O webhook chega sem sessao: '
           'quem descobre quem comprou o que e o externalreference gravado antes da chamada. A '
           'matricula e projecao do direito, nunca uma segunda fonte da verdade.')
