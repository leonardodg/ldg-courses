# theme/ldg

[Voltar ao índice](README.md)

## 1. `theme_config::load('ldg')` recarregado até 6x por requisição
- **Status:** corrigido
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
- **Correção:** `settings::theme_config()` carrega no máximo uma vez por
  requisição (estático privado); `reset_theme_config()` para teste. Renderer,
  construtor de `settings` e `theme_ldg_pluginfile()` usam o cache.

## 2. Bloco de navegação secundária/more-menu copiado entre layouts
- **Status:** corrigido
- **Arquivo:** `layout/ldgportal.php:65`
- **Achado:** copiado quase verbatim de `layout/drawers.php:100-114` em vez
  de um helper compartilhado chamado pelos dois layouts.
- **Cenário de falha:** uma correção futura em como o more-menu é construído,
  aplicada em um layout mas esquecida no outro, dessincroniza silenciosamente
  os layouts portal e drawers — a mesma classe de bug de cromo inconsistente
  já documentada em `dev/CLAUDE.md`.
- **Correção:** `util\layouthead::secondary_more_menu()` (+ 
  `has_secondary_children()`); portal chama com `requiregovern=true` (trava de
  capability), drawers com a flag `has-secondarynavigation` e o overflow
  continuando locais.

## 3. `sitename` duplicado entre `drawers.php` e `ldgportal.php`
- **Status:** corrigido
- **Arquivo:** `layout/drawers.php:128`
- **Achado:** o valor de template "sitename" (combinação específica de
  `format_string()` com contexto/escape) está duplicado verbatim entre
  `layout/drawers.php` e `layout/ldgportal.php` em vez de um helper
  compartilhado.
- **Cenário de falha:** um ajuste futuro em como o nome do site é
  escapado/contextualizado (ex.: hardening de XSS) corre o risco de ser
  corrigido em um layout e esquecido no outro.
- **Correção:** `util\layouthead::sitename()`; as duas layouts consomem o
  mesmo helper.

## 4. `langmenu::sigla()` duplica algoritmo já existente em `local_partners`
- **Status:** corrigido
- **Arquivo:** `classes/util/langmenu.php:100`
- **Achado:** reimplementa manualmente o mesmo algoritmo de "código curto de
  idioma" que `local_partners\landing_page` já implementa independentemente —
  o próprio docblock de `langmenu.php` diz que é deliberadamente o mesmo
  desenho do seletor da landing, mas a lógica foi escrita duas vezes.
- **Cenário de falha:** se a regra de abreviação do código de idioma precisar
  mudar, só uma das duas cópias independentes tem chance de ser atualizada,
  produzindo inconsistência visível entre a navbar do tema e os seletores da
  landing de parceiros.
- **Correção:** regra única em `landing_page::language_short()` (pública);
  `langmenu::sigla()` delega via `class_exists` (tema não declara dependência
  do plugin — `moodle-plugin-ci` instala o tema sozinho) e mantém a mesma
  fórmula local só nesse caso. Landing usa o método nas duas chamadas.

## 5. Docblock afirma dependência de Moove que não existe
- **Status:** corrigido
- **Arquivo:** `version.php:17`
- **Achado:** o docblock afirma "Tema global da plataforma. Filho do Moove"
  mas `config.php` declara `$THEME->parents = ['boost']` e o próprio
  `$plugin->dependencies` deste arquivo lista só `theme_boost` — Moove não é
  dependência nenhuma.
- **Cenário de falha:** `version.php` é o ponto de entrada natural para
  entender a cadeia de dependência de um tema; um mantenedor confiando nesse
  comentário raciocinaria sobre o tema ancestral errado ao depurar uma
  sobreposição de configuração/logo/renderer.
- **Correção:** docblock e comentário de `dependencies` dizem Boost; o aviso
  de manutenção (reconferir overrides ao subir o pai) permanece, apontando o
  pai real.
