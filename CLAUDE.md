# Marketplace de cursos — contexto para agentes

Plataforma Moodle 5.2 onde qualquer pessoa publica curso gratuito ou pago, com
split de pagamento. Três gateways: Mercado Pago, Asaas e Pagar.me.

Este arquivo é carregado automaticamente a cada sessão. Leia antes de propor
qualquer coisa — várias decisões aqui parecem erradas até você conhecer a razão.

## O que já foi decidido, e não é para revisitar

| Decisão | Por quê |
|---|---|
| **Sem fork do Moodle** | IOMAD modifica 171 arquivos do core e remove 14. Prende o projeto à versão dele. Tudo que ele resolve, exceto domínio por vendedor, já existe no 5.2. |
| **Empresa = categoria de cursos** | Dá contexto para papel, tema e conta de pagamento. Não é escolha estética: o `core_payment` escopa conta por contexto. |
| **Direito de acesso é a fonte única da verdade** | Matrícula e liberação de seção leem `local_marketplace_entitlement`. Ninguém lê a venda para decidir acesso. |
| **Sem auto-atendimento para criar empresa** | Criar empresa cria uma **categoria**, objeto global. A parceria é fechada fora do sistema; o admin provisiona. |
| **Campos, não HTML livre, na vitrine** | HTML do vendedor A rodando no navegador do aluno da empresa B é XSS entre inquilinos. Quem quer página própria usa a API. |
| **Sem `$CFG->sessioncookiedomain`** | A sessão passa a ser por domínio. Login no domínio do vendedor não vale na plataforma — comportamento desejado. |

## Restrições externas que moldaram o desenho

Não são preferências. São limites de terceiros, verificados.

**`core_payment::get_payable()` não recebe o usuário.** Valor, moeda e conta são
função pura do `itemid`. Uma oferta não pode ser BRL para um aluno e ARS para
outro — por isso o país vive na oferta, e planos por país são ofertas separadas.

**O Mercado Pago não tem recorrência com split.** `preapproval` não aceita
`marketplace_fee`, e o Transparente com cartão salvo exige CVV a cada cobrança.
Assinatura aqui é acesso com prazo mais aviso de vencimento — não débito
automático. Foi o que motivou procurar outro gateway: ver `docs/adr/0001`.

**Quem cria a cobrança é o vendedor.** Não é escolha de arquitetura, é regra
fiscal: a plataforma não emite nota por outra empresa. A cobrança nasce na conta
dele, o líquido fica com ele, e o split leva só a comissão. Ver `docs/adr/0003`.

**A base da comissão é configurável, e o padrão é o BRUTO.** Cada degrau da
cadeia pode declarar a sua, e a base sai do **mesmo degrau que deu a taxa** —
`api::resolve_commission()` devolve taxa, base e origem juntas. Coluna
`commissionbase` nula = "herda a do site", que é diferente de escolher bruto.

Como cada gateway aplica: bruto vai como valor absoluto (`fixedValue` no Asaas,
`marketplace_fee` no MP); líquido vai como `percentualValue` no Asaas e **não é
possível no Mercado Pago**, onde a taxa só é conhecida depois. Com `net`
configurado, a venda pelo MP sai sobre o bruto e **grava `gross`**.

**Os termos aplicados são fotografados na venda** (`feepercent`, `feebase`,
`feesource` em `local_marketplace_sale` e nas tabelas dos gateways). O webhook lê
da linha, nunca resolve de novo: mudar a configuração não pode reescrever o
passado. Ver `docs/adr/0007`.

A taxa **não varia por meio de pagamento**: quem escolhe o gateway é o aluno, no
checkout. Nunca recalcule o valor no relatório — estorno parcial e split recusado
mudam o que o gateway devolveu.

**Baixa manual não prova split.** `receiveInCash` faz o split sair `CANCELLED`
com o valor certo na tela. Dinheiro que não passou pelo gateway não tem como ser
dividido.

**O split só ocorre entre contas do mesmo país.** A comissão cai na conta da
plataforma, e uma conta só guarda a moeda do próprio país. Não há câmbio no
caminho — por isso a oferta tem `country` em ISO, e a moeda é derivada dele.

**São três partes no split:** comprador, vendedor e a **aplicação**. Misturar
ambientes — aplicação de produção com vendedor de teste — é recusado com "uma das
partes é de teste". O `test_token` no OAuth resolve.

## Arquitetura em uma tela

