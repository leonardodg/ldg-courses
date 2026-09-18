#!/usr/bin/env node
/**
 * Gera link #docz= para m3e-canvas a partir do JSON de design
 * Uso: node generate-docz-link.js <caminho-do-json>
 */

import { readFileSync } from 'fs';
import { resolve } from 'path';

function base64urlEncode(str) {
  return Buffer.from(str)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '');
}

function generateDoczLink(jsonPath) {
  const absolutePath = resolve(jsonPath);
  const content = readFileSync(absolutePath, 'utf-8');
  const json = JSON.parse(content);
  const encoded = base64urlEncode(JSON.stringify(json));
  const url = `https://lnkiai.github.io/m3e-canvas/#docz=${encoded}`;
  return url;
}

const jsonPath = process.argv[2];
if (!jsonPath) {
  console.error('Uso: node generate-docz-link.js <caminho-do-json>');
  process.exit(1);
}

try {
  const link = generateDoczLink(jsonPath);
  console.log('\n🔗 Link m3e-canvas (copie e cole no navegador):\n');
  console.log(link);
  console.log('\n📋 Tamanho do payload:', Math.round(Buffer.from(link).length / 1024), 'KB');
  console.log('\n✅ Abra no Chrome para validar as 11 telas.');
} catch (err) {
  console.error('❌ Erro:', err.message);
  process.exit(1);
}