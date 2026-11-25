# Atualização de segurança

Como aplicar uma correção de segurança do Moodle, do PHP ou do Node, e como saber que ela chegou.

## Fontes

Consulte a fonte oficial antes de agir: [avisos do Moodle](https://moodle.org/security/index.php),
[ChangeLog do PHP](https://www.php.net/ChangeLog-8.php),
[catálogo KEV da CISA](https://www.cisa.gov/known-exploited-vulnerabilities-catalog) e os avisos da
distribuição da VPS.

## PHP

A imagem base usa uma tag móvel. Uma imagem nova só nasce com um push ou com
`gh workflow run deploy.yml --ref dev`, e a correção da base **não chega sozinha**.

1. Confira se a base já tem a versão: `docker buildx imagetools inspect moodlehq/moodle-php-apache:8.4 --format '{{json .Image}}'`
2. Suba `.devcontainer/build/min-php-version`. O build reprova base abaixo dele, e o deploy confere de novo no container
3. Merge em `dev`

## Moodle

Siga a seção 5 de `docs/dev/estrutura-worktrees.md`. Antes, confira se os plugins passam: a validação
usa o `MOODLE_502_STABLE` atual, e não o commit do `dev`.

> **Rebuild "sem mudar código" não existe.** A validação instala o Moodle mais recente do ramo, e
> uma mudança do upstream pode reprovar código que passou na véspera. Foi o que aconteceu com o
> MDL-90110. Numa emergência, conte com esse tempo.

Quando há upgrade pendente, o deploy guarda um dump em `$VPS_PATH/backups`, fora do repo, com os
cinco mais recentes.

## Leitura de volta

Job verde não prova o que está rodando. Meça no container:

```bash
docker exec ldg-courses-moodle-1 php -v
docker exec ldg-courses-moodle-1 grep -E '^.release' /var/www/html/public/version.php
```

## Depois de uma falha explorável

**Investigue antes de fazer o deploy.** O rsync com `--delete` apaga o que não está no git, e a troca
de imagem descarta a camada e os logs do container: é evidência. Preserve primeiro.