```
Empresa (local_marketplace_company)
  ├── categoria de curso          → isolamento, contexto, tema
  ├── contas de pagamento         → UMA POR PAÍS (local_marketplace_account)
  ├── domínio próprio             → mapa Host→empresa lido pelo config.php
  └── ofertas (cada uma com country ISO)
        ├── direitos de acesso    → enrol + availability + block leem daqui
        └── vendas                → local_marketplace_sale, neutra de gateway
```

Sete plugins:

- `local_marketplace` — núcleo. Empresas, ofertas, direitos, vendas, relatórios,
  vitrine, telas de admin, `core_payment\service_provider`. **Não sabe o nome de
  gateway nenhum**: pergunta a cada um que moedas e países atende
- `paygw_mercadopago` — Checkout Pro com split. Todo HTTP passa por `mp_client`
- `paygw_asaas` — split em Pix, boleto e cartão. Credencial do vendedor cifrada,
  ambientes lado a lado, webhook autenticado
- `enrol_marketplace` — matrícula por diferença, a partir dos direitos
- `availability_marketplace` — libera seção mediante compra
- `block_marketplace` — assinaturas do aluno no Dashboard
- `mod_ldgvideo` — aula em vídeo por embed, a peça do plano Free. Guarda o
  endereço, nunca o arquivo; quem reconhece a plataforma é o `core_media_manager`

Detalhes de tabela e campo: `docs/dev/guia-desenvolvedor.md`.

## Ambiente

Worktrees de um bare repo em `/home/leodg/localhost/gitworktree-bare-moodle/`.
A worktree de repouso é `dev`; as de trabalho nascem e morrem com as features.
Use `moodev ls` para ver quais existem e qual está sendo servida — não presuma.

**Índice da documentação: `docs/README.md`.**

O comando do ambiente é o **`moodev`** (*Moodle Dev*): worktrees, devcontainer e
ferramental num só. Chamava-se `cf` até 04/09/2026, e `cf` segue como atalho.
Para levá-lo a outro fork do Moodle: `docs/dev/moodev-em-projeto-novo.md`.

**Cada worktree tem o próprio ambiente, e vários rodam ao mesmo tempo.** O
comando é o `moodev` (`.devcontainer/bin/moodev`): `moodev ls` mostra worktrees, offsets,
portas e status; `moodev new <nome>` cria worktree, ambiente, dados e stack, e
ramifica de `origin/dev` por padrão. Cada worktree recebe um offset, e dele saem
o nome do stack e as portas — offset 0 é o principal (`ldg-courses`,
8080/8443/3307/9004), offset 1 soma 10 a cada uma.
Guia completo em `docs/dev/guia-worktrees.md`.

**O código vem do `--from`, mas o banco vem do offset 0.** Se ele estiver numa
branch à frente da base, o banco nasce com plugin mais novo que o código e o
upgrade recusa (`cannotdowngrade`). O `moodev new` confere e para antes de criar
qualquer coisa: veja no `moodev ls` qual branch o offset 0 serve e passe no `--from`.

Não edite `.env` nem portas à mão: o `moodev` gera esses arquivos e o `moodev doctor`
reclama quando divergem do registro.

O `moodev` do `PATH` é um symlink encadeado que resolve para
`dev/.devcontainer/bin/moodev`. Ao editar o próprio `moodev` numa branch, chame pelo
caminho (`./.devcontainer/bin/moodev`) — `moodev` puro executa a versão de `dev`.

Moodle 5.2 usa layout `public/` — os plugins ficam em `public/local/…`,
`public/payment/gateway/…`, e o `config.php` fica na raiz, fora do webroot.

**Fluxo:** commit no branch de feature → PR para `dev` → merge dispara deploy
automático para a VPS. Não há PR `dev`→`main` no caminho normal.

**A ordem não é negociável, e existe para impedir commit órfão:** terminar tudo
→ verificar → commitar → *só então* abrir o PR → CI verde → merge. Abrir o PR
antes de terminar é como o commit órfão nasce, e já aconteceu seis vezes.
Detalhe em `docs/dev/fluxo-de-contribuicao.md`.

**ANTES DE ABRIR FEATURE NOVA, SINCRONIZE COM O UPSTREAM DO MOODLE.** Não é
sugestão: este projeto acompanha o Moodle em vez de forkar, e uma feature que
nasce de um `dev` atrasado vai encontrar o merge do upstream depois — com o
código dela no meio do caminho. Conflito de `upstream` resolvido dentro de uma
feature é conflito resolvido por quem não conhece a mudança do core.

