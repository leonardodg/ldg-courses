# local/partners

[Voltar ao índice](README.md)

## 1. Race condition em `approve()`/`reject()` (dupla empresa)
- **Status:** corrigido
- **Arquivo:** `classes/api.php:358` (`guard_pending()`)
- **Achado:** checa status e só depois persiste o novo, sem transação/lock,
  apesar do docblock afirmar que dupla submissão não pode criar duas empresas.
- **Cenário de falha:** duplo clique em "Aprovar" — ambos os POSTs carregam
  `status=pending`, ambos passam na guarda antes de qualquer UPDATE, ambos
  chamam `marketplace::create_company()` — duas categorias/empresas de uma
  única aplicação.
- **Correção:** `guard_pending()` agora trava a linha com
  `SELECT ... FOR UPDATE` dentro de uma transação (`start_delegated_transaction()`)
  aberta pelo chamador (`approve()`/`reject()`); a segunda submissão espera o
  commit da primeira e enxerga o status já mudado.

## 2. `resolve_owner()` sequestra conta por email não verificado
- **Status:** corrigido
- **Arquivo:** `classes/api.php:494`
- **Achado:** entrega ownership da empresa a qualquer conta existente que bata
  com o `contactemail` informado, sem prova de controle da caixa postal quando
  a confirmação por email está desabilitada.
- **Cenário de falha:** com `requireemailconfirmation` desligado, um
  submissor anônimo digita o email de outra pessoa; se o admin aprovar
  deixando `ownerid` em branco (instrução explícita da UI para "candidato já
  tem conta"), a ownership vai para a conta real e alheia.
- **Correção:** `resolve_owner()` só busca conta existente por email quando
  `$application->get('timeconfirmed')` está preenchido — sinal que já existia
  no persistent para "candidato autenticado" ou "confirmou o link enviado
  para o próprio email". Sem essa prova, `create_owner()` sempre cria conta
  nova. Teste antigo (`test_conta_existente_e_reaproveitada`) reescrito em
  dois: um confirma que SEM prova a conta alheia não é tocada
  (`test_conta_existente_nao_e_reaproveitada_sem_confirmacao`), outro que COM
  o fluxo de confirmação por link a conta é reaproveitada normalmente
  (`test_conta_existente_e_reaproveitada_apos_confirmacao`).

## 3. Duplicidade de notificação em `confirm()`
- **Status:** corrigido
- **Arquivo:** `classes/api.php:110`
- **Achado:** check-then-act sem lock na transição de status; dois hits quase
  simultâneos no mesmo token de confirmação passam ambos pela guarda
  `STATUS_UNCONFIRMED`.
- **Cenário de falha:** um prefetcher de segurança de email (Outlook Safe
  Links) acessa o link antes do clique real do destinatário; ambos passam a
  guarda e ambos chamam `notify_reviewers()` — notificação duplicada.
- **Correção:** mesmo padrão do achado 1 — `SELECT ... FOR UPDATE` dentro de
  `start_delegated_transaction()` antes de checar `STATUS_UNCONFIRMED`.

## 4. Falta link de logout em `apply_page.php`
- **Status:** corrigido
- **Arquivo:** `classes/output/apply_page.php:60`
- **Achado:** exporta `isloggedin`/`loginurl` mas nunca `logouturl`, ao
  contrário de `landing_page.php`; `apply.mustache` não tem bloco
  `{{#isloggedin}}` correspondente.
- **Cenário de falha:** um gerente logado é roteado para `apply.php` por
  `cta_url()`; a página só mostra ENTRAR, nunca SAIR — viola a regra explícita
  do projeto de que usuário logado vê SAIR onde anônimo vê ENTRAR (com sesskey).
- **Correção:** `apply_page::export_for_template()` passou a exportar
  `logouturl` via `landing_page::logout_url()` (mesmo helper de
  `landing_page.php`); `apply.mustache` ganhou o bloco `{{#isloggedin}}` com
  SAIR, espelhando o padrão já usado em `sectionbar.mustache`.

## 5. Duplo escape de `companyname` em emails plaintext
- **Status:** corrigido
- **Arquivo:** `classes/api.php:148, 212, 400`
- **Achado:** `send_confirmation()`, `notify_reviewers()` e `notify_applicant()`
  passam `companyname` por `format_string()` (escape HTML) antes de
  interpolar em corpo `FORMAT_PLAIN`.
- **Cenário de falha:** empresa "Alves & Filhos Ltda" vira `&amp;amp;` no
  corpo do email, corrompendo visualmente o nome em toda notificação da
  aplicação.
- **Correção:** as três chamadas (`send_confirmation()`, `notify_reviewers()`,
  `notify_applicant()`) passaram a usar
  `format_string($valor, true, ['escape' => false])`, evitando o
  htmlspecialchars que só faz sentido para corpo HTML. `contactname` recebeu
  o mesmo tratamento por ter o mesmo problema.

## 6. Throttle de spam fraco em `apply.php`
- **Status:** corrigido
- **Arquivo:** `classes/application.php:388`
- **Achado:** único throttle é 3 submissões/hora por igualdade exata de IP,
  com reCAPTCHA desligado a menos que o admin configure duas chaves extras.
- **Cenário de falha:** um atacante submete repetidamente com o email real de
  uma vítima como `contactemail`, rodando IPv6 (trivial em redes
  móveis/VPS) e deixando o honeypot vazio — cada submissão fica abaixo do
  limite por IP, gerando um vetor de email-bombing contra terceiros.
- **Correção:** novo `application::count_recent_from_email()`, checado em
  `application_form::validation()` ao lado do limite por IP já existente —
  agora o mesmo `contactemail` também não pode superar `api::max_per_hour()`
  em uma hora, independente de quantos IPs o remetente usar.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/local/partners   # limpo
php vendor/bin/phpunit --testsuite local_partners_testsuite         # OK (86 tests, 248 assertions, 1 skipped pre-existente)
```

Mustache lint (`npx grunt`) não foi rodado nesta rodada — exige host com
Node v22, fora do ambiente disponível aqui. O bloco novo em `apply.mustache`
espelha exatamente a estrutura já em produção em `sectionbar.mustache`.
