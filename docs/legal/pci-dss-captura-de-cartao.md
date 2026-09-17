# PCI DSS: o que muda ao digitar o cartão na nossa página

O `paygw_mercadopago` tem uma configuração — *Onde o cartão é digitado* — que
decide quem hospeda os três campos do cartão. A página de pagamento é **nossa
nos três modos**, com o nosso layout e a nossa marca; o que muda é o caminho que
o número do cartão percorre.

Essa escolha **não é técnica**. Ela muda o seu enquadramento no PCI DSS, e com
ele obrigações contratuais com as bandeiras que não se desfazem depois de um
incidente. Este documento existe para que a decisão seja tomada sabendo o preço.

> **O que este documento não é.** Não é parecer jurídico nem substitui o seu
> adquirente. **Quem determina o SAQ aplicável é o adquirente**, e a versão
> corrente do padrão é o PCI DSS v4.0.x. Confirme com ele antes de assumir
> qualquer número daqui.

---

## Os três enquadramentos, do mais barato ao mais caro

| | Quem hospeda os campos | O PAN passa pelo nosso servidor? | SAQ | Ordem de grandeza |
|---|---|---|---|---|
| **A** | iframes do Mercado Pago na nossa página | **não**, nem pelo nosso JavaScript | **SAQ A** | ~30 perguntas |
| **B** | formulário nosso, JS envia direto ao MP | **não**, mas o nosso JS monta o campo | **SAQ A-EP** | ~150 perguntas |
| **C** | formulário nosso, backend nosso envia ao MP | **sim** | **SAQ D** | 300+ perguntas |

**Os três estão implementados**, e a configuração *Onde o cartão é digitado* os
apresenta **nessa ordem** — quem desce a lista está escolhendo mais exposição a
cada linha. O rótulo de cada opção traz o SAQ correspondente, porque a escolha é
feita naquela tela e o custo precisa estar visível ali, não num documento que
ninguém abre na hora.

**O B é o meio-termo honesto.** Dá controle total do HTML e do CSS sem que o
número do cartão toque o servidor. Se o motivo de querer o C é aparência, o B
entrega a mesma aparência por uma fração do custo.

> **O B não é "quase o A".** No modo direto o número **passa pelo nosso DOM**,
> e é isso que traz as exigências 6.4.3 e 11.6.1 para cima de nós: todo script
> carregado naquela página precisa de inventário e de controle de integridade.
> A diferença para o C é grande; a diferença para o A também.

---

## O que o código já faz, e o que ele não pode fazer

**Implementado no plugin**, e coberto por teste:

- o padrão é o modo A, para que quem instala sem ler isto não acabe em escopo
  sem ter escolhido;
- **B e C são ignorados sem HTTPS** — o código cai no modo A sozinho, e a tela
  diz por quê. Não é aviso: é recusa (`card_capture::current()`). Vale para o B
  também, e a razão merece ser dita: o PAN não passar pelo nosso backend **não
  protege de nada** se a página que o coleta pode ser reescrita em trânsito;
- a tabela do gateway **não tem coluna capaz de guardar dado de cartão**, e o
  teste lê o texto do `install.xml` para impedir que alguém acrescente uma
  amanhã (`card_capture_test::test_nao_ha_coluna_capaz_de_guardar_cartao`);
- o que se guarda são identificadores do Mercado Pago (`mpcustomerid`,
  `mpcardid`), nunca o instrumento.

**Não implementável em plugin nenhum.** É aqui que mora a metade cara, e nenhuma
linha de código a substitui:

| Obrigação | O que é, na prática | Cadência |
|---|---|---|
| **Varredura ASV** | Contratar um *Approved Scanning Vendor* credenciado pelo PCI SSC para varrer os IPs públicos. Tem que **passar**, não só rodar | trimestral |
| **Teste de intrusão** | Contratação de terceiro, interno e externo, com segmentação testada | anual, e a cada mudança relevante |
| **Varredura interna de vulnerabilidade** | Ferramenta própria ou contratada, com correção documentada | trimestral |
| **Políticas formais** | Documentos escritos e aprovados: segurança da informação, controle de acesso, resposta a incidentes, gestão de fornecedores | revisão anual |
| **Treinamento** | Conscientização de segurança de quem opera | admissão e anual |
| **Gestão de fornecedores** | Lista de terceiros que tocam dado de cartão, com o AoC de cada um | anual |
| **Registro e monitoração** | Log centralizado, retenção de 12 meses (3 meses acessíveis de imediato), revisão diária | contínuo |
| **AoC / atestação** | Preencher o SAQ D, assinar o *Attestation of Compliance* e entregar ao adquirente | anual |

