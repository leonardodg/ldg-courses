# Portal do aluno — Plano 4: conferência visual, dados e acessibilidade

> **Para quem executa:** use `superpowers:executing-plans`. Os passos usam `- [ ]`.

**Meta:** provar que o portal **bate com a referência** que você mandou — por
medida, não por impressão —, e fechar os planos 1 a 4 com documentação.

**Arquitetura:** nada de código novo de produto. O trabalho é uma sonda de
medição no Chrome, dados de teste que tornem a comparação justa, e a
documentação.

**Pilha:** Chrome headless pelo DevTools Protocol, `axe-core` 4.10.3 (já
empacotado no Moodle), PHPUnit, Behat.

Design system: [`../brand/DESIGN.md`](../brand/DESIGN.md) ·
Planos anteriores: [`1`](2026-09-03-portal-plano-1-chrome-e-layout.md) ·
[`2`](2026-09-03-portal-plano-2-destinos-e-catalogo.md) ·
[`3`](2026-09-03-portal-plano-3-marca-e-tipografia.md)

## Restrições globais

- Worktree `format-course-ldg`, branch `fix/format-ldg-navegador`, stack offset 0.
- **A sonda mora no scratchpad da sessão, nunca dentro do `public/`.** Arquivo de
  nome curto na raiz do Moodle sobrescreve arquivo do core — já aconteceu aqui.
- Chrome sobe **em segundo plano de verdade** (`run_in_background`), senão morre
  junto com a chamada que o lançou.
- Medida antes de opinião: número primeiro, screenshot depois.
- Comentários em português sem acentos; documentação com acentos.
- Nada de push.

## O que já está levantado

- **`axe-core` está empacotado**: `public/lib/behat/axe/axe.min.js`, versão
  4.10.3. Dá para injetar do disco na página — sem rede, sem dependência nova.
- **O `make_testdata.php` já monta um curso** com seção de abertura, três módulos
  de aulas, `mod_url`, quiz, banco de questões e um `customcert` **trancado**.
- **Falta fórum.** Por isso o curso de demonstração hoje mostra só três dos
  quatro destinos — dá para ver no `?ldgview=forum`, que cai em Aulas.
- **Viewports da referência:** os PNGs são 2560×2764 e 780×2938, que a 2× dão
  **1280** e **390** CSS. São esses os dois tamanhos da comparação.

---

### Tarefa 1: os dados que tornam a comparação justa

**Arquivos:**
- Modificar: `public/course/format/ldg/cli/make_testdata.php`

Comparar tela cheia com tela quase vazia não prova nada. O curso precisa exercer
**os quatro destinos** e os **estados** que o desenho mostra: aula concluída, aula
em andamento, aula disponível e módulo trancado.

- [x] **Passo 1: acrescentar o fórum**

Depois do bloco do quiz, antes do certificado:

```php
// O forum e o quarto destino do portal. Sem ele, o curso de demonstracao
// mostra so tres, e a conferencia visual compararia uma tela que nao existe
// no desenho.
$forum = $generator->get_plugin_generator('mod_forum')->create_instance([
    'course' => $course->id,
    'section' => 3,
    'name' => 'Duvidas da turma',
    'intro' => 'Onde a turma pergunta e responde.',
    'completion' => COMPLETION_TRACKING_MANUAL,
]);
```

- [x] **Passo 2: acrescentar material de verdade**

O `mod_url` já existe e conta como material, mas o desenho mostra **arquivo para
baixar** — que é o caso que não pode abrir no quadro. Um `mod_folder` e um
`mod_resource` cobrem os dois ramos da regra:

```php
// Dois materiais que caem em ramos DIFERENTES da regra do miolo: a pasta tem
// pagina propria e abre no quadro; o arquivo com download forcado vira link,
// porque dentro de um iframe o download deixa a tela em branco.
$generator->get_plugin_generator('mod_folder')->create_instance([
    'course' => $course->id,
    'section' => 2,
    'name' => 'Anexos do modulo 2',
]);

$generator->get_plugin_generator('mod_resource')->create_instance([
    'course' => $course->id,
    'section' => 2,
    'name' => 'Apostila em PDF',
    'display' => RESOURCELIB_DISPLAY_DOWNLOAD,
]);
```

Com `require_once($CFG->libdir . '/resourcelib.php')` no topo do arquivo.

- [x] **Passo 3: rodar e conferir os quatro destinos**

```bash
docker exec -u 1000:33 -w /var/www/html courses-free-moodle-1 \
  php public/course/format/ldg/cli/make_testdata.php --run --reset < /dev/null
```

Depois, na página, conferir que os quatro `ldg-portal__navitem--*` aparecem.

- [x] **Passo 4: commitar**

---

### Tarefa 2: a sonda de medidas

**Arquivos:**
- Criar (scratchpad): `probe_visual.js`

