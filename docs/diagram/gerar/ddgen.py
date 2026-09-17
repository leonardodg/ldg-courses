#!/usr/bin/env python3
"""Primitivas do Diagram Design no skin LeoDG.

Gera SVG em vez de escrever a mao: e o que garante que todo campo venha do
schema real, que a grade de 4px feche, e que as seis regras de conector do
SKILL.md valham por construcao em vez de por atencao.
"""
import json
import os
from math import hypot

# Tokens do perfil ~/.diagram-design/profiles/leodg.md, modo dark (canonico).
T = {
    'paper': '#121212',
    'paper2': '#1e1e1e',
    'ink': '#ffffff',
    'muted': '#b0b3b8',
    'soft': '#8a8d91',
    'rule': 'rgba(255,255,255,0.12)',
    'rule_solid': '#3a3b3c',
    'accent': '#3394ff',
    'accent_tint': 'rgba(51,148,255,0.12)',
    'link': '#8fa3bc',
    'row_alt': 'rgba(255,255,255,0.02)',
    'band': 'rgba(255,255,255,0.04)',
    'chip': 'rgba(255,255,255,0.35)',
    'hair': 'rgba(255,255,255,0.22)',
}

SANS = "'Inter', system-ui, sans-serif"
MONO = "'Geist Mono', ui-monospace, monospace"
SERIF = "'Instrument Serif', serif"

FONTS = ("https://fonts.googleapis.com/css2?"
         "family=Instrument+Serif:ital@0;1"
         "&family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400"
         "&family=Geist+Mono:wght@400;500;600&display=swap")

ROW_H = 24
HEAD_H = 28

# O que o diagrama corrente desenhou, para o conferidor comparar com o XMLDB.
MANIFESTO = {'tabelas': {}, 'indices': {}}


def esc(s):
    return (str(s).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))


# --------------------------------------------------------------------------
# Conectores
# --------------------------------------------------------------------------

def rpath(pts, r=8):
    """Polilinha ortogonal com cantos arredondados.

    Recebe waypoints; todo segmento tem de ser horizontal ou vertical - um
    trecho diagonal e falha automatica pela regra 1 do SKILL.md, entao ele
    levanta em vez de desenhar torto.
    """
    pts = [(float(x), float(y)) for x, y in pts]
    for (x1, y1), (x2, y2) in zip(pts, pts[1:]):
        if abs(x1 - x2) > 0.01 and abs(y1 - y2) > 0.01:
            raise ValueError(f'segmento diagonal proibido: {(x1, y1)} -> {(x2, y2)}')
    if len(pts) == 2:
        return f'M {pts[0][0]:g} {pts[0][1]:g} L {pts[1][0]:g} {pts[1][1]:g}'
    d = [f'M {pts[0][0]:g} {pts[0][1]:g}']
    for i in range(1, len(pts) - 1):
        p0, p1, p2 = pts[i - 1], pts[i], pts[i + 1]
        v1 = (p1[0] - p0[0], p1[1] - p0[1])
        v2 = (p2[0] - p1[0], p2[1] - p1[1])
        l1, l2 = hypot(*v1), hypot(*v2)
        if l1 == 0 or l2 == 0:
            continue
        rr = min(r, l1 / 2, l2 / 2)
        a = (p1[0] - v1[0] / l1 * rr, p1[1] - v1[1] / l1 * rr)
        b = (p1[0] + v2[0] / l2 * rr, p1[1] + v2[1] / l2 * rr)
        d.append(f'L {a[0]:g} {a[1]:g}')
        d.append(f'Q {p1[0]:g} {p1[1]:g} {b[0]:g} {b[1]:g}')
    d.append(f'L {pts[-1][0]:g} {pts[-1][1]:g}')
    return ' '.join(d)


def conn(pts, color=None, dashed=False, marker='arrow', width=1, r=8):
    color = color or T['muted']
    dash = ' stroke-dasharray="5,4"' if dashed else ''
    mk = f' marker-end="url(#{marker})"' if marker else ''
    return (f'<path d="{rpath(pts, r)}" fill="none" stroke="{color}" '
            f'stroke-width="{width}"{dash}{mk}/>')