```bash
git -C dev fetch -q upstream MOODLE_502_STABLE
git -C dev rev-list --count origin/dev..upstream/MOODLE_502_STABLE
```

Zero, siga. Mais que zero, traga antes:

```bash
cd MOODLE_502_STABLE && git pull
cd ../dev && git merge upstream/MOODLE_502_STABLE && git push origin dev
```

Só então `moodev new`. Procedimento completo e as armadilhas em
`docs/dev/estrutura-worktrees.md`, seção 5 — inclusive a de **não editar o
`.gitattributes` da raiz**, que é do upstream e conflita a cada sincronização.

Container local: `ldg-courses-moodle-1` (Apache + PHP 8.4) e `ldg-courses-db-1`
(MariaDB 11.4).

## Comandos que funcionam

Rodar como `-u 1000:33` — uid do host, grupo `www-data`. Sem isso o PHPUnit não
escreve no dataroot.

```bash
# Testes
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer ldg-courses-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite local_marketplace_testsuite

# phpcs — LEIA O TOTAL, não corte a saída. O CI roda com --max-warnings 0,
# então aviso também reprova. Saída vazia = limpo; use -p para ver o que ele varreu.
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle -p --report=summary <caminho>

# behat com navegador (cenários @javascript, e os que MEDEM a tela)
moodev up --full
docker exec -d -u 1000:33 ldg-courses-moodle-1 \
  sh -c 'cd /var/www/html/public && php -S 0.0.0.0:8000 >/tmp/behatweb.log 2>&1'
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --profile=chrome --tags "@mod_ldgvideo"

# CLI do marketplace, na VPS (o < /dev/null é obrigatório)
docker compose exec -T moodle \
  php /var/www/html/public/local/marketplace/cli/status.php < /dev/null
```

## Erros já cometidos aqui — não repita

**`tail -3` no phpcs esconde o relatório.** Reportei "zero violações" com 16
erros presentes; o CI reprovou. Sempre leia o total.

**Regex cego em comentários corrompeu o cabeçalho GPL de 74 arquivos.** Um padrão
que capitaliza `// texto` também pega a segunda linha de comentários
multi-linha. Corrija por arquivo e linha exatos.

**`cd` no Bash persiste entre chamadas.** Um `cd` numa etapa me fez concluir que
arquivos do core não existiam, e criar um diretório no lugar errado. Use caminho
absoluto ou confira o `pwd`.

**Backup dentro do diretório que o rsync sincroniza com `--delete` não é
backup.** O deploy seguinte apagou a cópia do `config.php` durante um incidente.

**Remoção e recriação em blocos separados abrem janela de indisponibilidade.**
Removi o `config.php`, o script morreu antes de recriar, e o instalador do Moodle
ficou exposto. Escrita de arquivo crítico: grave ao lado e mova.

**Automação cara para tarefa única.** Construí uma entrada de workflow para
substituir dois comandos manuais; custou commit, deploy, quinze minutos e o site
fora do ar. O usuário havia apontado isso antes.

**Strings de idioma têm ordem alfabética obrigatória.** Inserir por âncora quebra
o `phpcs`. Reordene o arquivo inteiro depois de acrescentar.

**Commits empurrados depois de o PR ser merjeado ficam órfãos.** Aconteceu
**seis vezes** — o erro mais repetido daqui. Não abra o PR com nada pendente, e
antes de qualquer push adicional confirme que ele ainda está aberto:
`gh pr view <n> --json state -q .state` tem que dizer `OPEN`.

## Armadilhas do Moodle nesta base

