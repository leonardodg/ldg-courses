# Termos, privacidade e cookies — rascunhos para ajustar

Os três documentos que o rodapé público espera, escritos como **rascunho de
trabalho**. Eles descrevem o que a plataforma realmente faz hoje — foram
redigidos a partir do código, e não de um modelo genérico — mas **não são
aconselhamento jurídico**.

> **Leia antes de publicar.** Um texto legal errado é pior que nenhum: ele cria
> obrigação que você não pretendia e promessa que o sistema não cumpre. O que
> está aqui serve para você levar a alguém que responda por isso, com o trabalho
> de descrever o sistema já feito.

## Como publicar

O Moodle tem duas formas, e a segunda é a que dá controle:

1. **URL externa.** Publique os documentos onde quiser e aponte as três
   configurações do plugin para eles:
   *Administração do site → Plugins → Plugins locais → Captação de parceiros*,
   nos campos **URL dos termos de uso**, **URL da política de privacidade** e
   **URL da política de cookies**.
2. **`tool_policy`, do próprio Moodle.** Ative em
   *Administração do site → Usuários → Privacidade e políticas → Configurações
   de políticas*, escolhendo o manipulador `tool_policy`. Ele versiona os
   documentos, registra quem aceitou qual versão e obriga o aceite no primeiro
   acesso — que é o que uma auditoria pede. Depois aponte os três campos acima
   para os documentos publicados.

**Enquanto um campo estiver vazio, o link não aparece no rodapé.** É deliberado:
link legal que não leva a lugar nenhum é pior que link ausente, porque promete um
documento que não existe.

## O que precisa ser decidido por você

Cada item abaixo aparece marcado como **[decidir]** no texto:

- **Prazo de guarda das candidaturas recusadas.** Hoje a tarefa
  `purge_unconfirmed` apaga apenas as que nunca confirmaram o e-mail, depois de
  N dias configuráveis. Recusada confirmada fica indefinidamente.
- **Canal de contato do encarregado de dados (DPO).** Hoje não existe e-mail de
  empresa configurado.
- **Foro e legislação aplicável.** O contrato social elege Florianópolis - SC.
- **Se o Google Analytics vai ser ligado.** Se sim, a política de cookies deixa
  de ser opcional e passa a exigir banner de consentimento.

---

# Termos de uso

**Última atualização: [decidir a data de publicação]**

## 1. Quem oferece este serviço

Esta plataforma é operada por **LDG Tecnologia Ltda**, CNPJ 68.976.131/0001-42,
doravante "a Plataforma". O endereço do serviço é `https://courses.leodg.dev`.

## 2. O que a Plataforma faz, e o que não faz

A Plataforma hospeda um ambiente de ensino baseado em **Moodle 5.2**, no qual
uma pessoa ou empresa ("Parceiro") publica e vende os próprios cursos.

**A Plataforma não vende os cursos do Parceiro.** A relação de consumo é entre o
Parceiro e o aluno. A Plataforma fornece a infraestrutura, a intermediação
técnica do pagamento e a comissão que consta do plano contratado.

## 3. Como o dinheiro circula

**A cobrança é criada na conta de pagamento do próprio Parceiro**, no provedor
que ele conectar. O valor da venda é recebido por ele. A Plataforma recebe
apenas a comissão, por divisão automática realizada pelo provedor de pagamento
no momento da liquidação.

Em consequência:

- **A nota fiscal da venda é emitida pelo Parceiro**, e não pela Plataforma. A
  Plataforma emite documento fiscal apenas da comissão e de eventual
  mensalidade, contra o Parceiro.
- **Estorno e chargeback são resolvidos na conta do Parceiro**, segundo as
  regras do provedor de pagamento dele.
- A comissão é a que constava do plano **no momento da venda**. Alteração de
  plano não altera vendas já realizadas.

## 4. Candidatura e aprovação

O envio do formulário de parceria **não cria conta nem empresa**. É uma
solicitação, analisada por uma pessoa. A Plataforma pode recusar sem
justificativa, e pode encerrar uma parceria [decidir: com que aviso prévio].

## 5. Responsabilidade pelo conteúdo

O conteúdo dos cursos é de responsabilidade exclusiva do Parceiro, incluindo
direitos autorais, licenças de terceiros e adequação legal. A Plataforma pode
remover conteúdo que viole a lei ou estes termos, e notificará o Parceiro.

## 6. Disponibilidade

A Plataforma não oferece SLA contratual de disponibilidade. Manutenções são
comunicadas quando programadas.