def alabel(x, y, text, color=None, anchor='middle', cw=5.4, pad=8):
    """Rotulo de aresta com mascara opaca. x,y = centro do texto.

    O chamador posiciona; o vao de 6-10px ate o traco e responsabilidade de
    quem chama, porque so ele sabe de que lado a linha passa.
    """
    color = color or T['soft']
    w = int(round((len(text) * cw + pad * 2) / 4.0)) * 4
    if anchor == 'middle':
        mx = x - w / 2
    elif anchor == 'start':
        mx = x - pad
    else:
        mx = x - w + pad
    return (f'<rect x="{mx:g}" y="{y - 8:g}" width="{w}" height="12" rx="2" fill="{T["paper"]}"/>'
            f'<text x="{x:g}" y="{y + 1:g}" fill="{color}" font-size="8" font-family="{MONO}" '
            f'text-anchor="{anchor}" letter-spacing="0.06em">{esc(text)}</text>')


# --------------------------------------------------------------------------
# Caixa de tabela (type-db-schema)
# --------------------------------------------------------------------------

def table(x, y, w, name, rows, tag='TABLE', indexes=None, focal=False,
          note=None, chip_x=None):
    """Caixa de tabela fisica.

    rows: lista de (coluna, [chips], tipo_sql). Devolve (svg, cy) onde cy
    mapeia coluna -> centro vertical da linha, para a FK ancorar na COLUNA.
    """
    indexes = indexes or []
    MANIFESTO['tabelas'][name] = [c for c, _ch, _t in rows if not c.startswith('+')]
    if indexes:
        MANIFESTO['indices'][name] = list(indexes)
    n = len(rows)
    h = HEAD_H + ROW_H * n
    if indexes:
        h += 12 + 16 * len(indexes) + 8
    if note:
        h += 20
    band = T['accent_tint'] if focal else T['band']
    chip_x = chip_x if chip_x is not None else x + w - 116

    s = []
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{T["paper"]}"/>')
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{T["paper2"]}" '
             f'stroke="{T["ink"]}" stroke-width="1"/>')
    # Faixa do cabecalho: rx em cima, canto reto embaixo.
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{HEAD_H}" rx="6" fill="{band}"/>')
    s.append(f'<rect x="{x}" y="{y + 20}" width="{w}" height="8" fill="{band}"/>')
    s.append(f'<line x1="{x}" y1="{y + HEAD_H}" x2="{x + w}" y2="{y + HEAD_H}" '
             f'stroke="{T["hair"]}" stroke-width="1"/>')
    s.append(f'<text x="{x + 12}" y="{y + 18}" fill="{T["ink"]}" font-size="12" font-weight="600" '
             f'font-family="{SANS}">{esc(name)}</text>')
    tw = max(40, len(tag) * 6 + 12)
    s.append(f'<rect x="{x + w - tw - 12}" y="{y + 8}" width="{tw}" height="12" rx="2" fill="none" '
             f'stroke="rgba(255,255,255,0.40)" stroke-width="0.8"/>')
    s.append(f'<text x="{x + w - tw / 2 - 12}" y="{y + 17}" fill="{T["ink"]}" opacity="0.8" '
             f'font-size="8" font-family="{MONO}" text-anchor="middle" '
             f'letter-spacing="0.08em">{esc(tag)}</text>')

    cy = {}
    for i, (col, chips, typ) in enumerate(rows):
        ry = y + HEAD_H + ROW_H * i
        cy[col] = ry + ROW_H / 2
        if i % 2 == 0:
            s.append(f'<rect x="{x}" y="{ry}" width="{w}" height="{ROW_H}" fill="{T["row_alt"]}"/>')
        muted_col = col.startswith('+')
        s.append(f'<text x="{x + 12}" y="{ry + 16}" fill="{T["muted"] if muted_col else T["ink"]}" '
                 f'font-size="{9 if muted_col else 12}" '
                 f'font-family="{MONO if muted_col else SANS}">{esc(col)}</text>')
        cx2 = chip_x
        for c in chips:
            s.append(f'<rect x="{cx2}" y="{ry + 6}" width="20" height="12" rx="2" fill="none" '
                     f'stroke="{T["chip"]}" stroke-width="0.8"/>')
            s.append(f'<text x="{cx2 + 10}" y="{ry + 15}" fill="{T["ink"]}" opacity="0.75" '
                     f'font-size="8" font-family="{MONO}" text-anchor="middle">{esc(c)}</text>')
            cx2 += 24
        if typ:
            s.append(f'<text x="{x + w - 12}" y="{ry + 16}" fill="{T["muted"]}" font-size="9" '
                     f'font-family="{MONO}" text-anchor="end">{esc(typ)}</text>')

    iy = y + HEAD_H + ROW_H * n
    if indexes:
        s.append(f'<line x1="{x}" y1="{iy}" x2="{x + w}" y2="{iy}" stroke="{T["rule"]}" '
                 f'stroke-width="0.8"/>')
        s.append(f'<text x="{x + 12}" y="{iy + 14}" fill="{T["soft"]}" font-size="8" '
                 f'font-family="{MONO}" letter-spacing="0.14em">INDEXES</text>')
        for j, ix in enumerate(indexes):
            s.append(f'<text x="{x + 12}" y="{iy + 30 + 16 * j}" fill="{T["muted"]}" font-size="9" '
                     f'font-family="{MONO}">{esc(ix)}</text>')
        iy += 12 + 16 * len(indexes) + 8
    if note:
        s.append(f'<line x1="{x}" y1="{iy}" x2="{x + w}" y2="{iy}" stroke="{T["rule"]}" '
                 f'stroke-width="0.8"/>')
        s.append(f'<text x="{x + 12}" y="{iy + 14}" fill="{T["soft"]}" font-size="8" '
                 f'font-family="{MONO}">{esc(note)}</text>')

    return '\n      '.join(s), cy, h