| Sintoma | Causa |
|---|---|
| `Section error` | A seção do gateway é `paymentgateway<nome>`, não `paygw_<nome>` |
| Asaas recusa a cobrança inteira | Conta do vendedor sem o domínio **da plataforma** cadastrado em Minha Conta, ou aluno sem CPF no perfil |
| Webhook do Asaas respondendo 401 | Token vazio ou divergente entre o painel e a config do Moodle |
| `No define call` | `requirejs.php` serve `amd/src` quando não há `.map`. **Não há transpilador**: o `src` precisa ser AMD de verdade |
| Botão exige dois cliques | `cachejs` desligado faz cada módulo AMD virar uma requisição |
| Upgrade quebra em `messages.php` | `MESSAGE_DEFAULT_LOGGEDIN` não existe no 5.2. Use `MESSAGE_DEFAULT_ENABLED` |
| Empresa "sem meio de pagamento" após vincular | `account::is_available()` exige o gateway **habilitado**, não só o token |
| Filtro `branch=5.2` da API do diretório engana | Ele vai pelo `requires` (mínima). Confira `$plugin->supported` no `version.php` |
| Vídeo some do quadro embutido do portal | `100vh` dentro da atividade. O `player.js` encolhe o quadro para **zero** antes de medir; use `aspect-ratio`, que deriva da largura |
| Endereço `/embed/` ou `/shorts/` "não é vídeo" | Os regex dos players do core cobrem o link da **barra de endereços**, não o do `src`. Canonicalize antes de chamar `can_embed_url()` |
| Behat `@javascript` morre em `localhost:4444` | Falta `--profile=chrome`, e o Selenium só sobe com `moodev up --full` |
| Mudança em papel não chega à produção | `db/install.php` só roda em instalação nova. Sem passo no `db/upgrade.php`, nada muda no que está no ar |
| `assign_capability()` não tira nada | Ele só acrescenta. Papel que já existe guarda as capabilities do desenho antigo — reconcilie, apagando o que saiu da lista |
| Editou `styles.css` de plugin e nada mudou | O `purge_caches` **não** invalida CSS de plugin. Suba o `version.php` |
| Docblock do mustache aparecendo na tela | Comentário `{{! }}` termina no **primeiro** `}}`. Não cite tag dentro dele |
| Grade declarada por cima de `.d-flex` é ignorada | Utilitário do Bootstrap é `!important`. Trabalhe com o flex, ou use `flex-basis` |
| Módulo AMD novo não roda, e sem erro | Com `cachejs` ligado o Moodle serve `amd/build/`. Rode `npx grunt amd` |
| `npx grunt` recusa dentro do container | O node de lá é v20 e o Moodle 5.2 exige v22. Rode no **host** |
| Cenário behat de celular passa dizendo o que não é | O Moodle **escala** o viewport. Use `without runtime scaling` |
| `'choices'` do persistent reprova campo anulável | A lista é conferida antes da validação customizada, e `in_array(null, [...])` é falso. Use `validate_<campo>()` |
| `Could not open input file` num script de CLI | CLI do **core** fica na raiz (`admin/cli/`); de **plugin**, sob `public/` |
| Duas navbars e dois rodapes na home | O plugin desenha o proprio cromo E o layout do tema desenha o dele. Nenhum teste de servidor pega: conte os elementos |
| Marca vira palavra numa pagina so | `$PAGE->get_renderer('<plugin>')` nao tem `get_logo`; so o renderer do TEMA tem. Caia para o `$OUTPUT` |
| Cenario behat sobre a home cai no login | O site do behat nasce sem Dashboard, e o `index.php` do core redireciona anonimo. Ponha `enablemyhome`, `forcelogin` e `theme` no cenario |
| "A regra de CSS nao pegou" | A revisao muda a cada purge e a aba reusa a anterior. Confira o `styles.php/...` que a pagina carregou de verdade |
| Metade branca na tela de entrar | O `#page` do layout de login sai `#fff` do Boost, e vence tudo dentro dele. Pintar a coluna nao resolve |
| A raiz redireciona anonimo para o login, e mexer no `forcelogin` nao resolve | `enablemyhome` desligado. O `index.php:79` do core manda o anonimo para o login porque o destino calculado esta desabilitado - o comentario do proprio core diz "forcelogin may be off". Derruba canonica, hreflang e sitemap juntos |
| Titulo da pagina ignora o `set_title()` do plugin | O `head.mustache` resolve `page_title` ANTES de `standard_head_html`. O hook de `<head>` e tarde: use o `before_http_headers` |
| Passo de upgrade "roda" e nao cria a coluna | Nome de tabela errado mais a guarda `table_exists()` da silencio. O nome sai da constante `TABLE` da classe, **nao** do nome da classe |
| `table_exists(...) &&` antes de `add_field` | **Proibido** em tabela do proprio plugin: ela existe, e a guarda so faz nome errado passar calado. Sem ela o engano estoura. O `db_schema_test` cobra isso nos dez plugins |
| Campo gravado somindo sem erro | `insert_record` do Moodle **descarta** campo que a tabela nao tem. E perda silenciosa, nao excecao |
| `admin/cli/upgrade.php` diz "no upgrade needed" com passo pendente | Cache `allversionshash`. `unset_config('allversionshash')` e rode de novo |

