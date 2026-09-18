#!/usr/bin/env python3
"""10-fluxo-ativacao-empresa - da empresa provisionada ao checklist completo.

Continua o 07-fluxo-parceiro: aquele termina em "Empresa provisionada"
(api::create_company()), este comeca ali e mostra o que o block_marketplace
faz depois - o checklist derivado de onboarding::step_state()/progress(),
implementado em 18/09/2026.
"""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, box, oval, diamond, conn, alabel, legend

VW, VH = 1200, 620
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '10-fluxo-ativacao-empresa.html')
a, n, r = [], [], []          # arestas, nos, rotulos

# ---- topo: continuacao do 07 --------------------------------------------
n.append(oval(440, 40, 320, 56, 'Empresa provisionada'))

# ---- etapas do checklist, em paralelo ------------------------------------
n.append(box(40, 160, 320, 64, 'Conta de pagamento', sub='onboarding::STEP_GATEWAY', tag='ETAPA'))
n.append(box(440, 160, 320, 64, 'Plano selecionado', sub='onboarding::STEP_PLAN', tag='ETAPA'))
n.append(box(840, 160, 320, 64, 'Documento CNPJ/CPF', sub='opcional - ADR-0010', tag='OPCIONAL',
             kind='optional'))

d1, p1 = diamond(600, 320, 280, 112, 'Etapas obrigatorias\nconcluidas?', sub='gateway + plano')
n.append(d1)

# ---- desfecho -------------------------------------------------------------
n.append(box(80, 460, 340, 64, 'block_marketplace mostra o checklist',
             sub='% de progresso, dono ve o que falta', tag='BLOCO'))
n.append(oval(760, 460, 320, 64, 'Empresa ativa', kind='focal'))

# ---- arestas ---------------------------------------------------------------
# saida da empresa provisionada, tres pontos distintos na mesma aresta
a.append(conn([(520, 96), (520, 130), (200, 130), (200, 160)]))
a.append(conn([(600, 96), (600, 160)]))
a.append(conn([(680, 96), (680, 130), (1000, 130), (1000, 160)], dashed=True))

# etapas ate a decisao
a.append(conn([(200, 224), (200, 320), p1['esq']]))
a.append(conn([(600, 224), p1['topo']]))

# decisao -> desfecho
a.append(conn([p1['baixo'], (600, 420), (250, 420), (250, 460)]))
a.append(conn([p1['dir'], (920, 320), (920, 460)], color=T['accent'], marker='arrow-accent'))

r.append(alabel(250, 405, 'NAO'))
r.append(alabel(870, 300, 'SIM', color=T['accent'], anchor='start'))

corpo = '\n\n      '.join(a + r + n)
corpo += '\n\n      ' + legend(40, 552, 1120, [
    ('solid', 'ETAPA OBRIGATORIA'),
    ('dashed', 'OPCIONAL, NAO BLOQUEIA'),
    ('accent', 'CHECKLIST COMPLETO'),
], cols=380)

write_doc(
    OUT,
    'Flowchart · block_marketplace',
    'Ativacao da empresa: do provisionamento ao checklist completo',
    'Fluxo do checklist de ativacao no block_marketplace, derivado do estado '
    'que ja existe em local_marketplace - conta de pagamento e plano, sem '
    'tabela nova. Continuacao do 07-fluxo-parceiro.',
    VW, VH, corpo, minw=1000,
    footer='Fonte: public/blocks/marketplace/classes/onboarding.php (implementado 18/09/2026). '
           'O documento (CNPJ/CPF) e etapa informativa, nunca bloqueante - company::cnpj aceita '
           'nulo (ADR-0010). Nao ha estado "empresa em cadastro": ela nasce ja ativa na aprovacao, '
           'e o que falta e so a conta de pagamento com gateway habilitado '
           '(core_payment\\account::is_available()) e o plano (company::get_plan()). Enquanto '
           'incompleta, o bloco mostra o checklist no lugar do widget de assinatura do aluno; '
           'completa, ele fica vazio como antes.')
