# enrol/marketplace

[Voltar ao índice](README.md)

## 1. Snapshot obsoleto sobrescreve renovação concorrente
- **Status:** corrigido
- **Arquivo:** `classes/task/sync_entitlements.php:65`
- **Achado:** o loop de expiração monta cada entitlement a partir de um
  snapshot `$record` obsoleto (buscado no topo de `execute()`) e chama
  `update()`, que grava o snapshot inteiro de volta — não só o status — sem
  reconferir contra a linha atual.
- **Cenário de falha:** um aluno paga uma fatura atrasada/renovação via
  webhook no mesmo momento em que a tarefa horária `sync_entitlements`
  processa aquele mesmo entitlement como recém-expirado. A thread do webhook
  rematricula o aluno; microssegundos depois o `update()` de snapshot
  obsoleto do cron sobrescreve a extensão recém-paga com o `timeend`/status
  antigos (`expired`). O `sync_user()` da mesma iteração do cron então
  suspende a matrícula recém-restaurada.
- **Correção:** cada entitlement agora é carregado FRESCO pelo id
  (`new entitlement((int) $record->id)`), e a condição de vencimento é
  reconferida contra o dado atual imediatamente antes de expirar — se já não
  vence mais (alguém renovou nesse meio-tempo), a iteração pula sem escrever.

## 2. `get_or_create_instance()` sem lock e sem índice único
- **Status:** corrigido
- **Arquivo:** `lib.php:95`
- **Achado:** check-then-act sem lock; a tabela `enrol` do core não tem
  índice único em `(courseid, enrol)`, só um índice não-único em `enrol`.
- **Cenário de falha:** dois alunos compram o mesmo curso nunca vendido
  antes, com milissegundos de diferença, via dois webhooks de pagamento
  diferentes. Ambas as threads não encontram instância existente, ambas
  chamam `add_instance()` — o curso termina com duas instâncias de matrícula
  `marketplace`, e `allow_manage()` bloqueia remover a duplicata via a UI.
- **Correção:** como não é possível adicionar índice único na tabela do
  core, a serialização é por lock: `SELECT id FROM {course} WHERE id = ?
  FOR UPDATE` dentro de `start_delegated_transaction()` — trava a linha do
  curso (que sempre existe) até o commit, e reconfere se a instância já
  existe depois de travar antes de criar.

## 3. `sync_user()` sem transação/lock, chamado por cron e webhook
- **Status:** corrigido
- **Arquivo:** `lib.php:179`
- **Achado:** lê estado "deveria ter" vs "tem atualmente" e grava sem
  transação/lock de linha; é chamado tanto pelo cron horário quanto por todo
  webhook de pagamento, sem mutex entre eles.
- **Cenário de falha:** dois `sync_user($userid)` para o mesmo usuário se
  sobrepondo. Risco imediato baixo hoje (cada escrita por curso é
  individualmente idempotente), mas sem isolamento nenhum uma mudança futura
  que tornasse a lógica por curso multi-etapa herdaria a corrida em silêncio.
- **Correção:** `SELECT id FROM {user} WHERE id = ? FOR UPDATE` dentro de
  `start_delegated_transaction()`, travando a linha do usuário por toda a
  duração da função — serializa chamadas concorrentes para o mesmo usuário
  sem tocar a lógica por curso. Compõe corretamente com a transação aninhada
  de `get_or_create_instance()` (achado 2), que `sync_user()` chama internamente.

## 4. Comentários acentuados violando padrão do projeto
- **Status:** corrigido
- **Arquivo:** `tests/sync_user_test.php:30`
- **Achado:** docblocks/comentários usam português acentuado, violando a
  regra explícita em `dev/CLAUDE.md`: "o código e os comentários também [em
  português], sem acentos".
- **Cenário de falha:** `lib.php` e `sync_entitlements.php` do mesmo plugin
  seguem corretamente sem acentos; só este arquivo de teste diverge em ~35
  linhas — inconsistente dentro do próprio plugin e provável de se propagar
  por copiar-colar em futuros arquivos de teste.
- **Correção:** acentos removidos de todo o arquivo (normalização NFD +
  remoção de diacríticos), preservando o texto — só afetava comentários e
  docblocks, nenhum literal de string usado em asserção.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/enrol/marketplace   # limpo
php vendor/bin/phpunit --testsuite enrol_marketplace_testsuite         # OK (13 tests, 35 assertions)
```

Sem mudança de schema neste plugin.