Mede o que o `DESIGN.md` define, nos **dois viewports** e nos **dois modos**.
Cada linha vira número e veredito.

- [x] **Passo 1: escrever a sonda**

```js
// Sonda visual do portal. Mede DEPOIS do JS e das fontes, que e o unico jeito
// de comparar com o desenho - o layout so existe depois que o player.js mede o
// quadro e as fontes trocam a metrica do texto.
const CDP = require('chrome-remote-interface');

const ALVOS = {
    desktop: {width: 1280, height: 900},
    celular: {width: 390, height: 844},
};

async function medir(url, sess, viewport, modo) {
    const client = await CDP();
    const {Page, Runtime, Emulation, Network} = client;
    await Page.enable(); await Runtime.enable(); await Network.enable();
    await Network.setCookie({name: 'MoodleSession', value: sess, domain: 'localhost', path: '/'});
    await Emulation.setDeviceMetricsOverride({...viewport, deviceScaleFactor: 2, mobile: viewport.width < 700});
    await Page.navigate({url});
    await Page.loadEventFired();

    const expr = `
        (async () => {
            await document.fonts.ready;
            document.body.setAttribute('data-bs-theme', '${modo}');
            await new Promise(r => setTimeout(r, 400));

            const cx = (s) => document.querySelector(s);
            const larg = (s) => { const e = cx(s); return e ? Math.round(e.getBoundingClientRect().width) : null; };
            const alt = (s) => { const e = cx(s); return e ? Math.round(e.getBoundingClientRect().height) : null; };
            const visivel = (s) => { const e = cx(s); return !!e && getComputedStyle(e).display !== 'none'; };
            const token = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

            // A altura do quadro tem que ser ESTAVEL: o bug de 02/09 foi ela
            // crescer a cada ciclo do ResizeObserver.
            const frame = cx('.ldg-lesson__frame');
            const h1 = frame ? Math.round(frame.getBoundingClientRect().height) : null;
            await new Promise(r => setTimeout(r, 1500));
            const h2 = frame ? Math.round(frame.getBoundingClientRect().height) : null;

            return JSON.stringify({
                cabecalho: alt('.ldg-portal__header'),
                menu: visivel('.ldg-portal__nav') ? larg('.ldg-portal__nav') : null,
                indice: visivel('.ldg-lessonlist') ? larg('.ldg-lessonlist') : null,
                abas: visivel('.ldg-portal__tabs'),
                barraBaixo: visivel('.ldg-portal__bottomnav'),
                alturaBarra: alt('.ldg-portal__bottomnav'),
                quadro: {primeira: h1, depois: h2, estavel: h1 === h2},
                fundo: getComputedStyle(document.body).backgroundColor,
                tokens: {
                    bg: token('--ldg-bg'), surface: token('--ldg-surface'),
                    border: token('--ldg-border'), accent: token('--ldg-accent'),
                    label: token('--ldg-text-label'),
                },
                rolagemHorizontal: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            });
        })()
    `;
    const {result} = await Runtime.evaluate({expression: expr, awaitPromise: true, returnByValue: true});
    await client.close();
    return JSON.parse(result.value);
}
```

Com um `main` que percorre `[desktop, celular] x [dark, light]` e imprime a
tabela.

- [x] **Passo 2: rodar e comparar com o `DESIGN.md`**

| Medida | Alvo | Onde vale |
|---|---|---|
| Altura do cabeçalho | 56px | os dois viewports |
| Menu | 280px | ≥ 992 |
| Índice | 360px | ≥ 1200 |
| Abas | visíveis | < 992 |
| Barra de baixo | visível, 60px | < 992 |
| Menu, abas, barra | menu ausente / barra ausente | ≥ 992 |
| Quadro da aula | altura **estável** entre duas leituras | os dois |
| Rolagem horizontal | **nunca** | os dois |
| Tokens | os do `DESIGN.md` por modo | os dois |

Qualquer divergência é defeito **do código**, não da medida — corrigir e medir de
novo.

- [x] **Passo 3: commitar o que a medição corrigir**

---

### Tarefa 3: o lado a lado com a sua referência

- [x] **Passo 1: renderizar o mockup no mesmo Chrome**

O `portal-aluno-version-bootstrip-responsive.html` é aberto no **mesmo** Chrome,
**mesmo** viewport e **mesma** escala. Comparar com o PNG seria comparar com
outro motor de renderização.

- [x] **Passo 2: quatro capturas**

`Page.captureScreenshot` com `captureBeyondViewport`, em 1280 e 390, do portal e
da referência. Arquivos no scratchpad.

- [x] **Passo 3: entregar as capturas**

As quatro imagens vão para você comparar — é aqui que "chegar próximo" deixa de
ser número e vira julgamento seu, que é o certo para tipografia, respiro e
hierarquia.

---

### Tarefa 4: acessibilidade com axe-core

- [x] **Passo 1: injetar o axe do disco**

