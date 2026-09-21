# course/format/ldg

[Voltar ao índice](README.md)

## 1. Destino Forum/Certificado embutido sem checagem de bloqueio
- **Status:** pendente
- **Arquivo:** `classes/output/courseformat/content.php:122`
- **Achado:** o destino Forum/Certificado pega o primeiro cm do bucket
  não-filtrado do catálogo e embute direto, sem checagem `uservisible`/lock e
  sem mensagem de bloqueio, ao contrário do caminho AULA que trata
  explicitamente itens bloqueados.
- **Cenário de falha:** um aluno que não comprou o entitlement que libera o
  certificado do curso clica na aba Certificado. Em vez da mensagem de
  cadeado/bloqueio usada em todo o resto do portal, recebe qualquer página
  crua de "acesso negado" que o módulo embutido renderizar dentro do
  iframe — UX inconsistente, sem defesa em profundidade.
- **Correção:**

## 2. Aba Forum/Certificado mostrada mesmo sem item visível
- **Status:** pendente
- **Arquivo:** `classes/portalnav.php:116`
- **Achado:** `destinations()` decide se mostra a aba Forum/Certificado
  usando `catalog->has($chave)`, que só checa que o bucket não está vazio,
  não que o usuário atual pode ver algum item nele.
- **Cenário de falha:** num curso onde o único certificado está bloqueado por
  um entitlement não cumprido, todo aluno ainda vê a aba "Certificado" nas
  três superfícies de navegação. Clicar leva ao embed quebrado do achado
  acima, em vez de a aba simplesmente não aparecer.
- **Correção:**

## 3. `get_selected_cm()` teleporta silenciosamente para primeira lição visível
- **Status:** pendente
- **Arquivo:** `lib.php:197`
- **Achado:** cai silenciosamente para a primeira lição `uservisible` de todo
  o curso quando a lição pedida/próxima não é `uservisible`, sem aviso de
  bloqueio.
- **Cenário de falha:** um aluno clica em "próxima lição" esperando a lição
  4, mas ela está bloqueada. O aluno é teleportado silenciosamente para uma
  lição disponível não relacionada, sem explicação, enquanto o `lessonnav`
  ainda mostrava o nome da lição bloqueada como se fosse alcançável.
- **Correção:**

## 4. `durations_for()` chamado por seção, não por página
- **Status:** pendente
- **Arquivo:** `classes/output/courseformat/content/lessonlist.php:177`
- **Achado:** `lesson::durations_for()` é chamado uma vez por seção dentro de
  `export_lessons()`, não uma vez por página como o próprio docblock do
  método em `lesson.php` afirma.
- **Cenário de falha:** um curso com N seções dispara N consultas de duração
  separadas em vez de 1, reintroduzindo em nível de seção o exato problema
  N+1 que o docblock da classe afirma ter sido corrigido, sem detecção porque
  contagem de consultas de duração não é verificada por nenhum teste.
- **Correção:**

## 5. `get_selected_cm()` duplica varredura de classificação do `catalog`
- **Status:** pendente
- **Arquivo:** `format.php:174`
- **Achado:** revarre toda seção/cm e chama `catalog::classify()` por cm,
  duplicando exatamente a varredura de classificação que o construtor de
  `catalog.php` já faz momentos depois — duplicação que o próprio docblock de
  `catalog.php` afirma ter sido eliminada.
- **Cenário de falha:** toda renderização de página do portal faz uma
  varredura completa de classificação do curso pelo menos duas vezes, mais
  uma terceira varredura parcial em `lessonlist.php::export_lessons()` —
  triplicando o trabalho de classificação por requisição e criando uma
  armadilha de manutenção em três frentes para qualquer mudança futura na
  regra de classificação.
- **Correção:**

## 6. `section_progress` reimplementa regra do core em silêncio
- **Status:** pendente
- **Arquivo:** `classes/section_progress.php:36`
- **Achado:** reimplementa manualmente a regra de percentual de conclusão por
  seção do Moodle porque o método real do core é `protected` — o próprio
  docblock do arquivo admite: "Se o Moodle mudar essa regra, a nossa diverge
  em silêncio."
- **Cenário de falha:** um upgrade futuro do core muda o que conta para
  conclusão de seção; as barras de progresso do `format_ldg` discordam
  silenciosamente do resto do relatório de conclusão do próprio Moodle, com
  só um teste unitário do mesmo plugin capaz de pegar a divergência.
- **Correção:**
