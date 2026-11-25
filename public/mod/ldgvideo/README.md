# mod_ldgvideo

Aula em **vídeo hospedado fora da plataforma**: o professor cola o endereço, e a
atividade desenha o player no tamanho que a tela dá.

## Para que serve

Antes disto, uma aula em vídeo era um `mod_page` com o `<iframe>` do YouTube
colado na descrição. Funcionava, e era o problema: o vídeo não era um campo —
era HTML livre do professor, sem validação de endereço, sem proporção, e com o
`width="560" height="315"` fixo que o botão *Incorporar* entrega.

Este plugin **é a peça do plano Free**: R$ 0, 0% de comissão, hospedagem externa
por embed, **custo de infraestrutura zero**. O vídeo do plano pago é outro
plugin, com upload e link assinado — ver a
[ADR-0005](../../../docs/adr/0005-trava-de-resolucao-por-ticket.md).

## O campo aceita o que o YouTube te deu

Cole qualquer uma destas coisas:

| O que você cola | O que fica guardado |
|---|---|
| `<iframe width="560" height="315" src="…/embed/ID?si=…"…>` | `https://www.youtube.com/watch?v=ID` |
| `https://youtu.be/ID?si=…` | `https://youtu.be/ID` |
| `https://www.youtube.com/shorts/ID` | `…/watch?v=ID`, e a proporção já vem **9:16** |
| `https://player.vimeo.com/video/123` | `https://vimeo.com/123` |

**Extrair, e não avisar.** Dizer "não cole assim" transferiria ao professor um
trabalho que a máquina faz melhor — e, se ele colasse mesmo assim, o aviso não
teria impedido nada.

Do que é colado sobrevive **só o endereço**. O HTML nunca é guardado nem
ecoado, e é isso que fecha o XSS entre inquilinos da regra do `CLAUDE.md`
("campos, não HTML livre"). Os parâmetros de rastreio (`si`, `pp`, `utm_*`) são
descartados; o `list` da playlist fica, porque muda o que o aluno assiste.

## O tamanho: proporção manda, pixel não existe

É a razão de o plugin ter CSS próprio.

O `core_media_manager` desenha o player — e desenha bem, menos o tamanho:
`media/player/youtube/templates/embed.mustache` emite `width` e `height` **em
pixel fixo**. Num portal de curso, onde a coluna muda de largura conforme o
dispositivo e conforme o aluno esconde as laterais, pixel fixo é o defeito.

A troca é: **a largura vem sempre da coluna, e a altura sai da proporção.** Sem
detecção de dispositivo, sem JavaScript e sem media query — as três situações
caem da mesma regra, porque as três só mudam a largura disponível:

| Situação | O que acontece |
|---|---|
| Desktop, laterais abertas | o vídeo ocupa o que sobra entre as colunas |
| Desktop, laterais escondidas | a grade do portal colapsa e o vídeo **alarga sozinho** |
| Celular | largura total, altura proporcional |

Medido no Chrome em 04/09/2026: numa janela de 1440px o quadro mede **1022px**,
e não os 560 do atributo do core.

O professor escolhe só a **proporção** — 16:9, 4:3 ou 9:16. Se o trecho colado
trazia `width`/`height`, ela já vem escolhida a partir dali; se ele mexer no
campo, a escolha dele vence.

## O que precisa configurar

**Nada, para o YouTube.** Ele vem habilitado.

**Para as outras plataformas, uma vez:** *Administração do site → Plugins →
Players de mídia*. As plataformas aceitas por este plugin são exatamente as dos
players habilitados ali — **não há lista de plataformas neste código**. Instalar
ou ligar um player novo passa a valer aqui na hora.

