# Documentação do ldg-courses

Plataforma Moodle 5.2 onde qualquer pessoa publica curso gratuito ou pago, com
split de pagamento. Gateways: Mercado Pago, Asaas e Pagar.me.

## Por onde começar

| Se você quer… | Vá para |
|---|---|
| entender a estrutura de worktrees | [`dev/estrutura-worktrees.md`](dev/estrutura-worktrees.md) |
| montar o ambiente e começar a codar | [`dev/moodev.md`](dev/moodev.md) e [`dev/guia-worktrees.md`](dev/guia-worktrees.md) |
| **saber como uma feature se faz aqui** | [`dev/padrao-de-implementacao.md`](dev/padrao-de-implementacao.md) |
| mandar a mudança para produção | [`dev/fluxo-de-contribuicao.md`](dev/fluxo-de-contribuicao.md) |
| entender **por que** o sistema é assim | [`architecture/decisoes-marketplace.md`](architecture/decisoes-marketplace.md) |
| **produto: o que vemos, a quem e com que sucesso** | [`produto/`](produto/) |
| saber o que existe e o que falta | [`architecture/estado-e-proximas-fases.md`](architecture/estado-e-proximas-fases.md) |
| mexer nas tabelas | [`data-model/marketplace.md`](data-model/marketplace.md) |
| testar o que está no ar | [`data-validation/painel-de-testes.md`](data-validation/painel-de-testes.md) |
| **configurar uma instalação nova** | [`operacao/configuracao-inicial.md`](operacao/configuracao-inicial.md) |
| configurar **um plugin** específico | o `README.md` dentro do diretório dele |
| saber que dado pessoal é coletado | [`legal/mapa-de-dados-pessoais.md`](legal/mapa-de-dados-pessoais.md) |
| **publicar termos, privacidade e cookies** | [`legal/politicas-modelo.md`](legal/politicas-modelo.md) |
| **cadastrar o site no Google** | [`operacao/cadastrar-no-google.md`](operacao/cadastrar-no-google.md) |

### Os READMEs dos plugins

Cada plugin explica para que serve, de que depende, o que precisa ser
configurado e as armadilhas dele:

| Plugin | README |
|---|---|
| núcleo | [`public/local/marketplace/`](../public/local/marketplace/README.md) |
| captação de parceiros | [`public/local/partners/`](../public/local/partners/README.md) |
| tema | [`public/theme/ldg/`](../public/theme/ldg/README.md) |
| gateway Asaas | [`public/payment/gateway/asaas/`](../public/payment/gateway/asaas/README.md) |
| gateway Mercado Pago | [`public/payment/gateway/mercadopago/`](../public/payment/gateway/mercadopago/README.md) |
| gateway Pagar.me | [`public/payment/gateway/pagarme/`](../public/payment/gateway/pagarme/README.md) |
| matrícula | [`public/enrol/marketplace/`](../public/enrol/marketplace/README.md) |
| liberação de seção | [`public/availability/condition/marketplace/`](../public/availability/condition/marketplace/README.md) |
| bloco de assinaturas | [`public/blocks/marketplace/`](../public/blocks/marketplace/README.md) |
| portal do aluno | [`public/course/format/ldg/`](../public/course/format/ldg/README.md) |
| aula em vídeo | [`public/mod/ldgvideo/`](../public/mod/ldgvideo/README.md) |

## As pastas

| Pasta | O que guarda |
|---|---|
| [`adr/`](adr/) | **Architecture Decision Records** — uma decisão por arquivo, com contexto e consequências |
| [`architecture/`](architecture/) | visão de sistema, diagramas, estado do projeto e as decisões consolidadas |
| [`brand/`](brand/) | identidade visual: logo, cores, tipografia, tom de voz |
| [`coding-standards/`](coding-standards/) | padrão de código, convenções de commit, o que o CI cobra |
| [`data-model/`](data-model/) | tabelas, campos e relações |
| [`data-validation/`](data-validation/) | como se verifica que funciona: painel de testes, cenários, dados de teste |
| [`dev/`](dev/) | guias de quem desenvolve: ambiente, ferramentas, fluxo de trabalho |
| [`operacao/`](operacao/) | colocar e manter no ar: configuração inicial, ordem das coisas |
| [`produto/`](produto/) | hub de produto: PRD, TRD, fluxos, briefing UI/UX, intenção de schema, plano de implementação |
| [`legal/`](legal/) | privacidade, termos de uso e o mapa do que é coletado |
| [`ai-plans/`](ai-plans/) | **registro de todo plano executado por agente de IA** |
| [`history/`](history/) | de onde o projeto veio: ideia inicial, conversas fundadoras |
| [`man/`](man/) | páginas de manual instaláveis (`man moodev`) |
| `private/` | **fora do git** — material comercial interno. Ver a regra em "Convenções" |

## Convenções

**Português, sem acentos no código.** Prosa e documentação levam acentuação
normal; comentários em código e mensagens de commit vão sem, por consistência
com o que já existe na base.

**Fim de linha LF.** Garantido por [`.gitattributes`](.gitattributes), que tem
precedência sobre qualquer configuração da máquina. O git global daqui tinha
`core.autocrlf=true` até 27/08/2026 — sem o atributo, o checkout escreveria CRLF
no working tree, sujando diffs e quebrando a man page.

**Diagramas em Mermaid**, em bloco ```` ```mermaid ````, para versionar como
texto. O SVG em `architecture/` é a exceção: veio pronto.

**Um documento, um assunto.** Se um arquivo passa a responder duas perguntas
diferentes, é sinal de que virou dois.

**`docs/private/` não é versionado.** É onde vive documentação confidencial de
negócio — margem, custo, simulação de lucro. O remote é **público** e o deploy
faz `rsync` de `docs/` para a VPS, então a regra está no `.gitignore`
versionado, e não num exclude local que ninguém enxerga.

A linha de corte, quando um número precisa entrar em código ou em documento
versionado: **pode o que a plataforma exibe em público** — mensalidade dos
planos, percentual de comissão, tetos de resolução, faixas de ticket; **não pode
o que explica a nossa margem** — custo de banda, custo por aluno ativo, lucro
líquido, e o que o concorrente cobra.

Nada em `private/` sobrevive a um clone novo. É material de trabalho do dono do
projeto, e não documentação do sistema.
