# theme/ldg

[Voltar ao índice](README.md)

## 1. `theme_config::load('ldg')` recarregado até 6x por requisição
- **Status:** pendente
- **Arquivo:** `classes/output/core_renderer.php:80`
- **Achado:** `theme_config::load('ldg')` é chamado independentemente em
  `standard_head_html()`, `get_theme_logo_url()`, `get_theme_logo_dark_url()`,
  `favicon()`, e mais duas vezes dentro de duas instanciações novas de
  `new settings()` — seis recargas da mesma config de tema no caminho de
  código mais quente do projeto, sem cache estático.
- **Cenário de falha:** `theme_config::load()` reexecuta `get_config()` e a
  resolução completa da cadeia de temas-pai a cada chamada, até 6 vezes por
  requisição em vez de uma, em toda visualização de página do site, logado e
  anônimo.
- **Correção:**

## 2. Bloco de navegação secundária/more-menu copiado entre layouts
- **Status:** pendente
- **Arquivo:** `layout/ldgportal.php:65`
- **Achado:** copiado quase verbatim de `layout/drawers.php:100-114` em vez
  de um helper compartilhado chamado pelos dois layouts.
- **Cenário de falha:** uma correção futura em como o more-menu é construído,
  aplicada em um layout mas esquecida no outro, dessincroniza silenciosamente
  os layouts portal e drawers — a mesma classe de bug de cromo inconsistente
  já documentada em `dev/CLAUDE.md`.
- **Correção:**

## 3. `sitename` duplicado entre `drawers.php` e `ldgportal.php`
- **Status:** pendente
- **Arquivo:** `layout/drawers.php:128`
- **Achado:** o valor de template "sitename" (combinação específica de
  `format_string()` com contexto/escape) está duplicado verbatim entre
  `layout/drawers.php` e `layout/ldgportal.php` em vez de um helper
  compartilhado.
- **Cenário de falha:** um ajuste futuro em como o nome do site é
  escapado/contextualizado (ex.: hardening de XSS) corre o risco de ser
  corrigido em um layout e esquecido no outro.
- **Correção:**

## 4. `langmenu::sigla()` duplica algoritmo já existente em `local_partners`
- **Status:** pendente
- **Arquivo:** `classes/util/langmenu.php:100`
- **Achado:** reimplementa manualmente o mesmo algoritmo de "código curto de
  idioma" que `local_partners\landing_page` já implementa independentemente —
  o próprio docblock de `langmenu.php` diz que é deliberadamente o mesmo
  desenho do seletor da landing, mas a lógica foi escrita duas vezes.
- **Cenário de falha:** se a regra de abreviação do código de idioma precisar
  mudar, só uma das duas cópias independentes tem chance de ser atualizada,
  produzindo inconsistência visível entre a navbar do tema e os seletores da
  landing de parceiros.
- **Correção:**

## 5. Docblock afirma dependência de Moove que não existe
- **Status:** pendente
- **Arquivo:** `version.php:17`
- **Achado:** o docblock afirma "Tema global da plataforma. Filho do Moove"
  mas `config.php` declara `$THEME->parents = ['boost']` e o próprio
  `$plugin->dependencies` deste arquivo lista só `theme_boost` — Moove não é
  dependência nenhuma.
- **Cenário de falha:** `version.php` é o ponto de entrada natural para
  entender a cadeia de dependência de um tema; um mantenedor confiando nesse
  comentário raciocinaria sobre o tema ancestral errado ao depurar uma
  sobreposição de configuração/logo/renderer.
- **Correção:**
