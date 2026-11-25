# ADR-0008 — O embed multiplataforma é do core, e a fronteira é "menos o próprio site"

**Situação:** Aceita · **Data:** 2026-09-04

## Contexto

O plano Free existe para custar **zero de banda**: o vídeo é embed de serviço
externo, e a plataforma nunca serve o arquivo. O `mod_ldgvideo` é a
implementação disso.

O recorte original dizia "exclusivo para vídeo do YouTube". Isso não sobreviveu
ao primeiro contato com o produto: o próprio plano Free já nomeia
"YouTube/Vimeo por embed", e amarrar o plugin a uma plataforma faria cada
serviço novo virar código e deploy.

Três fatos do Moodle 5.2, verificados em 04/09/2026, moldaram a decisão:

**O core já resolve o reconhecimento.** `public/media/classes/manager.php` tem
`can_embed_url()` e `embed_url()`, e o site traz cinco players: `youtube`,
`vimeo`, `videojs`, `html5video`, `html5audio`. O `media_youtube` sozinho cobre
`watch?v=`, `v/`, `youtu.be/`, playlist, tempo de início, e tem configuração
`nocookie` própria.

**O core erra o tamanho.** `media/player/youtube/templates/embed.mustache` emite
`width` e `height` em **pixel fixo** — os mesmos `560x315` do botão *Incorporar*.

**Os regex do core não cobrem o endereço de incorporação.** Eles casam o que se
copia da barra de endereços. O `media_youtube` **não** casa `/embed/` nem
`/shorts/`; o `media_vimeo` não casa `player.vimeo.com/video/123`. Ou seja: a
forma que o professor mais cola é justamente a que o core recusaria.

## Decisão

**O reconhecimento e o embed são do `core_media_manager`.** O plugin não tem
regex de plataforma para decidir se um endereço é vídeo.

**Guarda-se a URL, e não `videoid` + `provider`.** Quem resolve o endereço é o
core, no momento de desenhar. Guardar o player escolhido congelaria a decisão de
hoje.

**O plugin corrige o tamanho, por CSS.** Uma caixa com `aspect-ratio` e o
elemento a 100% — os `width`/`height` do core vêm como **atributo**, e atributo
perde para CSS sem precisar de `!important`. A regra cobre `iframe`, `video` e
`.video-js`, porque o `media_videojs` desenha um `<video>` e não um quadro.

**Há uma tabela de quatro canonicalizações**, em `url::canonicalizar()`:
`/embed/ID`, `/shorts/ID` e `/v/ID` do YouTube viram `watch?v=ID`, e
`player.vimeo.com/video/N` vira `vimeo.com/N`. Ela **não decide** se algo é
vídeo — só escreve a mesma mídia na forma que o core sabe ler.

**A fronteira é: qualquer player habilitado, menos o próprio `wwwroot`.** Um
endereço do nosso site é recusado com mensagem própria.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Regex próprio de YouTube no plugin | Segunda fonte de verdade do mesmo padrão, que diverge do core no primeiro upgrade. E o plugin deixaria de ganhar de graça toda plataforma que o site aprender a embutir |
| Lista fechada YouTube + Vimeo no código | Cada plataforma nova vira código, PR e deploy. E duplicaria uma decisão que já é do admin, na tela de Players de mídia |
| Sem canonicalização, pedindo o link da barra de endereços | O professor cola o trecho `<iframe>`, que é o que o botão *Incorporar* entrega. O caso principal do plugin simplesmente não funcionaria |
| Sem a guarda do `wwwroot` | O professor sobe um `.mp4` num rótulo do curso, copia o link do `pluginfile.php` e cola no campo. Vídeo servido pela **nossa** banda, no plano de 0% de comissão |
| Corrigir o tamanho com JavaScript | O `format_ldg` já mede a altura do quadro embutido, e um segundo medidor brigaria com aquele. `aspect-ratio` deriva da largura e não participa da medida |

## Consequências

**Fica mais fácil:** o conjunto de plataformas aceitas passa a ser
**configuração de site**, e não código. Ligar o Vimeo, ou instalar um player de
Dailymotion, vale no plugin na hora.

**Fica mais difícil, e é o preço:** *desligar* um player de mídia quebra, em
silêncio, todas as **atividades já salvas** que dependiam dele — elas param de
abrir sem que nada avise.

No momento de **criar** a atividade, isso deixou de ser silencioso em
06/09/2026: se um player instalado reconheceria o endereço mas está desligado, o
formulário diz que o problema é configuração do site e não o link. Antes as duas
situações davam a mesma mensagem, e a genérica mandava o professor conferir um
endereço que já estava correto — foi o que aconteceu com o `media_vimeo`, que
vem desligado numa instalação padrão.

E fica uma dívida pequena e explícita: a tabela de canonicalização conhece
YouTube e Vimeo pelo nome. Ela envelhece se uma dessas plataformas mudar o
formato do embed, e nada avisaria além de um teste falhando.

## Como saber que erramos

O sinal é o suporte receber "colei o link e ele diz que não é vídeo" para uma
plataforma **com player habilitado**: aí a tabela de canonicalização ficou para
trás, e o endereço de incorporação daquela plataforma mudou de formato.

Se a queixa vier acompanhada da mensagem sobre player desligado, não há erro
nenhum — é o sistema funcionando, e a ação é do administrador.

O segundo é uma atividade **já salva** que parou de abrir: alguém desligou um
player que estava em uso, e nada avisa nesse caminho — a mensagem nova só cobre
o momento de criar.

O terceiro é o custo de armazenamento crescer sem que ninguém tenha mudado o
plano: quer dizer que algum caminho para o `moodledata` foi reaberto, e aí o
documento a revisitar é a [ADR-0009](0009-papeis-de-empresa-sem-upload.md).
