# ADR-0005 — A trava de resolução por ticket, e onde ela precisa ser aplicada

**Situação:** Superada por ADR-0014 · **Data:** 2026-08-31

## Contexto

O modelo comercial é híbrido. No **Starter** a plataforma absorve a
infraestrutura de vídeo e cobra 9,9% por venda, sem mensalidade. No **Pro/Scale**
o produtor conecta a chave de API da própria conta (BYOS), paga mensalidade e
uma comissão reduzida.

No Starter, a única coisa que separa a operação do prejuízo é a **trava dinâmica
de resolução**: cursos até R$ 49,90 sobem no máximo em 720p, até R$ 200,00 em
1080p, e acima disso o 4K é liberado. Um curso de R$ 30 servido em 4K consome
banda de curso caro e rende comissão de curso barato.

O dado já existe. A Fase 3 criou `local_marketplace_plan_tier`, e
`plan::max_resolution_for(float $price)` devolve a resolução máxima de um
ticket. **Nada consome esse método ainda** — não há player. Hoje a trava é uma
promessa registrada no banco.

Duas restrições do produto de vídeo moldam a decisão, e elas puxam para lados
opostos:

**O streaming é adaptativo.** Bunny e Cloudflare entregam HLS/DASH: um único
manifesto `.m3u8` com todas as trilhas, de 360p a 4K. Não existe "entregar só
720p" sem intervir — ou no player, ou na origem.

**O modelo de cobrança dos dois provedores é diferente na raiz.** O Bunny cobra
por **gigabyte transferido**: 4K custa várias vezes mais que 720p. O Cloudflare
cobra por **minuto assistido**, independente da resolução: 4K e 720p custam o
mesmo.

## Decisão

A trava é aplicada em **dois níveis, e o nível obrigatório depende do provedor**:

- **No player (front-end).** O player lê a regra do ticket e esconde as trilhas
  acima do teto. É o que o aluno vê, e vale para os dois provedores.
- **Na origem (link assinado com restrição de resolução máxima).** Obrigatório
  quando o provedor cobra por volume.

Concretamente: com **Cloudflare**, o bloqueio no player basta. Com **Bunny**, o
link assinado é obrigatório e o player é só cosmético.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Só bloqueio no player, para os dois provedores | Bloqueio em JavaScript é contornável em trinta segundos: abrir o devtools, pegar o `.m3u8`, tocar a trilha 4K. No Cloudflare isso não custa nada a mais, porque a cobrança é por minuto. No Bunny, custa — e a trava deixa de proteger exatamente o que ela existe para proteger |
| Só link assinado na origem, sem mexer no player | Funciona, mas o seletor de qualidade do player continua oferecendo 4K e falhando ao selecionar. O aluno lê isso como defeito da plataforma, e não como limite do plano |
| Transcodificar só até o teto no upload | Torna o teto **permanente**: se o produtor sobe o preço do curso depois, o 4K não existe para reprocessar sem novo upload. O teto é do ticket, e o ticket muda |
| Não ter trava, e cobrar por consumo | Transfere ao produtor um custo que ele não controla nem entende, e destrói a proposta "risco zero" do Starter |

## Consequências

Fica mais fácil: a regra de negócio já está em dado consultável, e a mesma
`max_resolution_for()` serve ao player, ao link assinado e à comparação de
planos na landing — uma fonte só.

Fica mais difícil: **a escolha do provedor deixa de ser detalhe de
infraestrutura e vira decisão de produto.** Trocar Bunny por Cloudflare depois
de construir só o bloqueio de player é seguro; o caminho inverso não é, e a
migração exigiria implementar o link assinado antes de mover um vídeo sequer.

E fica uma dívida explícita: o `plan.hostingmodel` hoje é **rótulo**. Não há
onde o produtor guardar a chave da Bunny/Cloudflare, nem código que troque o
destino do upload conforme o plano. Enquanto isso não existir, o BYOS é uma
promessa comercial sem a peça técnica, e vender o Pro/Scale antes disso é vender
o que não se entrega.

## Como saber que erramos

O sinal é o custo de banda do Starter crescer mais rápido que a receita de
comissão dele. Se o custo por aluno ativo subir enquanto o ticket médio fica
parado, a trava não está sendo aplicada onde deveria — provavelmente o bloqueio
está só no player e o provedor cobra por volume.

O segundo sinal é comercial: se ninguém migra do Starter para o Pro, a trava
não está funcionando como gatilho de upgrade, e o teto está generoso demais para
incomodar.
