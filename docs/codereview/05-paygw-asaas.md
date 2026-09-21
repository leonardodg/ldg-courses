# payment/gateway/asaas

[Voltar ao índice](README.md)

## 1. Idempotência sem lock e sem índice único no banco
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:210`
- **Achado:** check-then-write sem lock de linha; `db/install.xml:38` declara
  o índice `asaaspaymentid` como `UNIQUE="false"`, então nada em nenhuma
  camada impede uma duplicata concorrente.
- **Cenário de falha:** um retry de webhook e a tarefa de reconciliação
  horária chamam `process_notification()` para o mesmo `asaaspaymentid`
  quase simultaneamente; ambos leem `paymentid=null` antes de qualquer
  escrita, ambos chamam `save_payment()`/`record_sale()`/`deliver_order()`.
  Para pagamento de assinatura ciclo 2+, ambas as chamadas podem até inserir
  uma nova linha via `adopt_subscription_cycle()` independentemente, sem
  constraint única para rejeitar a segunda.
- **Correção:** `SELECT ... FOR UPDATE` dentro de `start_delegated_transaction()`.
  Sem constraint única viável (o campo nasce `''`, não `NULL`, então um
  índice único colidiria entre todas as cobranças pendentes), a proteção é
  só por lock: trava por `asaaspaymentid` quando a linha já existe, e — no
  caso de ciclo 2+ que ainda não tem linha — trava a linha mais recente da
  ASSINATURA antes de decidir se adota um ciclo novo, reconferindo depois de
  travar. Commit logo após reservar `paymentid`, antes de `record_sale()`/`deliver_order()`.

## 2. `build_split()` retorna split vazio sem erro quando wallet da plataforma está em branco
- **Status:** corrigido
- **Arquivo:** `classes/asaas_client.php:411`
- **Achado:** retorna array de split vazio silenciosamente quando o wallet id
  da plataforma está em branco, enquanto `payment_processor::start_payment()`
  ainda grava um `feeamount` estimado não-zero para a mesma cobrança
  independente de o split ter sido de fato enviado.
- **Cenário de falha:** admin habilita Asaas e vincula a chave do vendedor
  mas deixa "Platform wallet ID" sem configurar no ambiente ativo. A cobrança
  sai com o vendedor ficando com 100%, sem erro, enquanto o `feeamount` da
  linha local é populado com uma comissão que nunca foi de fato transferida —
  o ledger reporta receita que o Asaas nunca de fato dividiu para a
  plataforma, silenciosa e indefinidamente.
- **Correção:** no webhook, quando `credentials::platform_wallet()` vem
  vazio, `feeamount` é gravado como `0.0` diretamente — não chama mais
  `fee_from()` nesse caso, então nunca cai no ramo de estimativa sem split
  real. Corrigido junto com o achado 3.

## 3. Webhook usa base de comissão padrão errada
- **Status:** corrigido
- **Arquivo:** `classes/payment_processor.php:242`
- **Achado:** o caminho do webhook chama `fee_from()` sem repassar o
  `feebase` da própria linha, caindo silenciosamente para `'gross'`, ao
  contrário de `start_payment()` que passa `$feebase` corretamente.
- **Cenário de falha:** normalmente mascarado pelo ramo de split real, mas se
  o wallet id da plataforma foi rotacionado depois da cobrança criada, ou o
  split foi omitido (achado acima), `fee_from()` cai no ramo de estimativa
  usando base hardcoded `'gross'` mesmo quando a base real da venda é `'net'`
  — calculando e gravando permanentemente o `feeamount` errado via
  `record_sale()`.
- **Correção:** a chamada de `fee_from()` no webhook agora passa
  `(string) $record->feebase` como 5º argumento (só é alcançada quando a
  wallet da plataforma está configurada — ver achado 2).

## 4. Guarda de isenção da conta da plataforma duplicada verbatim (asaas/pagarme)
- **Status:** corrigido
- **Arquivo:** `link.php:136`
- **Achado:** a isenção do guard "mesma carteira" para a conta da plataforma
  está duplicada verbatim em `paygw_pagarme/link.php`, sem helper compartilhado.
- **Cenário de falha:** uma mudança futura em como a conta da plataforma é
  reconhecida é aplicada a só uma das duas cópias, reintroduzindo
  silenciosamente a rejeição "wallet da empresa == wallet da plataforma" no
  gateway esquecido — quebra o fluxo de assinatura SaaS da plataforma em um
  gateway sem sinal de que existe uma cópia irmã.
- **Correção:** não há neste projeto um plugin de gateway compartilhado onde
  a regra pudesse morar uma vez só (cada gateway é standalone por desenho).
  Adicionado comentário cruzado nos dois arquivos apontando explicitamente
  um para o outro, para que uma mudança futura na regra não passe despercebida
  na cópia irmã. Duplicação de 1 linha, estável, aceita como trade-off.

## 5. `account` construído antes de checagem de capability
- **Status:** corrigido
- **Arquivo:** `link.php:45`
- **Achado:** `new \core_payment\account($accountid)` é construído a partir
  de um `required_param(PARAM_INT)` puro antes de qualquer checagem de
  capability, então um id inválido lança `dml_missing_record_exception` não
  controlada em vez de um erro limpo.
- **Cenário de falha:** requisitar `link.php?accountid=999999&environment=sandbox`
  mostra uma página de erro/debug genérica do Moodle em vez da mensagem
  própria do plugin "conta não existe". Não é explorável para acesso a
  credencial, mas é um caminho de entrada sem guarda que deveria falhar
  de forma limpa antes de tocar classes persistentes.
- **Correção:** checagem `$DB->record_exists('payment_accounts', ...)` antes
  de construir `\core_payment\account`, lançando `moodle_exception('invalidrecord', 'error', ...)`
  (string genérica já existente no core) em vez do erro cru.

## 6. Validação de chave API só checa prefixo do ambiente
- **Status:** corrigido
- **Arquivo:** `classes/form/link_form.php:79`
- **Achado:** `validation()` só checa o prefixo de ambiente da chave API
  colada, sem checagem de tamanho mínimo/charset antes de enviá-la ao vivo
  para o Asaas.
- **Cenário de falha:** um vendedor cola uma chave truncada por acidente de
  copiar-colar; o formulário aceita e só falha depois de uma ida-e-volta HTTP
  real ao Asaas, mostrando a mensagem de rejeição crua da API em vez de um
  aviso imediato do lado do cliente — não é falha de segurança, só uma
  ida-e-volta desnecessária à API real.
- **Correção:** checagem de tamanho mínimo (40) e charset antes da checagem
  de ambiente. Nova string `errorkeyformat` em `en`/`pt_br`/`es`.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/payment/gateway/asaas   # limpo
php vendor/bin/phpunit --testsuite paygw_asaas_testsuite                   # OK (69 tests, 157 assertions)
```

Sem mudança de schema neste plugin — `asaaspaymentid` nasce `''` (não
`NULL`), então um índice único colidiria entre todas as cobranças pendentes;
a correção do achado 1 é só por lock (`FOR UPDATE`), sem tocar `install.xml`.