## Estado atual

**Funciona em produção:** compra completa validada — preferência, checkout,
webhook, matrícula. **395 testes** (123 no núcleo, 48 no Asaas, 47 no
`format_ldg`, **37 no `mod_ldgvideo`**, **82 no `local_partners`**, **24 no MP**,
e 34 em `enrol_marketplace`, `availability_marketplace`, `block_marketplace` e
`theme_ldg`). phpcs limpo, e o CI valida **um job por plugin, em paralelo**.

O behat cobre **51 cenários** de quatro plugins, e **vinte e um deles medem a
tela** — três no vídeo, dezessete na captação de parceiros e um no tema. Os do
vídeo são a única prova de que o `aspect-ratio` do `mod_ldgvideo` continua
vencendo o `width` fixo que o `core_media_manager` escreve no iframe. Os quatro
do `paygw_mercadopago` cobrem a configuração e a trava que impede habilitar o
gateway sem token.

**A captação de parceiros ganhou cara nova** em 10/09/2026. O estilo da landing
e do cadastro saiu do `theme_ldg` e virou `local/partners/styles.css`: o
`theme_config` injeta `styles.css` de plugin na CSS compilada de **qualquer**
tema, e as duas páginas passaram a renderizar igual sob `boost`, `moove` e `ldg`
— medido, com o mesmo número nos três. É exceção deliberada à regra de que o
tema pinta; ela continua valendo para o `format_ldg`.

Junto vieram três campos na candidatura (país, faixa de alunos e o **momento**
do aceite dos termos), um alternador claro/escuro que o Boost não tem, e SEO com
JSON-LD cujo preço sai do banco. Dezessete cenários behat **medem a tela**, e o
roteiro de conferência está em `docs/data-validation/local-partners-layout.md`.

Sete defeitos daquela rodada só apareceram no navegador, e nenhum quebrou um
teste — inclusive o docblock do mustache virando parágrafo na página pública.
O método está em `docs/dev/padrao-de-implementacao.md`.

**A segunda rodada, no mesmo dia, veio de conferência tela a tela**, e achou mais
quatro do mesmo tipo: a raiz do domínio mostrando barra e rodapé em duplicata,
porque o layout de frontpage do tema desenhava o cromo dele por cima do da
landing; a marca virando palavra só no cadastro, porque ali quem renderiza é o
renderer do PLUGIN, que não tem `get_logo`; o "Apply" repetido como âncora e como
botão na mesma barra; e a tela de entrar com metade branca, vinda do `#page` do
Boost.

Dela saíram três decisões que valem daqui para frente: **a home e a landing são a
mesma página e não podem divergir** — quem serve a landing na raiz não monta
cromo nenhum; **rodapé e barras de controle ficam escuros nos dois modos**, como
a navbar; e **quem já entrou vê SAIR onde o anônimo vê ENTRAR**, com `sesskey` no
endereço.

**O plano Free ganhou a peça dele** em 04/09/2026: o `mod_ldgvideo` e a
separação dos papéis de empresa. A fronteira "vídeo fica fora da plataforma"
passou a ter teste, e a lista de proibição saiu de 2 para 24 capabilities.

**O split foi provado** no sandbox do Asaas, com duas contas distintas. Em
2026-08-27, R$ 100 brutos → R$ 97,52 líquidos → 25% = R$ 24,38 na carteira da
plataforma. Em **2026-09-01**, com a base de cálculo já configurável, as duas
bases na mesma cobrança: **bruto R$ 25,00** (`fixedValue`) e **líquido R$ 24,38**
(`percentualValue`), ambas `AWAITING_CREDIT` e conferidas pela lista de splits
recebidos **da conta da plataforma**.

Falta vê-lo chegar a `DONE` com o saldo se movendo — cartão liquida em D+30 no
sandbox. Roteiro repetível em `docs/data-validation/asaas-sandbox.md`.

**O split do Mercado Pago foi provado** em 08/09/2026, com duas contas distintas
e dinheiro real. Pagamento `178004552586`, Pix: R$ 5,00 brutos − R$ 0,05 de taxa
do MP − R$ 1,25 de `application_fee` = R$ 3,70 para o vendedor. Os R$ 1,25
apareceram no extrato da DG como **dinheiro a liberar** — conferido nos dois
lados, que é o que separa prova de impressão. Foi a rodada que confirmou a
**ordem de dedução**: taxa do gateway primeiro, comissão do que sobra. Roteiro
em `docs/data-validation/mercadopago-split.md`.

