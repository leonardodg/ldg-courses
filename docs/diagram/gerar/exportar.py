#!/usr/bin/env python3
"""Exporta .svg e .png de um diagrama, conforme references/export.md."""
import pathlib
import re
import sys

FONTS_XML = ("https://fonts.googleapis.com/css2?"
             "family=Instrument+Serif:ital@0;1"
             "&amp;family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400"
             "&amp;family=Geist+Mono:wght@400;500;600&amp;display=swap")


def exportar_svg(src, out):
    html = pathlib.Path(src).read_text()
    m = re.search(r'<svg\b.*?</svg>', html, re.S)
    if not m:
        raise SystemExit(f'sem <svg> em {src}')
    svg = m.group(0)

    if 'xmlns=' not in svg.split('>', 1)[0]:
        svg = svg.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"', 1)
    if 'viewBox' not in svg.split('>', 1)[0]:
        print(f'AVISO: {src} sem viewBox', file=sys.stderr)

    # Fontes: entra DENTRO do <defs> existente, nunca um segundo <defs>.
    style = f"<style>@import url('{FONTS_XML}');</style>"
    if '<defs>' in svg:
        svg = svg.replace('<defs>', '<defs>\n        ' + style, 1)
    else:
        svg = re.sub(r'(<desc\b[^>]*>.*?</desc>)', r'\1\n      <defs>' + style + '</defs>',
                     svg, count=1, flags=re.S)

    # rgba() vira hex + -opacity: o importador do PowerPoint pinta rgba de preto solido.
    svg = re.sub(
        r'(fill|stroke)="rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d*\.?\d+)\s*\)"',
        lambda m: '{0}="#{1:02x}{2:02x}{3:02x}" {0}-opacity="{4}"'.format(
            m.group(1), int(m.group(2)), int(m.group(3)), int(m.group(4)), m.group(5)),
        svg)
    svg = re.sub(r'(fill|stroke)="transparent"', r'\1="none"', svg)

    pathlib.Path(out).write_text('<?xml version="1.0" encoding="UTF-8"?>\n' + svg)
    return out


def exportar_png(src, out, scale=2):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(device_scale_factor=scale)
        page.goto(f'file://{pathlib.Path(src).resolve()}')
        page.wait_for_load_state('networkidle')
        page.locator('svg').first.screenshot(path=out, omit_background=True)
        browser.close()
    return out


if __name__ == '__main__':
    for src in sys.argv[1:]:
        base = pathlib.Path(src).with_suffix('')
        s = exportar_svg(src, f'{base}.svg')
        p_ = exportar_png(src, f'{base}.png')
        import os
        print(f'  {pathlib.Path(s).name}  {os.path.getsize(s) // 1024}kb   '
              f'{pathlib.Path(p_).name}  {os.path.getsize(p_) // 1024}kb')