> **Numa instalação padrão do Moodle, só `videojs` e `youtube` vêm ligados** —
> o `media_vimeo` está lá e desligado. Neste site os cinco foram habilitados em
> 06/09/2026.
>
> Quando um player está desligado, o formulário **diz isso**: um endereço
> legítimo de um serviço instalado mas desligado recebe *"o player dele está
> desligado neste site; peça a um administrador…"*, e não a mensagem genérica de
> endereço inválido. As duas eram a mesma até 06/09/2026, e a genérica mandava o
> professor conferir um link que já estava certo.

**Recomendado:** ligar o `nocookie` do `media_youtube`. O domínio normal grava
rastreio **antes** de o aluno apertar play, e a troca não custa nada nem muda o
player.

## Armadilhas

**Nada de `vh` dentro desta página.** O `player.js` do `format_ldg` **encolhe o
quadro para zero antes de medir** a altura da atividade embutida — foi assim que
ele saiu de um ciclo de realimentação que levou o quadro de 420px a 12084px em
02/09/2026. Com o quadro em zero, qualquer `100vh` aqui dentro vira zero e o
vídeo some. O `aspect-ratio` é imune porque deriva da **largura**.

**Tela cheia depende de dois iframes.** O do YouTube fica dentro do nosso, que
fica dentro do quadro do portal. Os dois precisam de `allowfullscreen`, e a
política de permissão não é herdada por padrão — o sintoma de faltar é um botão
que não faz nada.

**O endereço de incorporação não é o que o core reconhece.** Os regex dos
players do core cobrem o endereço que se copia da **barra de endereços**, e não
o que vai no `src` do trecho: o `media_youtube` casa `watch?v=` e `youtu.be/`, e
**não** casa `/embed/` nem `/shorts/`. Por isso `\mod_ldgvideo\url::canonicalizar()`
reescreve quatro caminhos. Ela **não decide** se algo é vídeo — isso continua
sendo do `can_embed_url()`.

**A conclusão padrão não é `settings.php`.** O "marcar manualmente como feito"
vem de uma linha em `course_completion_defaults`, semeada no `db/install.php`
para o curso do site. É **default**, e não trava: o admin muda em *Padrões de
conclusão*.

## O que o professor não consegue fazer, e é de propósito

Quem monta curso por uma empresa **não coloca arquivo no site**: o papel dele
tem `CAP_PROHIBIT` em todo caminho para o `moodledata`. Sem arquivo local, não
há vídeo nosso para servir — é o que mantém o custo do plano Free em zero. Ver a
[ADR-0009](../../../docs/adr/0009-papeis-de-empresa-sem-upload.md) e o
[README do `local_marketplace`](../../local/marketplace/README.md).

Este plugin faz a outra metade: recusa um endereço do próprio `wwwroot`. Aquele
portão protege contra a intenção; este, contra o engano.

## Estrutura

```
classes/url.php        o que foi colado vira endereco; a regra dos dois portoes
classes/external.php   as chamadas do aplicativo movel
db/install.php         semeia "marcar manualmente" como padrao do site
lib.php                ciclo de vida, visita e conclusao
mod_form.php           o campo, a validacao e a limpeza antes de gravar
view.php               chama o core_media_manager e envolve na caixa de proporcao
styles.css             O TAMANHO - a unica coisa que o core erra
```

## Testes

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --testsuite mod_ldgvideo_testsuite
```

O Behat precisa do Chrome, porque três cenários **medem o quadro na tela** — e
essa é a única prova de que o `aspect-ratio` continua vencendo o `width` fixo do
core:

```bash
moodev up --full          # sobe o Selenium
docker exec -d -u 1000:33 ldg-courses-moodle-1 \
  sh -c 'cd /var/www/html/public && php -S 0.0.0.0:8000 >/tmp/behatweb.log 2>&1'
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --profile=chrome --tags "@mod_ldgvideo"
```

> **O `--profile=chrome` não é opcional.** Sem ele o behat usa o driver sem
> navegador, procura Selenium em `localhost:4444` e os cenários `@javascript`
> morrem com erro de conexão — que parece problema de ambiente, e é só o perfil
> errado.