**O vendedor NAO precisa ser pessoa jurídica.** A conta que vendeu naquela
rodada é pessoa física, e só se descobriu depois: `/users/me` devolve
`identification.type` vazio e sem a tag `business`. Quem precisa de CNPJ é a
**plataforma**, dona da aplicação, que recebe a comissão. Ver `docs/adr/0010`.

Daí uma regra de método: **confira o tipo da conta pela API antes de desenhar a
rodada**. O rótulo do cadastro dizia "empresa"; a API disse outra coisa, e o
resultado teria sido lido ao contrário se a prova tivesse falhado.

**O sandbox do Checkout Pro não serve para isso.** Com `wallet_purchase` o
checkout entra em loop no login de carteira; sem ele, devolve erro. Nos dois
casos `payments/search` volta vazio. Prova de split no MP é com conta real.

**A compra pelo Moodle com comissão maior que zero também foi provada**, no mesmo
dia e pela vitrine, com o webhook chegando sozinho: pagamento `177042328687`,
R$ 5,00, `application_fee` R$ 1,25. A linha gravou `feesource = company` — a
comissão veio da empresa pela `commission_terms_for()`, e não do padrão de
fábrica. Direito de acesso ativo por 30 dias exatos e matrícula pelo
`enrol_marketplace`.

**O ciclo da assinatura foi provado inteiro** em 08/09/2026, com duas compras
reais da mesma oferta: a renovação **soma** ao vencimento atual (11/09 → 11/10,
`cycles = 2`, um único direito), cada ciclo leva o seu `application_fee` de
R$ 1,25, o aviso sai uma vez só, e o vencimento suspende **por diferença** — três
das quatro matrículas, porque a quarta vinha de outro direito ainda vigente.
Matrícula é suspensa, nunca apagada.

Para exercitar o ciclo não se espera mês nenhum: move-se o `timeend` do direito e
roda-se a tarefa. Roteiro em `docs/data-validation/mercadopago-split.md`.

**São dois avisos, e a matrícula tem prazo** desde 08/09/2026. O
`notify_expiring` dispara em `NOTICE_MILESTONES = [5, 1]` dias: o primeiro
lembra, o último diz que vai bloquear, com texto próprio. A deduplicação guarda
`vencimento:marco` — guardar só o vencimento faria o primeiro aviso marcar a
linha e o último nunca sair, em silêncio.

O `sync_user()` passou a escrever o `timeend` do direito **na matrícula**. Antes,
o acesso dependia inteiramente da tarefa horária: com o cron parado, o aluno
entrava indefinidamente, e o sintoma — aluno acessando de graça — é o tipo de
falha de que ninguém reclama. Não é segunda fonte da verdade: é projeção do
direito, escrita sempre pelo mesmo lugar, e o sync por diferença conserta
qualquer divergência. Dois direitos sobre o mesmo curso valem o mais generoso, e
vitalício ganha de qualquer data.

**Continua sem prova:** o vendedor pessoa jurídica no Mercado Pago, que é o caso
convencional e nunca foi exercitado.

**Assinatura recorrente existe no Asaas** desde 09/09/2026, e **não existe no
Mercado Pago**. `POST /subscriptions` aceita o `split`, guarda com
`status: ACTIVE`, e ele chega na cobrança de cada ciclo — o oposto do
`preapproval` do MP, que engole o campo (ver `docs/adr/0001`).

**Há débito automático, e quem guarda o cartão é o GATEWAY.** O Moodle não
guarda dado de cartão em lugar nenhum, e não vai guardar. Medido no sandbox em
09/09/2026, no fluxo real do plugin: a assinatura nasce sem cartão, o aluno paga
a primeira fatura com cartão na página do Asaas, e **o Asaas passa a guardar
aquele cartão** — a assinatura sai de "nenhum" para "final 8829", e as cobranças
seguintes já nascem com o cartão anexado.

Corrigi isto duas vezes no mesmo dia, e vale registrar o erro: primeiro chamei de
débito automático sem medir; depois medi só "assinatura criada COM cartão via
API", que não é o nosso fluxo, e conclui que não havia. O que faltava era medir o
caminho de verdade — o aluno pagando a fatura.

