# ADR-0006 — Aprovação automática de parceiro, e por que ela ainda não pode existir

**Situação:** Proposta · **Data:** 2026-08-31

## Contexto

Hoje a candidatura de parceria entra numa fila e um administrador decide. A
decisão é humana por uma razão registrada no `CLAUDE.md`: aprovar cria uma
**categoria de curso**, que é objeto global do site e aparece na árvore que todo
usuário vê.

A intenção declarada é automatizar isso quando os meios de pagamento estiverem
funcionando, usando um gatilho melhor do que "alguém preencheu um formulário":
**a empresa vinculou e testou uma conta de recebimento**. É um gatilho
genuinamente mais forte — quem chegou até ali provou que existe, tem conta em
gateway e sabe operá-la.

Ao desenhar isso, três coisas apareceram. Duas são impedimentos técnicos reais,
e a terceira é a que mais preocupa.

**A ordem descrita é circular.** A conta de pagamento do `core_payment` é
escopada por **contexto**, e o contexto de uma empresa é o da **categoria dela**
(`company_account`, `company::get_payment_account()`). Sem empresa não há
categoria, sem categoria não há contexto, e sem contexto **não há onde
pendurar a conta de pagamento**. Não dá para vincular o meio de pagamento antes
de a empresa existir.

**Quem se candidata normalmente não tem conta de usuário.** Para entrar,
vincular e testar o gateway, o candidato precisa de um login. A tela de
aprovação atual exige escolher um usuário que já exista e se recusa a criar um —
criar usuário a partir de formulário público é `RISK_SPAM`. Enquanto não houver
convite por token, não há caminho automático do formulário até alguém logado.

**`is_available()` prova configuração, não dinheiro.** O que existe hoje
responde "a conta está habilitada e tem gateway configurado". Não responde "uma
cobrança real foi paga e o split caiu na conta certa". O `CLAUDE.md` é explícito
sobre a diferença: *baixa manual não prova split* — o `receiveInCash` do Asaas
deixa o split `CANCELLED` com o valor certo na tela. E o split só foi visto
funcionar **uma vez**, em sandbox.

## Decisão

**Revisada em 2026-08-31, depois de o dono do produto rever o fluxo.** A versão
anterior deste ADR adiava a criação da empresa até a aprovação, e por isso caía
na circularidade acima. A saída proposta por ele é melhor e desfaz o nó:

**A empresa e a categoria são criadas automaticamente, mas a categoria nasce
escondida.** É isso que dissolve o motivo original da regra "sem
auto-atendimento": uma categoria com `visible = 0` não é objeto global que todo
mundo vê — ela não aparece na árvore de ninguém até alguém aprovar.
`core_course_category::create()` aceita `visible` diretamente, então o custo
técnico é uma linha.

O fluxo passa a ser:

1. **O envio do formulário cria** a conta do candidato, a empresa e a categoria
   **escondida**. A empresa nasce `suspended`.
2. **O candidato entra pelo convite**, encontra a empresa dele e vincula a conta
   de recebimento — o contexto existe, porque a categoria existe.
3. **A aprovação torna a categoria visível** e a empresa `active`. É aqui que
   entra o gatilho automático, quando houver pagamento liquidado com split
   confirmado.

Como o passo 1 deixa de ter humano no caminho, **as defesas contra robô passam
a ser requisito, e não conforto**. As três camadas que hoje protegem só a fila
passam a proteger a criação de objetos no banco:

| Camada | Hoje | Com criação automática |
|---|---|---|
| Limite de taxa por IP | protege a fila | vira teto de criação de empresas |
| Honeypot | descarta o envio | idem, e nunca deve criar nada |
| reCAPTCHA | opcional | **obrigatório**: sem chave, o passo 1 não pode ser automático |
| Confirmação de e-mail | não existe | **necessária**: nada é criado antes de o candidato clicar no link |

A confirmação por e-mail é a defesa que falta e a mais importante das quatro:
ela custa ao robô um endereço real e funcional por empresa criada, o que o
limite de taxa e o captcha sozinhos não cobram.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Aprovar automático assim que a conta for vinculada | Vincular é configuração, e configuração não é prova. Um vendedor com token errado passa no `is_available()` e falha na primeira venda de verdade — e quem descobre é o comprador |
| Criar a empresa `active` e confiar no `can_sell()` | O `can_sell()` já barra a venda paga, mas a categoria fica visível na árvore do site desde o primeiro minuto. Empresa que desistiu no meio vira lixo permanente na navegação de todo mundo |
| Deixar o candidato vincular pagamento antes da empresa existir | Impossível sem reescrever o escopo do `core_payment`: a conta vive no contexto da categoria, e a categoria é a empresa |
| Criar o usuário direto no envio do formulário | `RISK_SPAM`. Um formulário público que cria contas é um formulário público que cria milhares de contas |

## Consequências

Fica mais fácil: o gatilho de ativação é uma pergunta que o sistema já sabe
fazer quase inteira. `company::can_sell()` hoje é `status ativo` **e** conta
disponível — falta só a terceira parte, "e houve pagamento liquidado".

Fica mais difícil: **é preciso um sinal de pagamento provado**, e ele não
existe. O `local_marketplace_sale` guarda a venda, mas ninguém pergunta a ele
"esta empresa já recebeu de verdade?". Essa consulta é o trabalho real desta
automação, e ela depende de o split estar provado em produção — o que ainda não
aconteceu nem no Mercado Pago nem com comissão maior que zero.

E fica uma dependência de ordem: **a ativação automática não pode ser
construída antes de os gateways estarem prontos**, porque o sinal que a dispara
vem deles. A criação automática do passo 1, essa sim, depende só das defesas
contra robô.

**Já feito em 2026-08-31:** a aprovação passou a criar a conta do candidato
quando não existe nenhuma com o e-mail do contato, e a enviar convite com token
de definição de senha. Era buraco do fluxo manual — sem conta não há dono, e sem
dono não há empresa — e é pré-requisito de qualquer automação. A senha nasce
aleatória e desconhecida; o acesso é só pelo link.

## Como saber que erramos

O sinal é uma empresa ativada automaticamente cuja primeira venda real falha.
Se isso acontecer, o gatilho estava olhando para configuração e não para
dinheiro, e a porta foi automatizada cedo demais.

O sinal contrário — nenhuma empresa chegando à ativação — indica que o caminho
do convite até o primeiro pagamento tem um degrau alto demais, e o problema não
é a automação, é o onboarding.
