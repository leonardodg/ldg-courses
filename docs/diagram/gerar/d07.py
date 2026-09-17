#!/usr/bin/env python3
"""07-fluxo-parceiro - da landing publica ate a empresa provisionada."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, box, oval, diamond, conn, alabel, legend, zone

VW, VH = 1360, 1160
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '07-fluxo-parceiro.html')
a, n, r = [], [], []          # arestas, nos, rotulos

# ---- coluna central, fluxo de cima para baixo ---------------------------
n.append(oval(520, 56, 320, 48, 'Visitante na landing'))
n.append(box(520, 152, 320, 56, 'index.php', sub='publica, sem require_login',
             tag='LANDING'))
n.append(box(520, 256, 320, 56, 'apply.php', sub='3 camadas anti-spam', tag='FORM'))

d1, p1 = diamond(680, 400, 280, 96, 'Ja tem conta?', sub='userid preenchido?')
n.append(d1)

# ramo NAO: confirma o e-mail
n.append(box(160, 464, 300, 60, 'E-mail com confirmtoken',
             sub='status = unconfirmed', tag='NAO'))
n.append(box(160, 584, 300, 56, 'confirm.php', sub='unconfirmed -> pending', tag='TOKEN'))

# ---- fila do administrador ---------------------------------------------
zonas = [zone(40, 688, 1280, 180, 'area do administrador do site')]
n.append(box(160, 736, 300, 60, 'admin/applications.php',
             sub='fila por status e chegada', tag='FILA'))
n.append(box(540, 736, 300, 60, 'admin/application_view.php',
             sub='escolhe o shortname aqui', tag='DECIDE'))

d2, p2 = diamond(1160, 766, 260, 96, 'Aprovar?', focal=True)
n.append(d2)

n.append(oval(520, 980, 360, 48, 'Empresa provisionada', kind='focal'))
n.append(oval(1030, 560, 260, 48, 'Candidatura recusada'))

# ---- arestas ------------------------------------------------------------
a.append(conn([(680, 104), (680, 152)]))
a.append(conn([(680, 208), (680, 256)]))
a.append(conn([(680, 312), (680, 352)]))
# NAO -> e-mail de confirmacao
a.append(conn([p1['esq'], (500, 400), (500, 494), (460, 494)]))
a.append(conn([(310, 524), (310, 584)]))
a.append(conn([(310, 640), (310, 736)]))
# SIM -> entra direto na fila (visitante autenticado nao confirma o que o site ja confirmou)
a.append(conn([p1['dir'], (900, 400), (900, 660), (690, 660), (690, 736)]))
# fila -> ficha -> decisao
a.append(conn([(460, 766), (540, 766)]))
a.append(conn([(840, 766), (1030, 766)]))
# aprovado desce para a empresa; recusado volta com o motivo
a.append(conn([p2['baixo'], (1160, 1004), (880, 1004)],
              color=T['accent'], marker='arrow-accent'))
a.append(conn([p2['topo'], (1160, 608)], dashed=True))

r.append(alabel(516, 450, 'NAO', anchor='start'))
r.append(alabel(916, 450, 'SIM', anchor='start'))
r.append(alabel(1020, 990, 'APROVA', color=T['accent']))
r.append(alabel(1176, 663, 'RECUSA + REVIEWNOTE', anchor='start'))

corpo = '\n\n      '.join(zonas + a + r + n)
corpo += '\n\n      ' + legend(40, 1080, 1280, [
    ('solid', 'CAMINHO NORMAL'),
    ('accent', 'O PORTAO: CRIA CATEGORIA'),
    ('dashed', 'RECUSA'),
], cols=420)

write_doc(
    OUT,
    'Flowchart · local_partners',
    'Cadastro de parceiro: da landing a empresa provisionada',
    'Fluxo da candidatura de empresa parceira, da landing publica ate a '
    'aprovacao pelo administrador, incluindo a confirmacao de e-mail exigida '
    'apenas de quem ainda nao tem conta no site.',
    VW, VH, corpo, minw=1000,
    footer='Fonte: public/local/partners/. Nao ha auto-atendimento, e o motivo esta no losango '
           'azul: aprovar cria uma CATEGORIA DE CURSO, que e objeto global do site. Quem ja tem '
           'conta pula a confirmacao - o site ja confirmou aquele e-mail. Uma candidatura decidida '
           'nunca volta para pending: reenvio e linha nova, para o historico de quem tentou nao '
           'sumir. A aprovacao e idempotente pelo companyid: preenchido, a segunda aprovacao nao '
           'cria uma segunda categoria.')
