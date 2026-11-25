# ADR-0014 — A trava de resolução por mensalidade do vendedor, e onde ela precisa ser aplicada

**Situação:** Aceita · **Data:** 2026-09-23 · **Aceite:** 2026-09-25

## Contexto

Este ADR **supera o [ADR-0005](0005-trava-de-resolucao-por-ticket.md)**. A trava
de resolução continua necessária; o que mudou é **sobre o que** ela trava e
**onde** ela é obrigatória.

No ADR-0005 a trava era definida pelo **ticket do curso** (preço da oferta) e a
decisão se organizava em dois níveis obrigatórios por provedor: player + link
assinado na origem. Desde então o desenho de produto mudou em três frentes que
intersectam essa decisão:

- **A cobrança da plataforma passou a ser a mensalidade SaaS da empresa**
  (paymentarea `'plan'`: a empresa paga à plataforma, sem split). Os degraus
  comerciais — Free, degrau intermediário, degrau BYOS — são definidos por essa
  mensalidade, não pelo preço de cada curso.
- **A frente de hospedagem escolhida é o Bunny multi-tenant**: uma `library` por
  empresa, com a trava de resolução entrando dentro dessa frente. Cloudflare
  ficou fora da v1.
- **O player passou a ser o lugar obrigatório da trava**: o `core_media_manager`
  é o que o aluno vê, e é nele que o seletor de qualidade precisa deixar de
  oferecer o que o plano não inclui.

O que continua valendo, do 0005, sem enfraquecer:

- **O streaming é adaptativo.** Bunny e Cloudflare entregam HLS/DASH: um único
  manifesto com todas as trilhas, de 360p a 4K. Não existe "entregar só 720p"
  sem intervir — no player ou na origem.
- **O modelo de cobrança dos provedores difere na raiz.** Bunny cobra por
  **gigabyte transferido** (4K custa várias vezes 720p); Cloudflare cobra por
  **minuto assistido**, independente da resolução.
- **A trava protege a economia do plano.** Servir 4K num curso barato consome
  banda de plano caro e rende comissão de plano barato — a diferença agora é
  lida pela **mensalidade da empresa**, não pelo ticket do curso.
- **`plan::max_resolution_for()` e `local_marketplace_plan_tier` existem** e
  continuam sem consumidor: a trava é promessa no banco até o player ler o teto.

O que muda em relação ao 0005, explicitamente:

| | ADR-0005 | ADR-0014 |
|---|---|---|
| **Chave da trava** | Ticket (preço) do curso | **Mensalidade do vendedor** (plano pago pela empresa) |
| **Lugar obrigatório** | Dois níveis, peso por provedor | **Player (`core_media_manager`) é o obrigatório** |
| **Origem assinada** | Obrigatória quando o provedor cobra por volume (Bunny) | **Consequência do modelo de cobrança** do provedor, não o núcleo da decisão de produto |
| **Provedor em jogo** | Cloudflare vs Bunny como eixo da decisão | **Bunny multi-tenant** é a frente; Cloudflare fora da v1 |

## Decisão

Vamos travar a resolução **pela mensalidade do vendedor** (o plano SaaS que a
empresa paga à plataforma), e aplicar a trava **primariamente no player**
(`core_media_manager`): o seletor de qualidade não oferece trilha acima do teto
do plano.

As restrições de origem do provedor (por exemplo, link assinado com resolução
máxima na Bunny) são **consequência do modelo de cobrança do provedor** — se ele
cobra por volume, a origem precisa proteger o que o player só sugere —, e não o
núcleo da decisão de produto. O núcleo é: **qual plano a empresa paga define o
teto**, e **o aluno enxerga esse teto no player**.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Manter o ADR-0005 como está (trava por ticket de curso, dois níveis obrigatórios) | A economia que a trava protege passou a ser a da **mensalidade da empresa**, não o ticket de cada curso: o mesmo curso pode ser assistido por empresas em degraus diferentes, e o teto tem que seguir o plano pagante. O 0005 também elevava a origem assinada a co-igual do player quando a frente escolhida é Bunny multi-tenant — a decisão de produto ficava presa ao provedor |
| Travar só pelo preço do ticket do curso, sem olhar a mensalidade | Erra nos dois sentidos: um curso caro de empresa Free continuaria puxando 4K no degrau que a plataforma absorve a banda; um curso barato de degrau pago ficaria abaixo do que a mensalidade já pagou. O degrau comercial deixa de refletir no que o aluno vê |
| Travar só na origem (link assinado), sem mexer no player | O seletor de qualidade continua oferecendo o que o plano não inclui e falhando ao selecionar. O aluno lê como defeito da plataforma, e não como limite do plano — exatamente o efeito que o 0005 já recusava |
| Transcodificar só até o teto no upload | Torna o teto **permanente**: mudar a mensalidade/degrau não reprocessa o vídeo sem novo upload. O teto é do plano pagante, e o plano muda |

## Consequências

Fica mais fácil: a regra de negócio continua numa fonte só
(`plan::max_resolution_for()` / tier do plano), agora indexada pelo **plano da
empresa** — a mesma consulta serve ao player, à comparação de degraus na
landing e ao gate de custo do PRD. O player ser o lugar obrigatório desacopla a
decisão de produto do provedor: trocar a origem não reescreve o que o aluno
enxerga.

Fica mais difícil: **a escolha do provedor deixa de mandar na decisão, mas não
some da conta.** Enquanto a origem cobra por volume (Bunny) e a origem não
restringe, o bloqueio só no player é contornável — abrir o `.m3u8` e tocar a
trilha 4K continua custando banda da plataforma. A restrição na origem
permanece **consequência obrigatória do modelo de cobrança** enquanto a
cobrança for por gigabyte; ela saiu do centro da decisão, não do escopo.

E a dívida do 0005 permanece: o `plan.hostingmodel` hoje é **rótulo**. Não há
onde a empresa guardar a chave da própria conta (BYOS) nem código que troque o
destino do upload conforme o plano. Enquanto isso não existir, o BYOS é promessa
comercial sem a peça técnica. A diferença é que a trava por mensalidade já
existe como intenção alinhada com a cobrança SaaS implementada; o BYOS continua
sendo o que falta para o degrau mais alto.

## Como saber que erramos

Sinal herdado do 0005, ainda válido: **o custo de banda cresce mais rápido que
a receita**. Se o custo por aluno ativo sobe com o ticket/mensalidade parado, a
trava não está sendo aplicada onde deveria — provavelmente a origem cobra por
volume e só o player está travando.

Segundo sinal, agora ligado à chave nova: **o teto não acompanha a mensalidade.**
Se empresas no mesmo degrau veem tetos diferentes, ou se trocar de degrau não
muda o que o aluno alcança, a trava está lendo o ticket errado (ou nenhum) — o
`core_media_manager` não está consumindo o plano da empresa.

Sinal comercial herdado: se ninguém sobe de Free para o degrau pago, a trava
não está funcionando como gatilho de upgrade — o teto está generoso demais para
incomodar.