> Esta cláusula existe porque a plataforma **não tem** SLA hoje. Prometer 99,9%
> num texto legal cria obrigação sem a estrutura para cumpri-la.

## 7. Encerramento

O Parceiro pode encerrar a qualquer tempo. Vendas já realizadas e direitos de
acesso já concedidos aos alunos **permanecem válidos pelo prazo contratado**:
quem comprou um curso não perde o acesso porque o vendedor saiu.

## 8. Foro

[decidir] Fica eleito o foro da comarca de Florianópolis - SC.

---

# Política de privacidade

**Última atualização: [decidir a data de publicação]**

## 1. Quem é o controlador

**LDG Tecnologia Ltda**, CNPJ 68.976.131/0001-42.
Contato para assuntos de dados pessoais: [decidir o e-mail].

## 2. Que dados coletamos, e por quê

### Ao enviar uma candidatura de parceria

| Dado | Para quê | Base legal |
|---|---|---|
| Nome da organização, CNPJ | identificar quem se candidata | execução de contrato |
| Nome, e-mail e telefone do contato | responder à candidatura | execução de contrato |
| País e faixa de alunos | dimensionar a proposta e saber a moeda | legítimo interesse |
| Mensagem livre | o que a pessoa quis nos contar | consentimento |
| Endereço IP | limitar envios automatizados | legítimo interesse |
| Momento do aceite dos termos | provar o consentimento | obrigação legal |

O endereço IP serve **apenas** ao limite de envios por hora e não é usado para
nenhuma outra finalidade.

### Ao usar a plataforma como aluno ou parceiro

Aplicam-se os dados que o Moodle coleta por padrão — perfil, matrículas,
progresso, notas e registros de acesso. O Moodle traz um **relatório de dados
pessoais** por plugin, disponível em *Administração do site → Usuários →
Privacidade e políticas*.

## 3. Com quem compartilhamos

- **Provedores de pagamento** (Mercado Pago e Asaas), quando há uma compra. Eles
  recebem os dados necessários à cobrança e são controladores independentes.
- **[decidir] Google Analytics**, se ativado, nas páginas públicas de captação.
  Não é usado dentro do ambiente de ensino.

Não vendemos dados pessoais, e não os usamos para publicidade de terceiros.

## 4. Por quanto tempo guardamos

- **Candidatura não confirmada:** apagada automaticamente após o prazo
  configurado no plugin (padrão de 7 dias).
- **Candidatura recusada:** [decidir o prazo].
- **Candidatura aprovada:** mantida enquanto a empresa existir, porque ela é a
  origem de um vínculo comercial. Num pedido de exclusão, o nome, o e-mail, o
  telefone, a mensagem e o IP são apagados; a razão social, o CNPJ, o país, a
  faixa declarada e **o momento do aceite** permanecem — este último porque é a
  prova de que o consentimento existiu.

## 5. Seus direitos

Acesso, correção, exclusão, portabilidade e revogação do consentimento. O Moodle
implementa exportação e exclusão pelo próprio sistema de privacidade; para a
solicitação, escreva para [decidir o e-mail].

## 6. Segurança

Conexão cifrada em todo o site. Senhas são guardadas com hash. **A Plataforma
não armazena dados de cartão de crédito em nenhuma hipótese** — quem guarda o
instrumento de pagamento é o provedor.

---

# Política de cookies

**Última atualização: [decidir a data de publicação]**

## 1. O que usamos hoje

| Cookie | Origem | Para quê | Necessário? |
|---|---|---|---|
| `MoodleSession` | própria | manter a sessão de quem entrou | sim |
| `MOODLEID1_` | própria | lembrar o nome de usuário, se você pedir | não |
| `local_partners-colormode` | própria | lembrar se você escolheu claro ou escuro | não |

O `local_partners-colormode` não é cookie: é armazenamento local do navegador, e
não é enviado ao servidor. Está listado porque, para quem lê, é a mesma coisa.

## 2. Medição de audiência

[decidir] **Se o Google Analytics for ativado**, ele grava cookies próprios nas
páginas públicas de captação e envia dados de navegação ao Google. Nesse caso:

- é preciso **banner de consentimento** antes de carregar a tag;
- a medição não ocorre dentro do ambiente de ensino;
- o endereço IP é anonimizado.

**Enquanto o campo de ID estiver vazio nas configurações do plugin, nenhuma tag
do Google é carregada.**

## 3. Como recusar

Cookies não necessários podem ser bloqueados no próprio navegador. Bloquear o
`MoodleSession` impede o login.
