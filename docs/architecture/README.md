# Arquitetura

| Documento | O que cobre |
|---|---|
| [`decisoes-marketplace.md`](decisoes-marketplace.md) | as decisões estruturais e o porquê de cada uma |
| [`estado-e-proximas-fases.md`](estado-e-proximas-fases.md) | o que existe, o que falta, o que está bloqueado |
| [`estrutura-do-repositorio.md`](estrutura-do-repositorio.md) | o bare repo, as worktrees e o fluxo de branches |
| [`arquitetura-plataforma.svg`](arquitetura-plataforma.svg) | visão geral em diagrama. **Desenhado quando havia um gateway só** — mostra "Checkout Mercado Pago" e "Split Mercado Pago" como se fossem o caminho único |
| [`assinatura-no-mercado-pago.md`](assinatura-no-mercado-pago.md) | **como a assinatura funciona no MP, e por que não se parece com a do Asaas**. Diagramas em Mermaid |

Decisões novas devem virar um [ADR](../adr/) em vez de entrar em
`decisoes-marketplace.md` — assim dá para superar uma sem mexer nas outras.