E duas do v4.0 que valem para **B e C**, e que passaram a ser obrigatórias em
31/03/2025 — existem por causa dos ataques de *skimming* em página de pagamento:

- **6.4.3** — inventário de **todo** script carregado na página de pagamento,
  com justificativa e garantia de integridade de cada um;
- **11.6.1** — detecção de alteração não autorizada no cabeçalho HTTP e no
  conteúdo da página de pagamento.

Vale reparar no que isso significa: **qualquer script de terceiro na página de
pagamento** — analytics, chat, mapa de calor, pixel de anúncio — entra no
inventário e precisa de controle de integridade. Num Moodle, isso inclui plugin
de terceiro que injete JavaScript.

---

## O caminho para habilitar o modo B

Bem mais curto que o do C, e é por isso que ele existe:

1. **Confirmar o SAQ com o adquirente** — A-EP, e não D.
2. **Montar o inventário de scripts** da página de pagamento e a detecção de
   alteração (6.4.3 e 11.6.1). **É o item que mais dá trabalho aqui**, e o que
   mais se esquece: num Moodle, qualquer plugin de terceiro que injete
   JavaScript naquela página entra na conta.
3. **Varredura ASV trimestral**, que o A-EP também exige.
4. **Políticas formais**, em escopo menor que o do D.
5. **Preencher o SAQ A-EP e assinar o AoC.**

Não há teste de intrusão anual obrigatório, e o escopo de servidores é bem
menor — o número do cartão nunca chega ao backend, então ele fica fora.

---

## O caminho para habilitar o modo C

Na ordem, porque cada passo depende do anterior:

1. **Falar com o adquirente** e confirmar o SAQ aplicável ao seu volume e
   modelo. É ele quem determina, e o resultado pode ser diferente do que está
   nesta tabela.
2. **Delimitar o escopo**: quais servidores, redes e pessoas passam a estar
   dentro. Tudo que pode alcançar o ambiente do cartão entra — inclusive a
   máquina de quem administra.
3. **Segmentar a rede**, se quiser que o escopo não seja o parque inteiro. Sem
   segmentação, tudo é escopo.
4. **Escrever as políticas** e fazê-las serem aprovadas. Modelos em
   [`politicas-modelo.md`](politicas-modelo.md) servem de ponto de partida, mas
   precisam descrever o que você **faz de verdade** — política que não
   corresponde à operação reprova na auditoria e, pior, não protege ninguém.
5. **Contratar o ASV** e passar na primeira varredura.
6. **Contratar o teste de intrusão**.
7. **Implantar log centralizado** com retenção de 12 meses.
8. **Montar o inventário de scripts** da página de pagamento e a detecção de
   alteração (6.4.3 e 11.6.1).
9. **Preencher o SAQ D e assinar o AoC**, entregando ao adquirente.
10. **Só então** ligar a configuração — e lembrar que ela ainda exige HTTPS,
    senão o código continua recusando.

**Custo recorrente, para dimensionar:** ASV e pentest são os itens contratados,
e somados costumam ficar na casa de alguns milhares de reais por ano para uma
operação pequena. O item caro de verdade costuma ser o **tempo interno**:
manter as evidências, revisar logs e refazer a atestação todo ano.

---

## A pergunta que decide

**O modo C entrega alguma coisa que o B não entregue?**

O B dá controle total do HTML e do CSS, o aluno não sai do site, e o número do
cartão nunca toca o nosso servidor. O C acrescenta apenas a capacidade de o
**backend** ver o número — e não há nada no nosso produto que precise disso: o
Mercado Pago devolve o `card_token_id`, e é com ele que a assinatura é criada.

Enquanto não houver uma necessidade que só o C atenda, ele é custo sem
contrapartida. A configuração existe porque foi pedida e porque medir os três
caminhos tem valor — **não porque o C seja recomendado**.

---

## Ligado ao resto

- A decisão de onde o cartão é digitado: `classes/card_capture.php`
- Por que a assinatura não carrega comissão:
  [ADR-0012](../adr/0012-duas-assinaturas-e-so-uma-tem-split.md)
- As medições que sustentam o desenho:
  [`mercadopago-assinatura.md`](../data-validation/mercadopago-assinatura.md)
- Dados pessoais e LGPD, que é assunto **diferente** e igualmente obrigatório:
  [`mapa-de-dados-pessoais.md`](mapa-de-dados-pessoais.md)
