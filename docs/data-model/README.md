# Modelo de dados

| Documento | O que cobre |
|---|---|
| [`marketplace.md`](marketplace.md) | as tabelas dos cinco plugins, campo a campo, e o diagrama ER |

## `mod_ldgvideo`

Uma tabela, `ldgvideo`, uma linha por aula em vídeo:

| Campo | O que guarda |
|---|---|
| `videourl` | **a fonte da verdade.** A URL canônica, já normalizada e sem rastreio |
| `aspectratio` | `16:9`, `9:16` ou `4:3`. A largura vem sempre da coluna; só a forma é escolhida |
| `displayoptions` | serializado, como no `mod_page` — guarda o `printintro` |

**Não há `videoid` nem `provider`, e é de propósito.** Quem resolve o endereço é
o `core_media_manager` na hora de desenhar; guardar o player escolhido
congelaria a decisão do dia em que a atividade foi criada, e um vídeo salvo
antes de o site ganhar um player novo continuaria preso ao antigo.

E não há **área de arquivo**: o vídeo mora fora da plataforma, que é a razão de
o plugin existir. Ver a [ADR-0008](../adr/0008-embed-multiplataforma-pelo-core.md).

A regra central: **o direito de acesso é a única fonte da verdade**. Matrícula e
liberação de seção consultam `local_marketplace_entitlement`; ninguém lê a venda
para decidir acesso.

A credencial de pagamento **não vive aqui** — fica em `payment_gateways.config`
do core. A tabela `local_marketplace_mpaccount` existiu e foi removida
justamente por criar uma segunda fonte de verdade para credencial financeira.
