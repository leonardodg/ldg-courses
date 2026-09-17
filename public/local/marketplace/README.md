# local_marketplace

O núcleo da plataforma. Empresas, ofertas, direitos de acesso, vendas,
relatórios, vitrine e a resolução da comissão.

**Este plugin não sabe o nome de nenhum gateway.** Ele pergunta a cada gateway
instalado que moedas e países atende, e é isso que permite acrescentar um
terceiro sem tocar no núcleo. Se você se pegar escrevendo `if ($gateway ===
'asaas')` aqui dentro, o desenho foi violado.

## Para que serve

Uma plataforma onde qualquer pessoa publica curso, gratuito ou pago, e a
plataforma retém uma comissão sobre as vendas.

```
Empresa (local_marketplace_company)
  ├── categoria de curso          → isolamento, contexto, tema
  ├── contas de pagamento         → UMA POR PAÍS
  ├── domínio próprio             → opcional
  └── ofertas (cada uma com country ISO)
        ├── direitos de acesso    → enrol + availability + block leem daqui
        └── vendas                → neutras de gateway
```

**Empresa é uma categoria de cursos.** Não é escolha estética: o `core_payment`
escopa conta de pagamento por **contexto**, e a categoria é o que dá contexto,
papel e tema à empresa.

**O direito de acesso é a fonte única da verdade.** Matrícula e liberação de
seção leem `local_marketplace_entitlement`. Ninguém lê a venda para decidir
acesso — venda e acesso são coisas diferentes, e confundi-las quebra estorno,
prazo e renovação de uma vez só.

## Dependências

Nenhuma além do Moodle 5.2 (`requires 2026042000`). É o plugin do qual os
outros dependem, e não o contrário.

Precisa de **pelo menos um gateway** instalado para vender: `paygw_asaas` ou
`paygw_mercadopago`. Sem gateway ele continua funcionando para curso gratuito.

## O que precisa configurar

`/admin/settings.php?section=local_marketplace_settings`

| Configuração | Padrão | O que faz |
|---|---|---|
| `defaultfeepercent` | `25` | Comissão do site, último degrau da cadeia |
| `commissionbase` | `gross` | Base de cálculo: sobre o bruto ou sobre o líquido |
| `defaultcountry` | — | País da primeira conta de uma empresa nova |

### A cadeia da comissão

Resolvida por `api::resolve_commission()`, do mais específico ao mais genérico:

```
1. course_policy    (só em oferta de curso único)
2. company          (comissão negociada com esta empresa)
3. plan             (plano contratado pela empresa)
4. padrão do site
```

**A base sai do mesmo degrau que deu a taxa.** Resolver as duas em cadeias
independentes produziria "taxa do plano com base do site" — combinação que
nenhum contrato tem, e que o parceiro não conseguiria conferir.

Coluna `commissionbase` **nula** significa "este contrato não define a base,
herda a do site". É diferente de escolher `gross`: gravar o valor congelaria a
base ali para sempre, e depois não haveria como distinguir quem escolheu de quem
herdou. Vale a mesma lógica para `commissionpct` nula na empresa — "não
negociamos nada" e "negociamos zero" precisam ser distinguíveis.

Detalhe em [ADR-0007](../../../docs/adr/0007-comissao-sobre-o-bruto.md).

### Capacidades

| Capacidade | Para quem |
|---|---|
| `local/marketplace:manageall` | administrador da plataforma |
| `local/marketplace:createcompany` | quem provisiona empresa |
| `local/marketplace:managecompany` | dono da empresa, no contexto dela |
| `local/marketplace:managepayment` | quem vincula conta de recebimento |
| `local/marketplace:publishcourse` | quem publica oferta |
| `local/marketplace:viewreport` | quem vê o relatório da empresa |

### Os dois papéis da empresa

São criados na instalação e no upgrade, por `\local_marketplace\roles::ensure()`,
e atribuídos no **contexto da categoria** — é o que impede a empresa A de
enxergar a B. Qual deles a pessoa recebe sai do `memberrole` do vínculo.

| | `marketplacemanager` (`owner`) | `marketplaceseller` (`seller`) |
|---|---|---|
| Criar e editar curso, publicar oferta | sim | sim |
| Conta de pagamento, dados da empresa, relatório | sim | **não** |
| Atribuir papel, configurar matrícula | sim | **não** |
| Gerir vendas e assinaturas | sim | **não** |
| **Estornar venda** | **não** | **não** |
| Colocar arquivo no site | **não** | **não** |

### As permissões sobre dinheiro

| Capability | Quem tem por padrão | O que permite |
|---|---|---|
| `viewreport` | gerente | **Olhar**: relatório, lista de assinantes, números |
| `managesales` | gerente | **Agir**: cancelar a assinatura de um aluno, e reenviar a fatura do ciclo |
| `refundsale` | **ninguém** | Estornar: devolve o dinheiro e revoga o acesso |

`viewreport` e `managesales` são separadas porque olhar e agir são coisas
diferentes. Ver quem assinou é leitura; cancelar a assinatura de outra pessoa
mexe no dinheiro e no acesso dela.

