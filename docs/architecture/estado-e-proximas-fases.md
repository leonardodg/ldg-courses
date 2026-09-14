# Estado do marketplace e o que falta

Atualizado em 2026-09-01.

## O que ja funciona

Validado de ponta a ponta em producao: preferencia criada, checkout do Mercado
Pago, pagamento aprovado, webhook recebido, direito de acesso criado e aluno
matriculado.

| Componente | Estado |
|---|---|
| `local_marketplace` | empresas, vendedores, ofertas, direitos, relatorios, vitrine, planos comerciais, telas de admin |
| `local_partners` | landing publica, candidatura com confirmacao de e-mail, fila e aprovacao que provisiona a empresa |
| `theme_ldg` | tema filho do Moove: dark e claro, menu lateral recolhivel, landing como home |
| `paygw_mercadopago` | OAuth com PKCE, vincular/desvincular, `marketplace_fee`, webhook, modo de teste |
| `paygw_asaas` | Pix, boleto e cartao, split provado, credencial cifrada, webhook autenticado |
| `paygw_pagarme` | codigo completo - Pix com pagina propria, boleto, cartao tokenizado, assinatura, estorno. **Nada foi exercitado contra a API**: a conta de homologacao nao processa cobranca |
| `enrol_marketplace` | matricula por diferenca, a partir dos direitos vigentes |
| `availability_marketplace` | secao liberada por oferta especifica, com botao de compra |
| `block_marketplace` | assinaturas do aluno no Dashboard |
| Infra | VPS Oracle, deploy automatico no push para `dev`, TLS por certbot, CI validando os plugins |

**187 testes PHPUnit, zero violacoes de phpcs**, rodados no CI a cada push. O job
de validacao bloqueia o deploy quando falha.

Tres idiomas completos: `en`, `pt_br` e `es`, com paridade exata de chaves.

**A base de calculo da comissao virou configuracao** (ADR-0007), e os termos
aplicados - percentual, base e origem - sao fotografados em cada venda. Mudar a
configuracao nao reescreve o passado.

## O que NAO foi validado

Nao e o mesmo que "nao funciona" - e o que ninguem viu funcionar ainda.

**O split - PROVADO em 2026-08-27**, no sandbox do Asaas, com duas contas
distintas:

    cobranca ... pay_w1ijat3r74sx4nlp | CONFIRMED
    bruto ...... R$ 100,00 | liquido R$ 97,52
    SPLIT ...... carteira da plataforma | AWAITING_CREDIT | 25% | R$ 24,38

Primeira vez no projeto. Falta ver o split do Asaas chegar a DONE com o saldo se
movendo. Roteiro repetivel em `../data-validation/asaas-sandbox.md`.

**O split do Mercado Pago - PROVADO em 2026-09-08**, com duas contas distintas e
dinheiro real:

    pagamento .. 178004552586 | approved/accredited | pix
    bruto ...... R$ 5,00 | taxa do MP R$ 0,05 | liquido do vendedor R$ 3,70
    SPLIT ...... application_fee | R$ 1,25 | collector 1233186727 != app 3675841384
    extrato .... R$ 1,25 na conta da plataforma | dinheiro a liberar

O vendedor daquela rodada e **pessoa fisica**, e isso so foi descoberto depois:
o CNPJ nao e exigido de quem vende. Ver `../adr/0010-vendedor-pessoa-fisica-no-mercado-pago.md`.

**O split do Pagar.me - SEM PROVA em 2026-09-09**, e nao por falta de tentativa.
Duas contas de homologacao responderam igual:

    recebedor .. POST /recipients | 412 action_forbidden
                 "This company it not allowed to create a recipient"
    cobranca ... pix, cartao e boleto | 200 no HTTP, failed na cobranca
                 500 "internal_error | Erro desconhecido no proxy"
    split ...... nunca chegou a existir - sem recebedor nao ha o que dividir

Sao dois bloqueios comerciais independentes, e liberar so um nao destrava a
medicao. O codigo do plugin esta pronto e foi escrito pela DOCUMENTACAO, com
cada suposicao marcada; o roteiro para medir, o script de prova e o texto do
chamado ao suporte estao em `../data-validation/pagarme-sandbox.md`.

