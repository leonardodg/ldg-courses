# Marca

Identidade visual da plataforma: logo, paleta, tipografia e os assets que o tema
consome.

## A marca é a LDG, e isso é provisório de propósito

A razão social é **DG Tecnologia**. O **nome fantasia ainda não existe** — a
decisão foi não inventar um "DG Courses" agora, para não amarrar o marketing a um
nome fraco antes de a plataforma existir.

Enquanto isso, tudo é construído sobre a band **LDG / LeoDG**, que é a marca do
desenvolvedor. Quando o nome fantasia for definido, a identidade é refeita do
zero e o tema nasce de novo — por isso o componente se chama `theme_ldg` e não
carrega nome de produto nenhum.

## Design system

| Arquivo | O que é |
|---|---|
| [`DESIGN.md`](DESIGN.md) | o design system do **portal do aluno**, extraído dos mockups: paleta com o papel de cada cor, escala tipográfica, componentes, layout e movimento |
| [`design_system_leodg.md`](design_system_leodg.md) | tokens da band LDG: paleta, tipografia, componentes |
| [`design_system_moove.md`](design_system_moove.md) | o mesmo sistema traduzido para o Moodle e para o `theme_moove`, com frontmatter de tokens |
| [`DESIGN_band_LDG.md`](DESIGN_band_LDG.md) | paleta Material completa, gerada a partir da band |

O `DESIGN.md` é o **contrato visual** do `theme_ldg` e do `format_ldg`: quando a
tela e o documento discordarem, um dos dois está errado. Ele traz a tradução para
os tokens `--ldg-*` do tema e três avisos de contraste **medidos** — o cinza de
rótulo do mockup reprova em AA e precisa subir.

Os mockups de onde ele saiu estão em [`mockups/`](mockups/) — é contra eles que a
tela do portal foi conferida, e é para lá que os planos apontam quando dizem
"a referência".

Resumo dos tokens que o `theme_ldg` implementa: `#007AFF` (primária, hover
`#3394FF`), `#121212` (fundo), `#1E1E1E` (superfície), `#2A2A2A` (elevação 2),
`#3A3B3C` (bordas), `#B0B3B8` (texto secundário), `#8E24AA` (secundária). Fonte
**Inter**. Raio 8px; chips em pill. Navbar 64px no desktop e 50px no mobile.
Escala de espaçamento de 8px, gutter 24px, container de 1440px.

**Dark é o tema, não um modo.** Ver a justificativa em
[`../architecture/estado-e-proximas-fases.md`](../architecture/estado-e-proximas-fases.md):
o dark do Moove é uma *user preference*, e visitante anônimo não tem preference —
a landing e o login sairiam claros para todo o público de captação.

## Assets

| Arquivo | Onde é usado |
|---|---|
| `band_LDG.png` | logo em PNG, fundo escuro |
| `brand-black.svg` | logo vetorial |
| `avatar.png` | páginas sobre a empresa e sobre o desenvolvedor |
| `hero.png` | hero da landing de captação e `og:image` |
| `bg-hero-medium.jpg` | fundo da tela de login (`theme_ldg/loginbg`) |
| `design-system-leodg.jpeg` | referência visual do design system |
| `courses-format-theme*.png` | referência de formato de curso — fase futura |

Os arquivos daqui são a **fonte**. O tema não os lê deste diretório: cópias
otimizadas vivem em `public/theme/ldg/pix/`. Trocar a arte aqui não muda o site
até a cópia ser refeita e os caches, purgados.

## Identidade por empresa vendedora

Independente do tema global. Cada empresa tem logo e CSS próprios como
*fileareas* (`pagelogo`, `pagecss`) e um tema de categoria — ver
[`../data-model/marketplace.md`](../data-model/marketplace.md), campo `themename`.

## O que ainda falta

Logo definitivo, tom de voz e as regras de uso da marca por empresa vendedora.
Tudo isso espera o nome fantasia.