**Depende do que o aluno usa para pagar.** Pix e boleto não deixam instrumento
guardado: cada ciclo gera um documento que alguém precisa pagar, e não há cartão
para expirar. Cartão deixa, e traz as duas coisas juntas — cobrança sozinha e
cartão que vence.

Não existe Pix automático nesta API — `PIX_AUTOMATIC` e afins são recusados como
valor inválido, e `DEBIT_CARD`/`TRANSFER` são recusados para assinatura com
mensagem explícita. Daí uma consequência que evita teste inútil: **não há "Pix
recusado" a testar**, porque recusa é coisa de cobrança automática e só o cartão
a tem. Pix não pago apenas vence.

**Cartão expirado é recusado na hora da compra**, com mensagem clara
(`invalid_creditCard`), a cobrança fica `PENDING` e nada é guardado. O caso que
machuca é o outro: comprar com cartão que vence no mês seguinte. O Asaas guarda,
e o ciclo seguinte falha - o aluno cai no caminho manual, recebe os avisos de
vencimento e paga a fatura com outro cartão, o que **atualiza o cartão guardado**.

Trocar o cartão sem esperar a falha é `PUT /subscriptions/{id}/creditCard`. O
`PUT` comum aceita e ignora em silêncio. O `subscriptionid` não muda, então
`cycles` e histórico sobrevivem.

Como funciona: oferta `recurring` faz o gateway criar assinatura em vez de
cobrança avulsa. Quem decide é o marketplace, por `api::recurrence_for()` — o
gateway não sabe o que é uma oferta. **Cada ciclo é uma linha própria** em
`paygw_asaas`, ligada pelo `subscriptionid`: o ciclo 1 nasce no checkout, e do 2
em diante o Asaas cria a cobrança sozinho e o webhook a adota copiando o
contexto e os termos da linha anterior.

Cancelar no Moodle **para de cobrar no gateway**, via
`api::stop_recurring_billing()` — marcar `norenew` e deixar o gateway cobrando
seria tirar dinheiro de quem pediu para sair. O núcleo continua sem saber nome de
gateway: pergunta a cada um habilitado.

**O ciclo foi provado inteiro em 09/09/2026**, no sandbox, sem esperar semana
nenhuma: o Asaas gera quatro cobrancas de uma vez, entao paga-se uma por vez.
Assinatura criada, ciclo 1 pago pelo webhook, avisos nos dois marcos, acesso
cortado por falta de pagamento e devolvido ao pagar a atrasada. Roteiro em
`docs/data-validation/asaas-assinatura.md`.

A prova achou um bug do NUCLEO, e nao do gateway: pagar a atrasada criava um
segundo direito em vez de reviver o primeiro, porque o `deliver_order()` so
procurava direito ativo e quem vence fica `expired`. O `cycles` voltava a 1 - e
com ele o `maxcycles` nunca terminaria. Corrigido para procurar ativo OU vencido;
`cancelled` fica de fora, porque revogar e decisao de negocio.

Duas armadilhas registradas: `percentualValue` incide sobre o **líquido**, então
comissão sobre o bruto vira `fixedValue` e **congela** o valor de todos os ciclos
— mudar o preço da oferta exige recriar a assinatura. E o intervalo em dias vira
ciclo nomeado, tradução lossy: **empate vai para o ciclo maior**, porque errar
cobrando mais cedo tira do aluno dinheiro que ele não combinou.

**Os dois gateways têm reconciliação** desde 08/09/2026, de hora em hora. A do
Mercado Pago consulta por `external_reference`, e não por id de pagamento: lá a
cobrança nasce com id, aqui nasce a *preferência*, e o pagamento só existe
quando o aluno paga.

**Fase 3** tem a fundação no ar; falta apontar um domínio real.
**Fase 5** está bloqueada por decisão de negócio do usuário.

Detalhe completo: `docs/architecture/estado-e-proximas-fases.md`.

## Como o usuário trabalha

Prefere entender o porquê antes de aceitar a solução, e questiona premissas — em
mais de uma ocasião a objeção dele melhorou o desenho. Vale apresentar o
trade-off em vez de só a conclusão.

Não gosta de automação que exista só para evitar um comando manual, nem de
solução que dependa de a configuração estar certa para ser segura.

Escreve em português; o código e os comentários também, sem acentos. As strings
de idioma cobrem `en`, `pt_br` e `es`.