No mesmo dia, a **compra pela vitrine** fechou a cadeia inteira, com o webhook
chegando sozinho:

    pagamento .. 177042328687 | approved/accredited | pix | R$ 5,00
    split ...... application_fee R$ 1,25 | taxa do MP R$ 0,05 | liquido R$ 3,70
    moodle ..... paygw_mercadopago approved | feesource COMPANY | payments id 3
    venda ...... local_marketplace_sale id 3 | externalid 177042328687
    acesso ..... direito ativo por 30 dias exatos | matricula por enrol_marketplace

O `feesource` gravado e `company`, e nao `site`: a comissao foi resolvida na
linha da empresa, e nao caiu no padrao de fabrica.

Antes disso, vendedor e marketplace eram a mesma conta, entao o `marketplace_fee`
nao transferia nada - e nao havia erro, que e o pior tipo de falso positivo. Por
isso a condicao `collector_id != dono da aplicacao` virou guarda automatica no
script da prova. Roteiro em `../data-validation/mercadopago-split.md`.

A rodada confirmou a ordem de deducao: a taxa do gateway sai primeiro, e a
comissao sai do que sobra - a plataforma recebe o combinado, e quem absorve a
taxa e o vendedor.

Descoberta no caminho: o sandbox do Checkout Pro nao consegue cobrar com
`marketplace_fee`. Com `wallet_purchase` ele entra em loop no login de carteira;
sem ele, devolve erro. Nos dois casos `payments/search` volta vazio.

Descoberta no caminho, que vale mais que a prova: baixa manual (`receiveInCash`)
faz o split sair CANCELLED com o valor certo na tela. Dinheiro que nao passou
pelo gateway nao tem como ser dividido.

**Pix e boleto.** Pix exige chave registrada na conta do vendedor; boleto e
excluido pelo `wallet_purchase` do modo de teste. Sao os caminhos assincronos,
onde o webhook e a unica fonte da verdade.

**Expiracao de direito.** Ofertas com prazo nunca chegaram ao vencimento num
teste.

**Dominio de vendedor de ponta a ponta.** O mapa, o nginx e o certbot estao
prontos; falta apontar um dominio real e confirmar - inclusive que login num
dominio nao vaza para o outro.

## Lacunas conhecidas

### Regra do video externo e honra, nao sistema

`course_policy` guarda `hostingtype` por curso, e o papel de vendedor tem
`PROHIBIT` em `repository/upload:view`. Mas nada audita se um curso marcado como
`external` passou a hospedar video no `moodledata` por outro caminho.

Sem uma tarefa que meca o tamanho por curso e alerte, a regra dos 25% depende de
boa fe. Com voce e uma professora, tudo bem; com dez vendedores, nao.

### Assinatura nao renova sozinha

O Mercado Pago **nao tem recorrencia com split**: `preapproval` nao aceita
`marketplace_fee`, e o Transparente com cartao salvo exige CVV a cada cobranca.

Hoje o aluno recompra quando vence, avisado por e-mail cinco dias antes. Decisao
de negocio pendente: aceitar assim, ou montar cobranca recorrente fora do split
com repasse manual - o que muda o modelo de dados e o relatorio.

### Trava de resolucao de video

**E a lacuna mais cara do projeto.** As faixas de resolucao por ticket existem no
banco (`local_marketplace_plan_tier`) e aparecem na vitrine, mas **nada as
aplica**: um curso barato serviria 4K se o video existisse em 4K.

A margem do plano de entrada depende inteiramente disso, e a alternativa
descartada era cobrar taxa fixa por venda - que e justamente o argumento
comercial contra os concorrentes. Ver ADR-0005.

## Fase 3 - dominio por vendedor

**Fundacao pronta.** Falta so o teste com dominio real.

| Camada | Responsabilidade |
|---|---|
| `nginx` | entrega o trafego, com `Host` preservado |
| Arquivo gerado no `dataroot` | diz quais dominios existem - **fronteira de seguranca** |
| Banco, no `after_config` | diz se a empresa esta ativa; bloqueia a plataforma inteira se nao |

O `config.php` usa o `Host` apenas como chave numa lista gerada do banco. Um
`Host: evil.com` nao encontra a chave e nada acontece - sem isso seria injecao de
cabecalho, com o Moodle gerando todo link para o dominio do atacante, inclusive o
de redefinicao de senha.

Provisionar:

```bash
sudo .devcontainer/nginx/provisiona-dominio.sh meuscursos.joao.com contato@leodg.dev
php local/marketplace/cli/domains.php --rebuild
```