# --------------------------------------------------------------------------
# Caixa de entidade (type-er) - lista de campos, sem tipo SQL
# --------------------------------------------------------------------------

# Medido no Chromium com a fonte carregada (scripts/medir.py), nao estimado:
# o Geist Mono e monoespacado de verdade, entao um numero basta.
MONO_ADV_9 = 5.418

_CACHE_SANS = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'larguras-sans.json')
try:
    with open(_CACHE_SANS) as _fh:
        SANS_W = json.load(_fh)
except Exception:
    SANS_W = {}


def largura_mono(texto, tam=9):
    return len(texto) * MONO_ADV_9 * tam / 9.0


def largura_sans(texto):
    """Inter e proporcional: usa a medida real quando existe, senao o pior caso."""
    if texto in SANS_W:
        return SANS_W[texto]
    return len(texto) * 7.6      # limite superior observado, so para nao passar batido


def entity(x, y, w, name, fields, tag='ENTITY', focal=False, external=False):
    """Caixa de entidade. fields: lista de strings ja prefixadas (# PK, > FK)."""
    util = w - 24
    for f in fields:
        if largura_mono(f) > util:
            raise ValueError(
                f'{name}: a linha {f!r} precisa de {largura_mono(f):.0f}px e so ha {util}px. '
                f'Corte o texto ou alargue a caixa.')
    # O nome vai em sans 12px; a tag do canto direito come o resto da largura.
    disp = w - 24 - max(36, len(tag) * 6 + 12) - 8
    if largura_sans(name) > disp:
        raise ValueError(
            f'o nome {name!r} precisa de {largura_sans(name):.0f}px e so ha {disp}px em {w}px '
            f'com a tag {tag!r}')
    lh = 14
    h = HEAD_H + 8 + lh * len(fields) + 8
    band = T['accent_tint'] if focal else T['band']
    stroke = T['ink'] if not external else 'rgba(255,255,255,0.30)'
    fill = T['paper2'] if not external else 'rgba(255,255,255,0.03)'
    s = []
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{T["paper"]}"/>')
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{fill}" '
             f'stroke="{stroke}" stroke-width="1"/>')
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{HEAD_H}" rx="6" fill="{band}"/>')
    s.append(f'<rect x="{x}" y="{y + 20}" width="{w}" height="8" fill="{band}"/>')
    s.append(f'<line x1="{x}" y1="{y + HEAD_H}" x2="{x + w}" y2="{y + HEAD_H}" '
             f'stroke="{T["hair"]}" stroke-width="1"/>')
    s.append(f'<text x="{x + 12}" y="{y + 18}" fill="{T["ink"] if not external else T["muted"]}" '
             f'font-size="12" font-weight="600" font-family="{SANS}">{esc(name)}</text>')
    tw = max(36, len(tag) * 6 + 12)
    s.append(f'<rect x="{x + w - tw - 12}" y="{y + 8}" width="{tw}" height="12" rx="2" fill="none" '
             f'stroke="rgba(255,255,255,0.40)" stroke-width="0.8"/>')
    s.append(f'<text x="{x + w - tw / 2 - 12}" y="{y + 17}" fill="{T["ink"]}" opacity="0.8" '
             f'font-size="8" font-family="{MONO}" text-anchor="middle" '
             f'letter-spacing="0.08em">{esc(tag)}</text>')
    for i, f in enumerate(fields):
        fy = y + HEAD_H + 8 + lh * i + 10
        col = T['muted'] if f.startswith('+') else T['ink']
        op = '0.75' if f.startswith('+') else '1'
        s.append(f'<text x="{x + 12}" y="{fy}" fill="{col}" opacity="{op}" font-size="9" '
                 f'font-family="{MONO}">{esc(f)}</text>')
    return '\n      '.join(s), h


