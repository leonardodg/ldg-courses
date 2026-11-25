# payment/gateway/mercadopago

[Voltar ao índice](README.md)

## 1. Status "approved" gravado antes da entrega (venda travada sem entitlement)
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:846`
- **Achado:** `process_notification()` marca a linha local `status='approved'`
  com `paymentid` setado ANTES de chamar `local_marketplace\api::record_sale()`
  e `helper::deliver_order()`. Se qualquer um lançar exceção, a linha fica
  "approved" para sempre sem entrega.
- **Cenário de falha:** `record_sale()`/`deliver_order()` lança (deadlock
  transitório, exceção do lado do marketplace) logo após a linha já persistir
  `status='approved'`. Qualquer retry encontra a linha já "approved" e cai na
  guarda de idempotência antes de tentar `record_sale()`/`deliver_order()`
  de novo; `reconcile.php` não resgata porque exige `status='pending'`. Dinheiro
  cobrado e registrado como aprovado, aluno nunca recebe o direito — permanente
  e silenciosamente.
- **Correção:** o `paymentid` é gravado imediatamente após `save_payment()`,
  mas com o `status` ANTERIOR (não `approved`) — só depois que
  `record_sale()` e `deliver_order()` terminam com sucesso é que `status`
  vira `approved` de verdade. Se qualquer uma lançar, a linha fica elegível
  para nova tentativa, e não recria o pagamento (`paymentid` já não está
  vazio). Corrigido junto com o achado 2 (mesma reestruturação).

## 2. Idempotência sem lock/transação
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:804`
- **Achado:** sequência ler-checar-escrever sem lock/transação; um retry
  genuinamente concorrente do webhook ou hit simultâneo do reconcile passam
  ambos pela única guarda de idempotência.
- **Cenário de falha:** retry do próprio Mercado Pago chega enquanto a
  primeira entrega ainda está em voo, ou a tarefa de reconciliação horária
  processa o mesmo `external_reference` no mesmo momento — ambas as
  requisições leem a mesma linha pendente antes de qualquer escrita, ambas
  executam `save_payment()`, `record_sale()` e `deliver_order()` de novo:
  linha duplicada em `{payments}`, comissão duplicada, matrícula/entitlement
  estendido em duplicidade.
- **Correção:** leitura via `SELECT ... FOR UPDATE` dentro de
  `start_delegated_transaction()`, travando a linha (por `mppaymentid` ou,
  na primeira notificação, por `id` após `locate_transaction()`). O commit
  acontece logo após reservar `paymentid` — `record_sale()`/`deliver_order()`
  rodam fora da transação para não segurar o lock durante a chamada externa.

## 3. Reconcile só varre `status='pending'` literal
- **Status:** corrigido
- **Arquivo:** `classes/task/reconcile.php:75`
- **Achado:** `WHERE` só re-varre linhas com status literalmente `pending`,
  excluindo permanentemente linhas cujo status avançou para um status
  não-final como `in_process` ou `authorized`.
- **Cenário de falha:** pagamento em análise antifraude fixa `status='in_process'`.
  Se o webhook seguinte que mudaria para `approved`/`rejected` se perder, a
  linha fica travada com `in_process` e `paymentid` nulo para sempre —
  reproduz a mesma classe de bug "reconciliação para de varrer cobrança
  vencida" já corrigida uma vez para outro valor de status.
- **Correção:** `WHERE` trocado para `status NOT IN ('rejected', 'cancelled')
  AND paymentid IS NULL` — cobre `pending`, `in_process`, `authorized` e
  qualquer status intermediário futuro, excluindo só os dois terminais que o
  Mercado Pago nunca reverte.

## 4. Sandbox/produção é flag global desconectada do token armazenado
- **Status:** corrigido
- **Arquivo:** `classes/application.php:199`
- **Achado:** `public_key()`/`test_mode()` derivam sandbox-vs-produção
  inteiramente de uma configuração global única, reavaliada a cada checkout,
  sem registro de qual modo o `access_token` OAuth armazenado de uma empresa
  foi realmente emitido.