```js
const axe = require('fs').readFileSync(
    '/home/leodg/localhost/gitworktree-bare-moodle/format-course-ldg/public/lib/behat/axe/axe.min.js',
    'utf8'
);
await Runtime.evaluate({expression: axe});
const {result} = await Runtime.evaluate({
    expression: `axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa']}})
        .then(r => JSON.stringify(r.violations.map(v => ({
            id: v.id, impacto: v.impact, nos: v.nodes.length,
            alvo: v.nodes[0] && v.nodes[0].target,
        }))))`,
    awaitPromise: true, returnByValue: true,
});
```

- [x] **Passo 2: rodar nos dois modos e nos quatro destinos**

Critério: **zero** violações de impacto `critical` ou `serious`. O que sobrar de
`moderate`/`minor` entra no commit, com decisão explícita — consertar ou aceitar
com motivo.

Os três `<nav>` da mesma página são o candidato natural a reprovar: por isso cada
um recebeu `aria-label` no plano 2.

- [x] **Passo 3: consertar e commitar**

---

### Tarefa 5: documentação e fechamento

- [x] **Passo 1: `format_ldg/README.md`** — os quatro destinos, a regra de
  classificação, a regra do material que baixa, e o `styles.css` estrutural.
- [x] **Passo 2: `theme_ldg/README.md`** — o layout `ldgportal`, as fontes
  empacotadas com a licença, os tokens novos com o contraste medido, e o aviso
  de que grade não se escreve no `_format.scss`.
- [x] **Passo 3: `docs/brand/DESIGN.md`** — marcar o implementado e **corrigir o
  que o código provou diferente**: o cinza de rótulo que reprovou, o
  `--ldg-accent-fill` que precisou existir, e os 16/9 que ficaram para o
  `mod_video`.
- [x] **Passo 4: `docs/dev/`** — o documento do portal, ligado pelo
  `docs/README.md`, com o roteiro da conferência visual para você repetir sem mim.
- [x] **Passo 5: versões dos dois plugins, e o registro dos planos.**

## Executado em 03/09/2026

Commits `8aea1e507e7` a `4e75d11fc5f`, mais a documentação.

**Medido:**

| Medida | Alvo | Resultado |
|---|---|---|
| Cabeçalho | 56px | **56** nos quatro cenários |
| Menu / índice | 280 / 360 | **280 / 360** no desktop |
| Abas e barra | só < 992 | corretas; barra **60px** |
| Quadro da aula | estável | **444/444** |
| Rolagem horizontal | nunca | **nenhuma** |
| Fontes | Inter + JetBrains | **carregadas** |
| axe-core (`wcag2a`+`wcag2aa`) | zero graves | **zero violações**, de qualquer impacto, nos 4 destinos × 2 modos |
| PHPUnit / Behat / phpcs | verdes | 53 testes / 12 cenários, 113 passos / `EXIT=0` |

**Três defeitos que só a medição achou:**

1. **Cabeçalho com 122px** em vez de 56. Ao separar estrutura de marca no plano 3
   eu tirei `display:flex` e `height` do `&__header` e não os recoloquei. Voltaram
   para o **tema**: o cabeçalho é desenhado pelo layout `ldgportal`, então a
   estrutura dele é de lá. A regra "estrutura no formato" vale para o **miolo**.
2. **"Fechar aula" caindo para fora do cabeçalho**, sobre o conteúdo — o
   container de ações não tinha `flex`. A medida dizia 56px e a tela dizia outra
   coisa; por isso a captura vem **depois** do número, e não no lugar dele.
3. **Conteúdo encostando na borda** da janela: o portal não tem container do
   Moodle em volta, e foi isso que tirou a navbar.

**E um bug de lógica que montar os dados expôs:** `url_get_final_display_type()`
põe `text/html` na lista de download, então **todo `mod_url` para uma página web
resolve para `DISPLAY_DOWNLOAD`** — ali aquilo significa "manda o navegador para
a URL". "Leitura complementar" aparecia como "(baixa o arquivo)" e ganhava o
atributo `download`, que faria o navegador tentar salvar a página.

**Refinei a própria sonda:** ela lia o quadro duas vezes e acusava crescimento
onde havia estabilização — a primeira leitura pega o `min-height` inicial, antes
de o `player.js` medir. Agora são três, e o que vale é a segunda ser igual à
terceira.

**Ambiente:** o curso de demonstração foi recriado (agora id 15), com fórum,
pasta e PDF.

## Como saber que o plano 4 acabou

1. Os quatro destinos existem no curso de demonstração.
2. Todas as medidas batem com o `DESIGN.md`, nos dois viewports e dois modos.
3. Zero violação `critical`/`serious` no axe-core.
4. As quatro capturas entregues e aprovadas por você.
5. `phpunit`, `behat` e `phpcs` verdes.
6. Documentação atualizada, incluindo o que o código provou diferente do desenho.