# --------------------------------------------------------------------------
# Caixa simples (arquitetura / fluxo)
# --------------------------------------------------------------------------

def box(x, y, w, h, name, sub=None, tag=None, kind='backend'):
    fills = {
        'focal': (T['accent_tint'], T['accent'], None),
        'backend': (T['paper2'], T['ink'], None),
        'store': ('rgba(255,255,255,0.05)', T['muted'], None),
        'external': ('rgba(255,255,255,0.03)', 'rgba(255,255,255,0.30)', None),
        'input': ('rgba(176,179,184,0.10)', T['soft'], None),
        'optional': ('rgba(255,255,255,0.02)', 'rgba(255,255,255,0.20)', '4,3'),
        'security': ('rgba(51,148,255,0.05)', 'rgba(51,148,255,0.50)', '4,4'),
    }
    fill, stroke, dash = fills[kind]
    da = f' stroke-dasharray="{dash}"' if dash else ''
    s = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{T["paper"]}"/>',
         f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{fill}" '
         f'stroke="{stroke}" stroke-width="1"{da}/>']
    cx = x + w / 2
    ty = y + h / 2 + (2 if not sub else -4)
    if tag:
        tw = max(32, len(tag) * 6 + 12)
        s.append(f'<rect x="{x + 8}" y="{y + 6}" width="{tw}" height="12" rx="2" fill="none" '
                 f'stroke="rgba(255,255,255,0.40)" stroke-width="0.8"/>')
        s.append(f'<text x="{x + 8 + tw / 2}" y="{y + 15}" fill="{T["ink"]}" opacity="0.8" '
                 f'font-size="8" font-family="{MONO}" text-anchor="middle" '
                 f'letter-spacing="0.08em">{esc(tag)}</text>')
        ty = y + h / 2 + (8 if not sub else 2)
    s.append(f'<text x="{cx:g}" y="{ty:g}" fill="{T["ink"]}" font-size="12" font-weight="600" '
             f'font-family="{SANS}" text-anchor="middle">{esc(name)}</text>')
    if sub:
        s.append(f'<text x="{cx:g}" y="{ty + 14:g}" fill="{T["muted"]}" font-size="9" '
                 f'font-family="{MONO}" text-anchor="middle">{esc(sub)}</text>')
    return '\n      '.join(s)


def oval(x, y, w, h, name, kind='input'):
    """Inicio / fim de fluxo. A forma carrega o tipo, nunca a cor."""
    fills = {'input': ('rgba(176,179,184,0.10)', T['soft']),
             'focal': (T['accent_tint'], T['accent']),
             'backend': (T['paper2'], T['ink'])}
    fill, stroke = fills[kind]
    return ('\n      '.join([
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="20" fill="{T["paper"]}"/>',
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="20" fill="{fill}" '
        f'stroke="{stroke}" stroke-width="1"/>',
        f'<text x="{x + w / 2:g}" y="{y + h / 2 + 4:g}" fill="{T["ink"]}" font-size="12" '
        f'font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(name)}</text>',
    ]))