O aluno cancela a própria assinatura pela tela dele. O gerente cancela a de
qualquer aluno da empresa, e a checagem é no contexto da **categoria** — quem
gere uma empresa não gere a assinatura de outra. Ele chega pelo botão na aba de
assinantes do relatório, que só aparece para assinatura vigente que ainda cobra:
cancelar o que já acabou não pararia cobrança nenhuma.

### A fatura do ciclo

O aviso de vencimento levava o aluno à **vitrine**. Numa assinatura isso é pedir
que ele compre de novo algo que já está cobrado — o gateway já gerou a cobrança
do ciclo, e com boleto ela já nasce com linha digitável.

Agora o aviso leva à **fatura**, e traz a linha digitável quando existe. A mesma
fatura aparece em *Minhas assinaturas*, com botão de pagar.

O gerente reenvia a fatura pela aba de assinantes — *"não recebi o boleto"* é o
motivo mais comum de uma mensalidade não ser paga, e até aqui a única saída dele
era copiar o link à mão, se soubesse onde achar.

O reenvio manda **a mesma mensagem** que o cron manda: dois textos separados
divergiriam na primeira edição feita só num deles. E ele **não marca a
preferência de aviso enviado** — se marcasse, reenviar hoje calaria o aviso
automático de amanhã.

Ausência de fatura não é erro: sem assinatura, ou com o gateway fora do ar, tudo
volta ao caminho antigo. Nem a tela nem o e-mail podem quebrar porque o gateway
piscou.

**`refundsale` não vai para papel nenhum**, nem para o gerente. O estorno
devolve dinheiro de verdade, revoga acesso e não tem desfazer — fica com o
administrador da plataforma, que a concede por empresa quando confiar em quem
vai usar. O caminho normal é o aluno procurar o responsável, e ele decidir.

### O estorno, e por que ele é diferente do cancelamento

| | Cancelar | Estornar |
|---|---|---|
| Quem faz | aluno ou gerente (`managesales`) | só quem tem `refundsale` |
| Dinheiro | fica | volta, com a comissão junto |
| Acesso | vale até o fim do ciclo pago | **cai na hora** |
| Assinatura | para de cobrar | para de cobrar **e** devolve |

**Estorno é sempre total.** Medido no sandbox em 09/09/2026: pedir estorno
parcial devolveu sucesso, deixou a cobrança em `CONFIRMED` e manteve o split
vivo com a comissão cheia — a plataforma ficaria com os 25% de uma venda
parcialmente devolvida.

**Numa assinatura, só o primeiro ciclo.** Estornar um ciclo do meio devolve o
dinheiro daquele mês e **não para a assinatura**: as cobranças futuras seguem
pendentes, e o aluno continua sendo cobrado depois de reembolsado. Do segundo
ciclo em diante o caminho é cancelar — ele usou os meses anteriores.

E quando o estorno acontece no primeiro ciclo, **o cancelamento vai junto, na
mesma operação**. O gateway não cancela sozinho, e deixar isso a cargo de quem
clica seria confiar em memória humana para não continuar cobrando alguém já
reembolsado.

A ordem é gateway primeiro, direito depois: revogar antes deixaria o aluno sem
curso e sem reembolso se a chamada externa falhasse.

**A venda fica no histórico.** Apagar a linha esconderia o dinheiro que entrou e
saiu, e o relatório precisa dos dois lados.

Um `PAYMENT_REFUNDED` disparado no painel do gateway **não revoga nada aqui** —
ninguém da plataforma decidiu. O que revoga é o estorno feito por esta tela, por
quem tem a capability.

Há teste fixando as três coisas: `managesales` no gerente e não no editor,
`refundsale` em nenhum dos dois, e as duas declaradas em `db/access.php` — papel
que aponta para capability inexistente não faz o Moodle reclamar, e a permissão
simplesmente nunca vale.

Até 04/09/2026 havia um papel só, e quem apenas montava curso também alcançava a
credencial financeira da empresa.

### Por que nenhum dos dois envia arquivo

**É a fronteira que sustenta a margem do plano Free**, e não uma preferência.
O plano existe para custar zero de banda: o vídeo é embed de serviço externo.
Se o professor conseguisse subir um `.mp4`, a plataforma passaria a servir vídeo
de graça — ver a [ADR-0009](../../../docs/adr/0009-papeis-de-empresa-sem-upload.md).

A regra **não é uma capability própria**: é a ausência das que colocam arquivo
no `moodledata`. A lista está em `roles::PROHIBIT`, cobre todo repositório que
copia arquivo para dentro, e usa `CAP_PROHIBIT` — o `CAP_PREVENT` não serviria,
porque o papel de usuário autenticado **permite** `repository/upload:view`, e o
`ALLOW` vence o `PREVENT`.

Três consequências que aparecem no uso:

