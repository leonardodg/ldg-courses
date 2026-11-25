# ADR-0015 — O compose compartilhado não fixa perfil de desenvolvimento

**Situação:** Aceita · **Data:** 2026-11-02

## Contexto

O `dev.yml` sobe na máquina local e também na VPS. Ele fixava `MOODLE_DEBUG` e
`MOODLE_DEBUG_DISPLAY` em `environment:`, que tem precedência sobre o `env_file`. Assim, o valor que
o deploy gravava no `dev.env` nunca valia. O mesmo arquivo publicava a porta do Xdebug.

A imagem da VPS também sai do target `development`, com Xdebug, git, node e phpcs.

## Decisão

Valor que difere por ambiente sai do `dev.yml`: debug vem do `dev.env` de cada ambiente, e a porta
do Xdebug passa para `local-dev.yml`, que a VPS não carrega. O deploy confere o resultado, e o smoke
test reprova `developerdebug` ligado.

A VPS **continua** no target `development` por enquanto.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Só mudar o valor no `deploy.yml` | É o que já existia, e não tinha efeito. O defeito era a precedência |
| Um `prod.yml` por cima do `dev.yml` | Continua dependendo de sobrescrever um valor inseguro |
| Trocar para o target `production` agora | O deploy depende do bind mount do repo, e aquele target assume outro modelo. Exige prova própria |

## Consequências

O ambiente local depende de ter as linhas no `dev.env`; o `dev.env.example` passou a tê-las. Com
debug desligado, o cache de strings liga na VPS, e uma tradução alterada exige purga.

Fica pendente a imagem de desenvolvimento na VPS.

## Como saber que erramos

Se o smoke test passar a reprovar por `developerdebug`, algum arquivo voltou a fixar o valor. Se o
desenvolvimento local perder o debug, o `dev.env` da worktree está sem as linhas.