def diamond(cxp, cyp, w, h, name, sub=None, focal=False):
    """Decisao. Devolve (svg, pontos) com as quatro pontas para ancorar aresta."""
    fill = T['accent_tint'] if focal else T['paper2']
    stroke = T['accent'] if focal else T['ink']
    pts = f'{cxp},{cyp - h / 2} {cxp + w / 2},{cyp} {cxp},{cyp + h / 2} {cxp - w / 2},{cyp}'
    s = [f'<polygon points="{pts}" fill="{T["paper"]}"/>',
         f'<polygon points="{pts}" fill="{fill}" stroke="{stroke}" stroke-width="1"/>',
         f'<text x="{cxp:g}" y="{cyp + (0 if not sub else -4):g}" fill="{T["ink"]}" font-size="12" '
         f'font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(name)}</text>']
    if sub:
        s.append(f'<text x="{cxp:g}" y="{cyp + 12:g}" fill="{T["muted"]}" font-size="9" '
                 f'font-family="{MONO}" text-anchor="middle">{esc(sub)}</text>')
    pontos = {'topo': (cxp, cyp - h / 2), 'baixo': (cxp, cyp + h / 2),
              'esq': (cxp - w / 2, cyp), 'dir': (cxp + w / 2, cyp)}
    return '\n      '.join(s), pontos


def merge(x, y):
    """Ponto de reencontro de ramos."""
    return f'<circle cx="{x}" cy="{y}" r="4" fill="{T["ink"]}"/>'


def zone(x, y, w, h, label, color=None):
    color = color or 'rgba(255,255,255,0.20)'
    return ('\n      '.join([
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" fill="rgba(255,255,255,0.02)" '
        f'stroke="{color}" stroke-width="1" stroke-dasharray="4,4"/>',
        f'<text x="{x + 12}" y="{y + 16}" fill="{T["soft"]}" font-size="8" font-family="{MONO}" '
        f'letter-spacing="0.14em">{esc(label.upper())}</text>',
    ]))


def legend(x, y, w, items, cols=None):
    """Faixa horizontal no rodape. items: lista de (tipo, texto)."""
    s = [f'<line x1="{x}" y1="{y}" x2="{x + w}" y2="{y}" stroke="{T["rule"]}" stroke-width="0.8"/>',
         f'<text x="{x}" y="{y + 20}" fill="{T["soft"]}" font-size="8" font-family="{MONO}" '
         f'letter-spacing="0.14em">LEGEND</text>']
    step = cols or 240
    cx = x + 72
    for kind, text in items:
        iy = y + 16
        if kind == 'solid':
            s.append(f'<line x1="{cx}" y1="{iy}" x2="{cx + 20}" y2="{iy}" stroke="{T["muted"]}" '
                     f'stroke-width="1" marker-end="url(#arrow)"/>')
        elif kind == 'dashed':
            s.append(f'<line x1="{cx}" y1="{iy}" x2="{cx + 20}" y2="{iy}" stroke="{T["muted"]}" '
                     f'stroke-width="1" stroke-dasharray="5,4" marker-end="url(#arrow)"/>')
        elif kind == 'accent':
            s.append(f'<line x1="{cx}" y1="{iy}" x2="{cx + 20}" y2="{iy}" stroke="{T["accent"]}" '
                     f'stroke-width="1" marker-end="url(#arrow-accent)"/>')
        elif kind == 'link':
            s.append(f'<line x1="{cx}" y1="{iy}" x2="{cx + 20}" y2="{iy}" stroke="{T["link"]}" '
                     f'stroke-width="1" marker-end="url(#arrow-link)"/>')
        elif kind == 'swatch-focal':
            s.append(f'<rect x="{cx}" y="{iy - 6}" width="20" height="12" rx="2" '
                     f'fill="{T["accent_tint"]}" stroke="{T["accent"]}" stroke-width="1"/>')
        elif kind == 'swatch-ext':
            s.append(f'<rect x="{cx}" y="{iy - 6}" width="20" height="12" rx="2" '
                     f'fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.30)" stroke-width="1"/>')
        elif kind == 'swatch-box':
            s.append(f'<rect x="{cx}" y="{iy - 6}" width="20" height="12" rx="2" '
                     f'fill="{T["paper2"]}" stroke="{T["ink"]}" stroke-width="1"/>')
        s.append(f'<text x="{cx + 28}" y="{iy + 4}" fill="{T["muted"]}" font-size="8" '
                 f'font-family="{MONO}" letter-spacing="0.06em">{esc(text)}</text>')
        cx += step
    return '\n      '.join(s)


