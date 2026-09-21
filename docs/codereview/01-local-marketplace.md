# local/marketplace

[Voltar ao índice](README.md)

## 1. Cross-tenant IDOR em `offer::add_course()`

- **Status:** corrigido
- **Arquivo:** `classes/offer.php:295`
- **Achado:** `add_course()` vincula um `courseid` a uma oferta sem checar que o
  curso pertence à mesma empresa/categoria da oferta.
- **Cenário de falha:** um gerente de empresa (capability `local/marketplace:managecompany`
  escopada à própria empresa) edita uma oferta em `offer_edit.php:126`, que
  itera o array `courses[]` postado e chama `$o->add_course((int) $courseid)`
  sem checagem de propriedade em nenhum ponto da cadeia. Submeter um `courseid`
  de outra empresa funciona e insere uma linha em `local_marketplace_offer_course`
  — quebra o isolamento company=category que é a fronteira multi-tenant do projeto.
- **Correção:** `add_course()` agora chama `company::owns_course()` antes de
  inserir — confere igualdade exata de categoria OU que a categoria do curso
  é descendente da categoria da empresa (via `path` do `core_course_category`),
  e lança `errorcoursenotowned` quando não pertence. String nova em
  `en`/`pt_br`/`es`. **Atualização (checkpoint 8):** o mesmo problema apareceu
  duplicado em `availability_marketplace\frontend::get_company_for_course()`
  — a lógica foi consolidada em `company::owns_course()`/`company::for_course()`,
  públicos, e os dois lugares passaram a chamá-los em vez de reimplementar.

## 2. `$context` indefinido em `offers.php`

- **Status:** corrigido
- **Arquivo:** `offers.php:104`
- **Achado:** a página pública da vitrine chama `format_text()` com `$context`
  nunca atribuído (só existe `$PAGE->set_context()`).
- **Cenário de falha:** qualquer empresa com `pageintro` configurado dispara o
  aviso de variável indefinida e `format_text()` recebe `context => null`,
  pulando filtros (multilang, mediaplugin, embedded-file) em toda vitrine
  pública com intro configurada; em `DEBUG_DEVELOPER` o aviso aparece na tela
  para visitantes anônimos.
- **Correção:** trocado `$context` por `$company->get_context()`, que já é o
  mesmo contexto passado a `$PAGE->set_context()` na mesma página.

## 3. `refund_sale()` sem guarda de idempotência

- **Status:** corrigido
- **Arquivo:** `classes/api.php:932`
- **Achado:** chama `refund()` do gateway sem nenhuma guarda de idempotência;
  `record_refund()` (idempotente) só roda depois que a chamada ao gateway já
  teve sucesso.
- **Cenário de falha:** duplo clique em "Reembolsar" no `report.php`, ou dois
  admins agindo sobre o mesmo `paymentid` — se o `refund()` do gateway
  específico não for ele mesmo idempotente, o comprador é reembolsado duas vezes.
- **Correção:** nova coluna `local_marketplace_sale.refundedat` (upgrade
  2026091706). `refund_sale()` reserva o estorno dentro de uma transação
  própria (`refundedat = now()`) ANTES de chamar o gateway; se já estava
  reservado, retorna falso sem chamar o gateway de novo. Se o gateway recusar,
  libera a reserva (`refundedat = null`) para permitir nova tentativa.
  Estreita bastante a janela, embora não seja um lock de linha 100% atômico
  entre bancos.

## 4. Exceção não tratada em webhook duplicado (`record_sale()`)

- **Status:** corrigido
- **Arquivo:** `classes/api.php:402`
- **Achado:** `record_sale()` faz check-then-insert em `payments.id` sem
  transação/lock; existe chave única em `paymentid` (`db/upgrade.php:239`), e
  uma duplicata concorrente genuína lança `dml_write_exception` não capturada.
- **Cenário de falha:** um gateway reenvia o mesmo webhook duas vezes em
  milissegundos; ambas as chamadas avaliam `sale::get_record(['paymentid' => ...])`
  como falso antes de qualquer commit, a segunda `create()` viola a chave
  única e retorna 500 ao gateway em vez do retorno idempotente esperado.
- **Correção:** `create()` agora fica num `try/catch (\dml_write_exception)`;
  ao capturar, rebusca `sale::get_record(['paymentid' => ...])` e retorna a
  venda que a outra requisição já gravou, em vez de deixar a exceção subir.

## 5. Ciclo de faturamento de 30 dias duplicado (SaaS)

- **Status:** corrigido
- **Arquivo:** `classes/payment/service_provider.php:264` (e `api.php:329`)
- **Achado:** o literal `30 dias` está hardcoded em dois lugares independentes,
  mantidos em sincronia apenas por um comentário que diz que eles "precisam concordar".
- **Cenário de falha:** se um dos literais mudar sem o outro, o gateway cobra
  em uma cadência (`api.php:329`) enquanto `company::extend_plan()` concede
  acesso em outra (`service_provider.php:264`) — divergência silenciosa entre
  cobrança e acesso.
- **Correção:** criada `api::PLAN_CYCLE_DAYS = 30`, única fonte; ambos os
  pontos (`api::recurrence_for_plan()` e
  `service_provider::deliver_order_plan()`) agora leem essa constante.

## 6. Docblock de capability contradiz decisão do projeto

- **Status:** corrigido
- **Arquivo:** `db/access.php:39`
- **Achado:** o docblock de `local/marketplace:createcompany` descreve
  autoatendimento ("qualquer usuario autenticado cria empresa"), contradizendo
  a decisão explícita em `dev/CLAUDE.md` de que não há autoatendimento — a
  capability em si já está segura (`archetypes => []`), mas o comentário pode
  induzir um mantenedor futuro a atribuí-la ao papel de usuário autenticado.
- **Cenário de falha:** um mantenedor confiando no comentário atribui a
  capability ao Authenticated User esperando que exista alguma outra guarda —
  não existe; `admin/company_edit.php` não faz checagem adicional além dessa
  capability.
- **Correção:** removida a linha do docblock que descrevia autoatendimento;
  o comentário agora só afirma o comportamento real (não concedida a
  ninguém por padrão, não é autoatendimento). A definição da capability
  (`archetypes => []`) já estava correta e não mudou.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/local/marketplace   # limpo
php vendor/bin/phpunit --testsuite local_marketplace_testsuite         # OK (162 tests, 564 assertions)
php admin/cli/upgrade.php --non-interactive                            # 2026091706: Success
php admin/cli/check_database_schema.php                                # Database structure is ok.
```
