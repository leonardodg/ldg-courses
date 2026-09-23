# Identificador de código em inglês — o que falta e o tamanho

Decisão de 23/09/2026: identificador de código (variável, propriedade,
parâmetro, método, classe) é sempre em inglês daqui pra frente. Comentário e
mensagem de commit continuam em português, sem acentos. Ver
`docs/coding-standards/README.md`.

## Por que existe este documento

Uma rodada de code review no mesmo dia interpretou mal a regra antiga
("escreve em português; o código e os comentários também") e reverteu um
arquivo que já tinha sido corrigido para inglês de volta para português — o
oposto do que deveria ter feito. A regra foi reescrita para não deixar essa
ambiguidade de novo, e este documento guarda o levantamento de tamanho feito
na hora, para quando alguém decidir converter a base existente.

## O que NÃO foi decidido

Converter os identificadores já em português da base existente para inglês.
Isso é trabalho separado, arriscado (base em produção, mais de 60 mil linhas)
e não tem prioridade definida. Até essa decisão existir, **não renomeie em
massa por conta própria** — só código novo, ou arquivo que um code review já
está tocando por outro motivo, nasce com identificador em inglês.

## Progresso

- **`format_ldg`: convertido em 23/09/2026.** 27 arquivos (produção + testes +
  AMD), 17 deles com pelo menos um identificador em português na estimativa
  original. `phpcs`, `phpunit` (70 testes) e `grunt` (eslint dos três módulos
  AMD, build regenerado) verdes depois da conversão. Nomes de teste
  (`test_aluno_no_curso_ldg_usa_o_portal`, etc.) continuam em português — é
  convenção separada e deliberada, documentada em
  `docs/dev/padrao-de-implementacao.md`.
- **`paygw_mercadopago`: convertido em 23/09/2026.** 23 arquivos (produção +
  testes + AMD), 11 deles com pelo menos um identificador em português na
  estimativa original — a varredura real achou mais (o dicionário inicial
  era incompleto). `phpcs` (42 arquivos), `phpunit` (127 testes, 308
  asserções) e `grunt` (eslint do `card_form.js`, build regenerado) verdes
  depois da conversão. Um bug de shadowing foi pego antes de commitar: em
  `webhook.php` a variável de loop teria colidido com um `$type` já existente
  fora do loop (renomeada para `$apptype`); em `card_form.js`, `documento`
  não virou `document` porque isso teria sombreado o `document` global do
  navegador dentro da mesma função (virou `cpf`).
- **`mod_ldgvideo`: convertido em 23/09/2026.** 31 arquivos (produção + testes;
  sem AMD neste plugin), 8 deles com pelo menos um identificador em português
  na estimativa original — a varredura real achou mais, incluindo a classe
  inteira `classes/url.php` (nomes de método como `atributo`, `montar`,
  `canonicalizar`, `proporcao_por_tamanho` viraram `attribute`, `assemble`,
  `canonicalize`, `ratio_from_size`) e o passo de Behat
  `tests/behat/behat_mod_ldgvideo.php` (`medir_quadro()` → `measure_frame()`,
  incluindo variáveis dentro do JS embutido no `evaluate_script`). `phpcs`
  (31 arquivos) e `phpunit` (43 testes, 118 asserções) verdes depois da
  conversão.
- **`local_marketplace`: convertido em 23/09/2026.** 76 arquivos (produção +
  testes; sem AMD neste plugin), 7 deles com pelo menos um identificador em
  português na estimativa original — a varredura real achou muito mais,
  incluindo o maior arquivo de teste do plugin (`tests/roles_test.php`, 97
  ocorrências) e nomes de método/constante inteiros
  (`tests/db_schema_test.php`: `tabelas_declaradas()` → `declared_tables()`,
  `passo_em()` → `step_at()`, constantes `REMOVIDAS`/`PLUGINS_DO_PROJETO` →
  `REMOVED`/`PROJECT_PLUGINS`). `phpcs` (76 arquivos) e `phpunit` (165 testes,
  570 asserções) verdes depois da conversão.
- **`local_partners`: convertido em 23/09/2026.** 39 arquivos (produção +
  testes + 2 módulos AMD), 6 deles com pelo menos um identificador em
  português na estimativa original — a varredura real achou muito mais,
  incluindo os dois módulos AMD inteiros (`colormode.js`: `PREFERENCIA`,
  `ESCURO`, `CLARO`, `aplicar()` → `PREFERENCE`, `DARK`, `LIGHT`, `apply()`;
  `sectionbar.js`: `medirCabecalho()`, `observarSecoes()`, `adiar()` →
  `measureHeader()`, `observeSections()`, `debounce()`) e a classe
  `classes/seo.php` inteira (parâmetro `$superficie` usado em ~15 métodos →
  `$surface`, mais dezenas de variáveis locais). `phpcs` (39 arquivos),
  `phpunit` (86 testes, 248 asserções, 1 skip pré-existente) e `grunt`
  (eslint dos dois módulos AMD, build regenerado) verdes depois da
  conversão.
