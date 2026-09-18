# block_marketplace

As assinaturas do aluno no Dashboard — e, para quem administra uma empresa
parceira, o checklist do que falta para ela ficar pronta.

## Para que serve

**Visão do aluno.** Existe por causa de uma limitação real: **não há débito
automático** neste projeto — o Mercado Pago não faz recorrência com split, e o
Asaas ainda não foi habilitado para isso. Então o aluno precisa **agir** para
continuar assinando.

O e-mail de aviso chega uma vez e some na caixa de entrada. O bloco fica.

**Visão do dono da empresa** (desde 18/09/2026). Uma empresa nasce já
aprovada (`local_partners`), mas ainda pode faltar vincular uma conta de
pagamento com gateway habilitado, ou escolher um plano. Enquanto isso, quem
tem a capability `local/marketplace:managecompany` naquela empresa vê, no
Dashboard, um checklist com o que falta em vez do widget de assinatura — e o
widget volta assim que a empresa fica completa. Um vendedor (sem essa
capability) nunca vê o checklist, e o checklist nunca aparece dentro de
página de curso: as duas visões não se misturam.

## Dependências

| Depende de | Por quê |
|---|---|
| `local_marketplace` (qualquer versão) | lê os direitos de acesso do aluno, o estado da empresa e o plano |

## O que precisa configurar

**Nada.** Adicione o bloco ao Dashboard — para todos os usuários de uma vez em
`/my/indexsys.php`. Não há `settings.php` neste plugin.

## Etapas de instalação / upgrade

Instalação normal de plugin (copiar, `admin/cli/upgrade.php` ou tela de
administração). **Não há migração de banco nesta mudança** — o checklist é
derivado dos campos que `local_marketplace` já tem
(`company.planid`, `company.cnpj`, contas em `local_marketplace_account`);
não existe tabela nova nem `db/upgrade.php` para rodar. Um bump de versão
comum já é suficiente.

## O que ele mostra, e o que não mostra

Para o aluno, mostra **só o que exige atenção ou decisão**: assinatura
vigente com a data de vencimento, e o que está perto de vencer ou já venceu.

**O histórico de pagamentos não fica aqui**, de propósito. Numa barra lateral,
uma lista que cresce a cada mês empurraria para baixo justamente o que precisa
ser visto. O histórico tem página própria.

Para o dono de empresa incompleta, mostra as duas etapas obrigatórias (conta
de pagamento, plano) e a % de progresso — documento (CNPJ/CPF) é informativo,
nunca bloqueia (empresa parceira não precisa ser pessoa jurídica). O link do
rodapé leva para `local/marketplace/company.php`, onde essas etapas já se
resolvem — este bloco não tem página própria, só aponta para o que já existe.

## Arquitetura

`classes/onboarding.php` é lógica pura (sem `$USER`/`$PAGE`/I-O de página):
`onboarding::step_state(company)` deriva o estado de cada etapa,
`onboarding::progress(company)` agrega num percentual. `block_marketplace.php`
despacha entre as duas visões em `get_content()`, sem tocar na classe
`onboarding` para decidir contexto (Dashboard vs. curso) ou capability — essa
lógica fica no próprio bloco.

## Armadilha

**Assinatura aqui é acesso com prazo**, e não cobrança recorrente. Um aluno cujo
prazo venceu perde o acesso e precisa comprar de novo — não há tentativa
automática de cobrança, e o bloco é o principal lugar onde ele descobre isso a
tempo.

**Conta de pagamento vinculada não é conta disponível.**
`core_payment\account::is_available()` exige o gateway **habilitado no site**
(`\core\plugininfo\paygw::get_enabled_plugins()`), separado do `enabled` da
linha `account_gateway` — uma conta pode estar vinculada e mesmo assim a etapa
continuar pendente. Já documentado no `CLAUDE.md` da raiz, e é a razão de
`onboarding::step_state()` checar `is_available()` e não só a existência do
vínculo.

## Testes

`tests/onboarding_test.php` (7 testes) cobre a derivação pura do checklist —
etapa por etapa, incluindo o caso do gateway vinculado mas sem plugin
habilitado. `tests/content_test.php` (13 testes) cobre o dispatcher: aluno,
dono de empresa incompleta/completa, dono que também é aluno em outra oferta,
vendedor sem capability, e o contexto de curso. Nenhum mock de banco — todos
os testes criam empresa/oferta/direito/plano/conta reais via persistent.
