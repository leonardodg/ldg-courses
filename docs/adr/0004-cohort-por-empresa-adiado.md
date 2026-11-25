# ADR-0004 — Cohort por empresa: adiado, e derivado quando vier

**Situação:** Aceita · **Data:** 2026-08-27

## Contexto

Surgiu a necessidade de **separar os alunos por empresa nos relatórios**, e com
ela a proposta de usar [cohorts](https://docs.moodle.org/502/en/Cohorts), que é a
estrutura que o Moodle oferece para nomear um conjunto de usuários.

Ao examinar, a necessidade declarada já estava resolvida no dado:
`local_marketplace_entitlement.companyid` existe e é desnormalizado de propósito.
O que faltava era **a tela** — o relatório tinha transações, cursos e assinaturas,
e nenhuma aba de alunos.

Cohort agregaria outra coisa: um objeto Moodle de primeira classe, consumível de
graça por `enrol_cohort`, mensagem em massa, `configurable_reports` (já instalado)
e criação de grupos.

## Decisão

**Não fazer agora.** Entregamos a aba "Alunos" no relatório, que sai do direito de
acesso e responde a pergunta com duas consultas.

Quando aparecer um consumidor concreto — assinatura corporativa, mensagem em
massa, relatório de terceiro que só fale cohort — o cohort entra como **projeção
derivada**:

- escrito **a partir** dos direitos de acesso, por hook mais tarefa de
  reconciliação
- no contexto da **categoria da empresa** (`CONTEXT_COURSECAT`); cohort de sistema
  vaza entre inquilinos
- **nunca lido para decidir acesso**

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Cohort agora, como estrutura principal | Cria segunda fonte da verdade sobre pertencimento. Este projeto já matou uma tabela por isso — `local_marketplace_mpaccount`, removida no upgrade `2026082404`, guardava o token do MP em paralelo ao `core_payment` |
| Cohort agora, só para o relatório | Não é o que falta. A consulta sai dos direitos de um jeito ou de outro; o cohort seria uma cópia a mais para manter sincronizada |
| Cohort + `enrol_cohort` para matricular | Brigaria com o `enrol_marketplace`, que matricula **por diferença**: calcula o conjunto de cursos dos direitos vigentes e suspende o resto. Dois plugins mexendo nos mesmos usuários dos mesmos cursos produz "aluno some do curso às vezes" |
| Nunca usar cohort | Fecha a porta para integrações que só falam cohort. A decisão é de **momento**, não de princípio |

## Consequências

**Fica mais fácil:** nenhuma estrutura nova para manter sincronizada; a resposta
sobre quem é aluno de quem tem um único dono.

**Fica mais difícil:** quem quiser mandar mensagem para "todos os alunos da
empresa X" hoje não tem um alvo pronto — precisa de uma consulta.

**O que a decisão evita** e vale nomear: cohort é binário, direito de acesso
vence. "Aluno da empresa X" e "aluno com acesso vigente na empresa X" são
perguntas diferentes, e qualquer regra de associação escolhida para o cohort iria
divergir do relatório em algum momento — em silêncio.

## Como saber que erramos

Se aparecerem três ou mais consultas diferentes reimplementando "os alunos da
empresa X" espalhadas pelo código, o objeto de primeira classe faz falta e este
ADR deve ser superado.

Se alguém instalar `enrol_cohort` apontando para os cursos do marketplace, a
decisão foi contornada e vai aparecer como matrícula intermitente.