- **`theme_ldg`: convertido em 23/09/2026.** 22 arquivos, 6 na estimativa
  original — a varredura real achou só um caso a mais
  (`classes/privacy/provider.php`: `$valor`/`$sufixo` → `$value`/`$suffix`,
  repetido em dois métodos). Quase todo o plugin já nascia com identificador
  em inglês; os módulos AMD (`accessibilitybar.js`, `colormode.js`,
  `navmenu.js`) já estavam limpos. `phpcs` (22 arquivos) e `phpunit` (15
  testes, 30 asserções) verdes. Sem mudança em AMD, sem `grunt` necessário.
- **`paygw_pagarme`: convertido em 23/09/2026.** 31 arquivos (produção +
  testes; nenhum AMD tocado), 3 na estimativa original — a varredura real
  achou mais, incluindo nomes de método de fixture inteiros
  (`tests/fixtures/documented_responses.php`: `pix_pendente()`,
  `cobranca_paga_com_split()`, `payable_do_vendedor()` →
  `pending_pix()`, `paid_charge_with_split()`, `payable_for_seller()`, e
  outros três). Um sed grosseiro renomeou por engano um nome de método
  `test_*` que continha a substring convertida
  (`test_veredito_da_cobranca_que_falhou_de_verdade`); pego e revertido antes
  do commit. `phpcs` (31 arquivos) e `phpunit` (119 testes, 213 asserções)
  verdes.
- **`paygw_asaas`: convertido em 23/09/2026.** 25 arquivos (produção +
  testes; nenhum AMD tocado), 2 na estimativa original — a varredura real
  achou mais, espalhado por `payment_processor.php`, `gateway.php` e
  `asaas_client.php` (`$comum`, `$recorrencia`, `$cobrancas`, `$linha`,
  `$resposta` → `$common`, `$recurrence`, `$charges`, `$record`,
  `$response`, entre outros). `phpcs` (25 arquivos) e `phpunit` (69 testes,
  157 asserções) verdes.
- Os outros 3 plugins seguem pendentes.

## Tamanho estimado, por plugin

Levantamento com uma lista de ~60 palavras portuguesas comuns na base
(`curso`, `empresa`, `oferta`, `pagamento`, `usuario`, `cobranca`,
`assinatura`, `categoria`, `direito`, `matricula`, `percentual`, `comissao`,
etc.), contando arquivos `.php` que têm pelo menos um identificador batendo
com a lista. **É uma estimativa por baixo** — a lista não é exaustiva (não
cobre variantes de gênero/plural nem todo o vocabulário do domínio), então o
número real de arquivos e de identificadores é maior.

| Plugin | Arquivos `.php` | Linhas | Arquivos com ≥1 identificador em português (estimativa) |
|---|---|---|---|
| `local/marketplace` | 76 | 16.932 | ~~7~~ 0 (convertido) |
| `local/partners` | 39 | 8.843 | ~~6~~ 0 (convertido) |
| `blocks/marketplace` | 10 | 1.226 | 0 |
| `paygw_mercadopago` | 42 | 10.692 | ~~11~~ 0 (convertido) |
| `paygw_asaas` | 25 | 5.177 | ~~2~~ 0 (convertido) |
| `paygw_pagarme` | 31 | 6.525 | ~~3~~ 0 (convertido) |
| `enrol_marketplace` | 11 | 1.095 | 1 |
| `availability_marketplace` | 8 | 822 | 1 |
| `format_ldg` | 38 | 5.694 | ~~17~~ 0 (convertido) |
| `theme_ldg` | 22 | 3.289 | ~~6~~ 0 (convertido) |
| `mod_ldgvideo` | 31 | 3.400 | ~~8~~ 0 (convertido) |
| **Total** | **333** | **63.695** | **19 restantes** (≈6% dos arquivos, por baixo) |

`format_ldg` e `paygw_mercadopago` concentram a maior densidade — ambos
plugins antigos, com bastante lógica de negócio nomeada em português desde o
início do projeto.

## Risco de uma conversão em massa

- **Renomear variável local é seguro** (escopo de função/método), mas
  **renomear propriedade, parâmetro público ou constante pode quebrar
  chamador** — alguns métodos já têm parâmetro nomeado usado por chamador
  externo ou teste com argumento nomeado (`named argument`).
- **Testes já escritos usam os nomes atuais** em alguns casos (ex.: mensagem
  de asserção referenciando nome de variável em erro). Precisa rodar o
  testsuite inteiro do plugin depois de cada arquivo convertido, não só
  phpcs.
- **`git blame`/histórico fica mais difícil de ler** para commits antigos
  depois da conversão — não é motivo para não fazer, mas é custo real.
- Estimativa de esforço: dado o tamanho (333 arquivos, ~64 mil linhas, 11
  plugins independentes com seus próprios testsuites), uma conversão
  completa e verificada plugin por plugin é trabalho de múltiplas sessões,
  não de uma tarde.

## Como proceder quando a decisão for tomada

1. Um plugin por vez, começando pelo de maior densidade (`format_ldg` ou
   `paygw_mercadopago`) para validar o processo antes de escalar.
2. Rodar phpcs + phpunit do plugin inteiro depois de cada arquivo — não só no
   final.
3. Não tocar em string de idioma (`get_string()` key), nome de tabela, nome
   de campo de banco, nem chave de contexto de template mustache — essas são
   contrato externo (webservice, banco, mustache) e já são em inglês na
   maioria dos casos; mexer nelas quebra compatibilidade sem necessidade.
4. Atualizar este documento ao terminar cada plugin, e apagá-lo quando a
   conversão completa estiver feita.