- a **imagem de capa** vem de URL externa, e não do seletor de arquivos;
- no portal do aluno, **"Material de apoio" vale só para `mod_url`** — `mod_resource` e `mod_folder` podem ser criados e ficam vazios;
- capability acrescentada à mão ao papel **some no upgrade seguinte**: o `ensure()` reconcilia.

O escopo é a **categoria**, e não o site: a proibição vale onde o curso é
montado, e não no perfil pessoal de quem monta. O vendedor ainda pode subir
arquivo nos próprios *Arquivos privados*, e aquilo não chega a aluno nenhum.

O `cli/status.php` relata quando um repositório habilitado escapa da lista.

## Telas

| URL | O que é |
|---|---|
| `/local/marketplace/admin/companies.php` | empresas, com a comissão efetiva e de onde ela veio |
| `/local/marketplace/admin/plans.php` | planos comerciais e as faixas de resolução |
| `/local/marketplace/report.php?company=<shortname>` | vendas, cursos, alunos e assinaturas |
| `/local/marketplace/company.php?company=<shortname>` | painel do gerente: meio de pagamento, **plano e assinatura**, ofertas |

O relatório é filtrado por `company` **shortname**, não por id.

## A assinatura SaaS: a empresa pagando a PLATAFORMA

Desde 17/09/2026 existe uma segunda direção de cobrança, na mesma
infraestrutura: além do aluno pagando a empresa por um curso
(`paymentarea = 'offer'`), a empresa parceira paga a **plataforma** pelo
plano comercial que contratou (`paymentarea = 'plan'`, `itemid` =
`companyid`, nunca `offerid`). `service_provider`, e as três funções
genéricas de `api.php` (`recurrence_for`, `commission_terms_for`,
`record_sale`), recebem esse `$paymentarea` justamente para não confundir
as duas — sem isso, um `companyid` que coincidisse com um `offerid` real
herdaria a comissão/recorrência de uma oferta sem relação nenhuma.
`record_sale()` nunca grava nada para `'plan'`: não há split, a plataforma
fica com o valor inteiro.

Quem recebe é uma `core_payment\account` comum, no contexto do **site**, sem
empresa dona — o vínculo empresa↔conta vive em
`local_marketplace_company_account`, e essa conta simplesmente não tem
linha lá. `api::get_or_create_platform_account(string $country)` cria (ou
devolve, se já existir) essa conta, e `api::is_platform_account()` é o que
permite ao Asaas e ao Pagar.me vincular a carteira/recebedor da própria
plataforma sem cair na guarda `errorsamewallet`/`errorsamerecipient` deles
(escrita para a venda de curso, onde vendedor e plataforma têm que ser
partes diferentes).

Ela nasce sem gateway vinculado, no primeiro checkout — para não deixar o
modal de pagamento vazio para o primeiro gerente que clicar, rode antes:

```bash
docker exec -u 1000:33 -w /var/www/html/public ldg-courses-moodle-1 \
  php local/marketplace/cli/platform_account.php --country=BR
```

O botão de pagar fica na seção **Plano**, dentro de `company.php` — sem
tela nova. `company::planexpiry` é a data de vencimento (nulo = nunca
pagou); "inadimplente" é derivado (`planexpiry < time()`), do mesmo jeito
que `entitlement::timeend` já fazia para o aluno.

**O Pagar.me nunca vai cobrar um plano de verdade**: toda assinatura SaaS é
recorrente, e esse gateway já recusa oferta recorrente na porta
(`supports_recurring()`) — vincular a conta da plataforma lá serve só para
conferir a guarda, não para cobrar.

Roteiro de prova com dinheiro real, incluindo o CLI e as consultas de
conferência no banco:
[`docs/data-validation/assinatura-saas-plano-empresa.md`](../../../docs/data-validation/assinatura-saas-plano-empresa.md).

## Armadilhas

**Não recalcule a comissão no relatório.** Guarde o que o gateway devolveu.
Estorno parcial e split recusado mudam o valor depois da criação, e um relatório
que discorda do extrato é pior que relatório nenhum. Por isso `feepercent`,
`feebase` e `feesource` são fotografados em `local_marketplace_sale`: mudar a
configuração não pode reescrever o passado.

**Dinheiro é `TYPE="number"` com `DECIMALS`**, nunca float, no XMLDB.

**`get_payable()` do core não recebe o usuário.** Valor, moeda e conta são função
pura do `itemid` — por isso o país vive na oferta, e "o mesmo curso em BRL para
um aluno e ARS para outro" é impossível por construção. Plano em outro país é
outra oferta.

**O split só ocorre entre contas do mesmo país.** Uma conta guarda só a moeda do
próprio país, e não há câmbio no caminho.

**`allowcategorythemes`** precisa estar ligado para o tema por empresa funcionar.
O `db/install.php` garante; num ambiente que veio de antes, confira.

## Testes

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite local_marketplace_testsuite
```

162 testes. Os que mais importam: `commission_test.php` (a cadeia e a base),
`sale_test.php` (a foto dos termos resistindo a mudança de configuração) e
`plan_reconciliation_test.php` (a ordem arquivar-antes-de-semear na migração
dos planos antigos).