- **Cenário de falha:** Empresa A vincula Mercado Pago com `testmode` ON
  (token sandbox). Admin depois desliga o `testmode` globalmente (indo ao ar
  com Empresa B) sem a Empresa A re-vincular. Próximo aluno comprando da
  Empresa A recebe a `public_key` de produção no checkout enquanto o
  `payment_processor` cobra com o `access_token` ainda sandbox da Empresa A —
  Mercado Pago recusa com "Invalid users involved" na frente do aluno pagante.
- **Correção:** novo campo `testmode` em `ACCOUNT_FIELDS`, gravado por
  `oauth_callback.php` com o modo vigente NO MOMENTO do vínculo.
  `application::test_mode()` ganhou parâmetros opcionais `$type`/`$accountid`:
  com uma conta em contexto, usa o marcador da PRÓPRIA conta; sem conta
  (formulário de configuração, ou conta vinculada antes deste campo existir),
  cai no `testmode` global como antes. `public_key()` repassa `$accountid`, e
  os três chamadores (`subscribe.php`, `confirm_cycle.php`,
  `switch_to_card.php`) agora passam `$record->accountid`.

## 5. Duplicidade de ciclo de cobrança sem lock/constraint única
- **Status:** corrigido
- **Arquivo:** `classes/task/charge_due_cycles.php:91`
- **Achado:** sem lock e sem constraint única no banco em
  `(subscriptionid, cycles)` protegendo o insert do próximo ciclo pendente em
  `issue_card_cycle()`.
- **Cenário de falha:** a tarefa estoura o intervalo ou é disparada
  manualmente durante um cron em andamento; ambas as execuções inserem uma
  nova linha de ciclo com `build_next_cycle()` — dois `external_reference`
  distintos, cada um gerando seu próprio link de confirmação; se o aluno
  confirmar os dois, duas cobranças para o mesmo período.
- **Correção:** novo índice único `(subscriptionid, cycles)` (upgrade
  2026091772) — `subscriptionid` é `NULL` na venda avulsa, e `NULL` não
  colide consigo mesmo num índice único, então vendas avulsas não são
  afetadas. `issue_card_cycle()` e `issue_invoice_cycle()` capturam
  `\dml_write_exception` e retornam silenciosamente quando a segunda
  execução perde a corrida.

## 6. `refresh_tokens.php` sobrescreve linha inteira sem lock otimista
- **Status:** corrigido
- **Arquivo:** `classes/task/refresh_tokens.php:127`
- **Achado:** lê o JSON de config completo de uma empresa uma vez, depois faz
  sobrescrita incondicional da linha inteira no fim, sem checagem de lock
  otimista contra mudanças humanas concorrentes na mesma linha.
- **Cenário de falha:** enquanto `refresh_tokens.php` está no meio do loop, o
  dono da empresa usa `oauth_unlink.php` para revogar a conexão na mesma
  linha já lida em memória pela tarefa. A sobrescrita incondicional posterior
  da tarefa ressuscita os tokens acabados de remover — ou o inverso, apaga um
  relink recém-feito.
- **Correção:** lock otimista — a escrita final usa
  `set_field_select('config', ..., 'id = :id AND config = :original', ...)`,
  onde `$original` é o config lido no topo do loop. Se o config mudou
  enquanto a tarefa rodava (unlink ou relink humano), a escrita não se aplica.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/payment/gateway/mercadopago   # limpo
php admin/cli/upgrade.php --non-interactive                                      # 2026091772: Success
php admin/cli/check_database_schema.php                                          # Database structure is ok.
php vendor/bin/phpunit --testsuite paygw_mercadopago_testsuite                    # OK (127 tests, 308 assertions)
```

Teste `application_test::test_os_campos_da_conta_sao_um_conjunto_por_tipo`
atualizado para incluir o novo campo `testmode` na lista esperada.
