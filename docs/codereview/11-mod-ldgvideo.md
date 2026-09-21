# mod/ldgvideo

[Voltar ao índice](README.md)

## 1. Canonicalização do Vimeo checa host errado (não stripado)
- **Status:** pendente
- **Arquivo:** `classes/url.php:384`
- **Achado:** checa `$partes['host']` cru para igualdade exata com
  `player.vimeo.com`, em vez de reusar o `$host` sem www/m já calculado duas
  linhas acima para o ramo do YouTube.
- **Cenário de falha:** um professor cola um src de embed do Vimeo prefixado
  com "www." (ex.: `https://www.player.vimeo.com/video/12345`) — variante real
  produzida por alguns proxies corporativos/clientes de email.
  `$partes['host'] === 'player.vimeo.com'` é falso, `canonicalizar()` retorna
  a URL sem alteração, o regex `media_vimeo` do core também não reconhece
  essa forma com subdomínio "player", e o professor recebe
  "erroraddressnotvideo" para um vídeo Vimeo legítimo e funcional.
- **Correção:**

## 2. Regex do YouTube casa com "videoseries" de embed de playlist
- **Status:** pendente
- **Arquivo:** `classes/url.php:376`
- **Achado:** o regex de canonicalização embed/shorts/v também casa com o
  segmento literal "videoseries", que o snippet de embed de playlist do
  YouTube usa no lugar de um id de vídeo real
  (`.../embed/videoseries?list=PLxxxx`).
- **Cenário de falha:** um professor usa "Compartilhar > Incorporar" numa
  playlist (natural para uma aula em várias partes) e cola o src do iframe
  resultante. `canonicalizar()` captura "videoseries" como se fosse um id de
  vídeo e reescreve para `.../watch?v=videoseries&list=PL...`, apontando para
  um vídeo inexistente/errado — salvo silenciosamente sem erro de validação,
  já que "videoseries" ainda passa no regex do YouTube de `can_embed_url()`
  como id sintaticamente válido.
- **Correção:**

## 3. Detecção de endereço self-hosted é prefixo de string, não fronteira de host
- **Status:** pendente
- **Arquivo:** `classes/url.php:189`
- **Achado:** `problem()` detecta endereço self-hosted com um simples
  `str_starts_with` contra `$CFG->wwwroot`, em vez de comparação por
  fronteira de host de verdade.
- **Cenário de falha:** se `$CFG->wwwroot` for `https://ldg.example.com` e um
  professor colar um vídeo legitimamente hospedado em
  `https://ldg.example.com.cdn-video.net/...` — um host externo real que só
  compartilha o prefixo da string wwwroot — a checagem incorretamente marca
  como self-hosted e bloqueia com "errorselfhosted", mesmo o Moodle não
  servindo aquilo de forma alguma.
- **Correção:**

## 4. Webservice sem checagem explícita de capability por cm
- **Status:** pendente
- **Arquivo:** `classes/external.php:143`
- **Achado:** `get_ldgvideos_by_courses()` valida os ids de curso pedidos mas
  nunca chama `validate_context()`/`require_capability()` por
  course-module antes de retornar os dados de cada vídeo — depende só da
  filtragem de visibilidade própria de `get_all_instances_in_courses()`, ao
  contrário de `view.php`/`view_ldgvideo()` que têm guarda explícita de
  capability por cm.
- **Cenário de falha:** se a filtragem de visibilidade de
  `get_all_instances_in_courses()` algum dia divergir da checagem de
  capability `mod/ldgvideo:view` (um papel com a capability proibida em
  nível de módulo enquanto a visibilidade em nível de curso ainda está
  ligada, ou uma mudança futura do core alterar o critério de filtragem desse
  helper), um chamador de webservice/mobile poderia receber dados de
  endereço/nome/intro de um vídeo de uma atividade a que não tem direito de
  acesso, sem a guarda explícita que a UI web tem.
- **Correção:**