# --------------------------------------------------------------------------
# Documento
# --------------------------------------------------------------------------

def write_doc(out, eyebrow, title, desc, vw, vh, body, minw=900, footer=None):
    """Escreve o arquivo derivando o slug do nome dele.

    O slug tem de bater com o stem do arquivo: dois diagramas na mesma pagina
    com IDs iguais fariam o segundo ser anunciado com o nome do primeiro.
    """
    import os
    slug = os.path.splitext(os.path.basename(out))[0]
    os.makedirs(os.path.dirname(out), exist_ok=True)
    html = doc(slug, eyebrow, title, desc, vw, vh, body, minw=minw, footer=footer)
    with open(out, 'w') as fh:
        fh.write(html)
    # So os diagramas coluna a coluna tem o que declarar ao conferidor.
    manifesto = os.path.splitext(out)[0] + '.manifesto.json'
    if MANIFESTO['tabelas']:
        with open(manifesto, 'w') as fh:
            json.dump(MANIFESTO, fh, indent=1, ensure_ascii=False, sort_keys=True)
    elif os.path.exists(manifesto):
        os.remove(manifesto)
    print(f'escrito: {out}  ({vw}x{vh})')
    return out


def doc(slug, eyebrow, title, desc, vw, vh, body, minw=900, footer=None):
    foot = ''
    if footer:
        foot = (f'\n    <p class="colophon">{esc(footer)}</p>')
    return f'''<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{esc(title)}</title>
  <link href="{FONTS}" rel="stylesheet">
  <style>
    *, *::before, *::after {{ box-sizing: border-box; margin: 0; padding: 0; }}
    :root {{
      --color-paper:  {T['paper']};
      --color-paper2: {T['paper2']};
      --color-ink:    {T['ink']};
      --color-muted:  {T['muted']};
      --color-accent: {T['accent']};
      --color-rule:   {T['rule']};
      --font-sans:  {SANS};
      --font-serif: {SERIF};
      --font-mono:  {MONO};
    }}
    body {{
      font-family: var(--font-sans);
      background: var(--color-paper);
      color: var(--color-ink);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 2rem;
    }}
    .frame {{ max-width: {max(1200, vw)}px; width: 100%; }}
    .eyebrow {{
      font-family: var(--font-mono);
      font-size: 0.66rem;
      font-weight: 500;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--color-muted);
      margin-bottom: 0.5rem;
    }}
    h1 {{
      font-family: var(--font-serif);
      font-size: clamp(1.5rem, 2.4vw + 0.75rem, 2rem);
      font-weight: 400;
      letter-spacing: -0.02em;
      line-height: 1.15;
      color: var(--color-ink);
      margin-bottom: 1.5rem;
    }}
    .colophon {{
      font-family: var(--font-mono);
      font-size: 0.62rem;
      line-height: 1.6;
      color: var(--color-muted);
      border-top: 1px solid var(--color-rule);
      margin-top: 1.5rem;
      padding-top: 0.75rem;
    }}
    svg {{ width: 100%; min-width: {minw}px; display: block; }}
  </style>
</head>
<body>
  <div class="frame">
    <p class="eyebrow">{esc(eyebrow)}</p>
    <h1>{esc(title)}</h1>

    <svg viewBox="0 0 {vw} {vh}" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="{slug}-title {slug}-desc">
      <title id="{slug}-title">{esc(title)}</title>
      <desc id="{slug}-desc">{esc(desc)}</desc>
      <defs>
        <marker id="arrow" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{T['muted']}"/></marker>
        <marker id="arrow-accent" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{T['accent']}"/></marker>
        <marker id="arrow-link" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{T['link']}"/></marker>
        <marker id="arrow-soft" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{T['soft']}"/></marker>
      </defs>

      <rect width="100%" height="100%" fill="{T['paper']}"/>

      {body}
    </svg>{foot}
  </div>
</body>
</html>
'''
