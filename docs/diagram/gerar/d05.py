#!/usr/bin/env python3
"""05-er-conteudo - aula em video, duracao, candidatura e politica de curso."""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from ddgen import T, write_doc, table, box, conn, alabel, legend

VW, VH = 1440, 860
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '05-er-conteudo.html')
p = []

vid_svg, vid_cy, _ = table(
    40, 56, 300, 'ldgvideo',
    [('id', ['PK'], 'int(10)'),
     ('course', ['NN'], 'int(10)'),
     ('name', ['NN'], 'char(1333)'),
     ('videourl', ['NN'], 'char(1333)'),
     ('aspectratio', ['NN'], 'char(8)'),
     ('+ 4 outras colunas', [], '')],
    indexes=['course'], note='guarda o endereco, nunca o arquivo', chip_x=40 + 120)

cm_svg, cm_cy, _ = table(
    440, 56, 320, 'course_modules',
    [('id', ['PK'], 'int(10)'),
     ('course', ['NN'], 'int(10)'),
     ('module', ['NN'], 'int(10)'),
     ('instance', ['NN'], 'int(10)'),
     ('availability', [], 'text')],
    tag='CORE', note='+ muitas outras colunas do core', chip_x=440 + 140)

les_svg, les_cy, _ = table(
    440, 320, 320, 'format_ldg_lesson',
    [('id', ['PK'], 'int(10)'),
     ('cmid', ['UQ', 'NN'], 'int(10)'),
     ('duration', [], 'int(10)'),
     ('timecreated', ['NN'], 'int(10)'),
     ('timemodified', ['NN'], 'int(10)')],
    indexes=['cmid (unique)'],
    note='sem campo de usuario: null_provider', chip_x=440 + 140)

app_svg, app_cy, _ = table(
    1060, 56, 340, 'local_partners_application',
    [('id', ['PK'], 'int(10)'),
     ('companyname', ['NN'], 'char(255)'),
     ('contactemail', ['NN'], 'char(255)'),
     ('status', ['NN'], 'char(20)'),
     ('planid', [], 'int(10)'),
     ('companyid', [], 'int(10)'),
     ('userid', [], 'int(10)'),
     ('+ 17 outras colunas', [], '')],
    indexes=['status-timecreated', 'confirmtoken'], chip_x=1060 + 168)

lmc_svg, lmc_cy, _ = table(
    880, 560, 340, 'local_marketplace_course',
    [('id', ['PK'], 'int(10)'),
     ('courseid', ['FK', 'UQ', 'NN'], 'int(10)'),
     ('companyid', ['FK', 'NN'], 'int(10)'),
     ('hostingtype', ['NN'], 'char(20)'),
     ('commissionpct', ['NN'], 'number(5,2)'),
     ('+ 4 outras colunas', [], '')], chip_x=880 + 168)

# --- arestas -------------------------------------------------------------
# format_ldg_lesson.cmid -> course_modules.id: sem FK de proposito
p.append(conn([(440, les_cy['cmid']), (400, les_cy['cmid']),
               (400, cm_cy['id'] - 8), (440, cm_cy['id'] - 8)], dashed=True))
# course_modules.instance -> ldgvideo.id
p.append(conn([(440, cm_cy['instance']), (380, cm_cy['instance']),
               (380, vid_cy['id']), (340, vid_cy['id'])], dashed=True))
# course_modules.availability (JSON) -> a oferta: e o availability_marketplace
p.append(conn([(760, cm_cy['availability']), (850, cm_cy['availability'])],
              color=T['accent'], marker='arrow-accent'))
# candidatura -> plano e -> empresa, os dois sem FK declarada
p.append(conn([(1060, app_cy['planid']), (1040, app_cy['planid']),
               (1040, 420), (1060, 420)], dashed=True))
p.append(conn([(1060, app_cy['companyid']), (1024, app_cy['companyid']),
               (1024, 500), (1060, 500)], dashed=True))
# local_marketplace_course.courseid -> {course}
p.append(conn([(880, lmc_cy['courseid']), (720, lmc_cy['courseid'])]))

p.append(alabel(805, cm_cy['availability'] - 14, 'JSON', color=T['accent']))

for t in (vid_svg, cm_svg, les_svg, app_svg, lmc_svg):
    p.append(t)

p.append(box(850, cm_cy['availability'] - 20, 160, 40, '{..._offer}', sub='marketplace',
             kind='external'))
p.append(box(1060, 400, 220, 40, '{..._plan}', sub='marketplace', kind='external'))
p.append(box(1060, 480, 220, 40, '{..._company}', sub='marketplace', kind='external'))
p.append(box(560, lmc_cy['courseid'] - 20, 160, 40, '{course}', sub='core', kind='external'))

p.append(legend(40, 768, 1360, [
    ('solid', 'FK DECLARADA NO INSTALL.XML'),
    ('dashed', 'VINCULO SEM FK, POR DECISAO'),
    ('accent', 'REFERENCIA DENTRO DE UM JSON'),
], cols=440))

write_doc(
    OUT,
    'Database schema · plugins LeoDG',
    'Conteudo e candidatura: os vinculos que nao viraram FK',
    'Esquema fisico da aula em video, da duracao por modulo do curso, da '
    'candidatura de empresa parceira e da politica de hospedagem por curso - o '
    'grupo em que quase toda ligacao real foi deixada sem chave estrangeira, e '
    'cada omissao tem motivo registrado no proprio XMLDB.',
    VW, VH, '\n\n      '.join(p), minw=1100,
    footer='Fontes: mod/ldgvideo, course/format/ldg, local/partners e local/marketplace. As linhas '
           'tracejadas nao sao descuido. format_ldg_lesson.cmid nao tem FK porque a atividade pode '
           'ser apagada e a linha orfa nao pode impedir a troca de formato; '
           'local_partners_application.planid nao tem porque apontaria para tabela de outro plugin '
           'e o check_database_schema reprovaria - a integridade vem do validate_planid do '
           'persistent. A azul e a mais escondida: o availability_marketplace guarda '
           '{"type":"marketplace","offerid":N} dentro da coluna de texto availability, entao a '
           'referencia a oferta nao existe para o banco.')
