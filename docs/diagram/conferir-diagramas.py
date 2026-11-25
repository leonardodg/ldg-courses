#!/usr/bin/env python3
"""Confere os diagramas de banco contra o schema real dos install.xml.

Existe porque diagrama nao reprova teste nenhum: ele envelhece em silencio
enquanto o schema anda. Rode depois de mexer em qualquer db/install.xml.

    python3 docs/diagram/conferir-diagramas.py

Reprova quando um diagrama cita coluna, indice ou tabela que nao existe, e
avisa quando uma FK declarada no XMLDB nao aparece em diagrama nenhum.
"""
import glob
import json
import os
import re
import sys
import xml.etree.ElementTree as ET

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.abspath(os.path.join(AQUI, '..', '..'))

# Os plugins do LeoDG que tem tabela propria. O core entra so para validar as
# tabelas dele que os diagramas desenham - as FKs do core NAO sao cobradas:
# sao 300 e poucas, e nenhuma delas e responsabilidade deste repositorio.
XMLS_NOSSOS = [
    'public/local/marketplace/db/install.xml',
    'public/payment/gateway/mercadopago/db/install.xml',
    'public/payment/gateway/asaas/db/install.xml',
    'public/payment/gateway/pagarme/db/install.xml',
    'public/local/partners/db/install.xml',
    'public/course/format/ldg/db/install.xml',
    'public/mod/ldgvideo/db/install.xml',
]
XMLS_CORE = ['public/lib/db/install.xml']


def ler_schema():
    """Devolve (colunas, indices, fks, nossas) a partir dos install.xml.

    fks so traz as chaves declaradas pelos NOSSOS plugins.
    """
    colunas, indices, fks, nossas = {}, {}, [], set()
    for rel in XMLS_NOSSOS + XMLS_CORE:
        nosso = rel in XMLS_NOSSOS
        caminho = os.path.join(RAIZ, rel)
        if not os.path.exists(caminho):
            print(f'AVISO: {rel} nao existe', file=sys.stderr)
            continue
        for tab in ET.parse(caminho).getroot().iter('TABLE'):
            nome = tab.get('NAME')
            if nosso:
                nossas.add(nome)
            colunas[nome] = {c.get('NAME') for c in tab.find('FIELDS').iter('FIELD')}
            bloco = tab.find('INDEXES')
            indices[nome] = ({x.get('NAME') for x in bloco.iter('INDEX')}
                             if bloco is not None else set())
            bloco = tab.find('KEYS')
            if bloco is not None:
                for k in bloco.iter('KEY'):
                    if k.get('TYPE') in ('foreign', 'foreign-unique'):
                        indices[nome].add(k.get('NAME'))
                        if nosso:
                            fks.append((nome, (k.get('FIELDS') or '').split(',')[0].strip(),
                                        k.get('REFTABLE')))
    return colunas, indices, fks, nossas


def main():
    colunas, indices, fks, nossas = ler_schema()
    if not colunas:
        print('FALHA: nenhum install.xml lido', file=sys.stderr)
        return 1

    erros, avisos = [], []
    vistas = set()

    manifestos = sorted(glob.glob(os.path.join(AQUI, '*.manifesto.json')))
    if not manifestos:
        print('FALHA: nenhum *.manifesto.json - os diagramas foram gerados?', file=sys.stderr)
        return 1

    for m in manifestos:
        diagrama = os.path.basename(m).replace('.manifesto.json', '')
        dados = json.load(open(m))

        for tabela, cols in dados.get('tabelas', {}).items():
            if tabela not in colunas:
                erros.append(f'{diagrama}: a tabela {tabela} nao existe no schema')
                continue
            vistas.add(tabela)
            for c in cols:
                if c not in colunas[tabela]:
                    erros.append(f'{diagrama}: {tabela}.{c} nao existe no schema')

        for tabela, ixs in dados.get('indices', {}).items():
            if tabela not in indices:
                continue
            for ix in ixs:
                # O diagrama escreve "nome (unique)"; o XMLDB guarda so o nome.
                nome = re.sub(r'\s*\(unique\)$', '', ix).strip()
                if nome not in indices[tabela]:
                    erros.append(f'{diagrama}: o indice {tabela}.{nome} nao existe no schema')

    # Toda tabela nossa deveria aparecer em algum zoom coluna a coluna.
    for t in sorted(nossas - vistas):
        avisos.append(f'a tabela {t} nao aparece em zoom nenhum')
    # E toda FK que NOS declaramos deveria estar desenhada em algum deles.
    for origem, campo, destino in fks:
        if origem not in vistas:
            avisos.append(f'a FK {origem}.{campo} -> {destino} nao aparece em zoom nenhum')

    print(f'{len(manifestos)} diagramas conferidos: {len(nossas)} tabelas nossas, '
          f'{len(fks)} FKs declaradas, {len(vistas)} tabelas desenhadas coluna a coluna.')
    for a in sorted(set(avisos)):
        print(f'  aviso: {a}')
    for e in erros:
        print(f'  ERRO:  {e}')

    if erros:
        print(f'\nFALHA: {len(erros)} referencia(s) inexistente(s).')
        return 1
    print('\nOK: todo campo e indice desenhado existe no schema.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