Decisao mantida: **sem** `$CFG->sessioncookiedomain`. A sessao passa a ser por
dominio, e quem entra no dominio do vendedor nao esta logado na plataforma. Isso
precisa estar claro na UX de checkout.

## Fase 4 - captacao e relatorio

**Concluida em 2026-09-01.**

O relatorio financeiro mostra transacoes, cursos vendidos e assinaturas, com a
comissao por empresa e **os termos aplicados em cada venda**.

A captacao foi construida no `local_partners`: landing publica, candidatura com
confirmacao de e-mail opcional, anti-robo em tres camadas e aprovacao que chama
`api::create_company()`.

O tema de captacao havia sido abandonado por qualidade de codigo. Voltou como
`theme_ldg`, **filho do `theme_moove`** - e nao do `boost_union`, como se
imaginava. O Moove ja resolvia a base visual, e o custo passou a ser sobrescrever
os quatro metodos dele que carregam `theme_config::load('moove')` fixo.

## Plano Free - a peca tecnica existe desde 04/09/2026

O `mod_ldgvideo` entrega a aula em video por embed, e os dois papeis de empresa
fecham os caminhos para o moodledata. Juntos, sao o que faz o plano Free custar
zero de banda: sem arquivo local, nao ha video nosso para servir.

O que **nao** existe ainda e o Free como plano vendavel em
`local_marketplace_plan` - este trabalho entregou a peca tecnica, e nao a
comercial.

Ver [ADR-0008](../adr/0008-embed-multiplataforma-pelo-core.md) e
[ADR-0009](../adr/0009-papeis-de-empresa-sem-upload.md).

## Fase 5 - conteudo hospedado na plataforma

**Bloqueada por decisao de negocio**, nao por tecnica. Falta definir a cobranca:
assinatura com cota de storage e banda mais comissao menor, ou comissao maior com
limite tecnico por curso.

`course_policy::validate_hostingtype()` recusa `platform` hoje, de proposito.

Quando ela destravar, o plugin de video do plano pago **e outro** - com upload,
chave de API e link assinado. O `mod_ldgvideo` nao vira aquilo: a
`plan::max_resolution_for()` nao tem consumidor aqui, porque o YouTube nunca
suportou travar resolucao pela URL do embed. Ver a
[ADR-0005](../adr/0005-trava-de-resolucao-por-ticket.md).

## Multi-pais

**Implementado em 2026-08-27** - ver [ADR-0002](../adr/0002-conta-de-pagamento-por-pais.md).

A oferta tem `country` em ISO-3166 alpha-2, e a tabela
`local_marketplace_account` liga empresa x pais x conta. A moeda deixou de ser
escolhida e passou a ser derivada do pais, o que torna impossivel - e nao apenas
invalida - uma oferta em BRL vendendo na Argentina.

A chave e ISO e nao o `siteid` do Mercado Pago: Asaas e Pagar.me so existem no
Brasil e nao tem codigo de site. A traducao para o vocabulario de cada
fornecedor fica no cliente HTTP dele.

Falta ainda um par `client_id`/`client_secret` por pais no Mercado Pago, em vez
de um unico.

Restricao que decidiu o desenho: `core_payment::get_payable()` nao recebe o
usuario, entao valor, moeda e conta sao funcao pura do `itemid`. Uma oferta nao
pode ser BRL para um aluno e ARS para outro - o pais tem que estar na oferta.

## Divida tecnica conhecida

**AMD escrito a mao.** Nao ha grunt nem babel: `amd/src` e AMD de verdade e
`amd/build` e o mesmo arquivo com o `define` nomeado. Se alguem introduzir ES6 no
`src` sem um transpilador, o `requirejs.php` serve esse arquivo e o modulo quebra
com "No define call".

**Tres plugins de terceiros fora do suporte declarado.** `mod_attendance`,
`format_onetopic` e `format_tiles` declaram `supported = [501, 501]` e a
plataforma roda 5.2. O Moodle avisa e nao bloqueia; ficam sob observacao ate
publicarem release que declare 5.2.

**Disco da VPS.** Em 2026-08-25 estava em 97%, com 52 GB em imagens Docker de
varios projetos. Cada deploy precisa de ~2,8 GB para a imagem nova. Sem folga, o
deploy falha na extracao - e o site continua no ar com o container antigo, entao
"o site responde" nao prova que o deploy aplicou.

**Sem Behat.** Os 33 testes sao unitarios; nenhum exercita o fluxo pelo navegador.
