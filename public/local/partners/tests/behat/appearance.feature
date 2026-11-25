@local @local_partners @javascript
Feature: A landing e o cadastro na tela
  Para que a pagina publica funcione no celular e em qualquer tema do site
  Como visitante
  Preciso que o layout responda a largura da tela e nao dependa do tema

  # Estes cenarios exigem navegador de verdade:
  #   moodev up --full
  #   vendor/bin/behat --config ... --profile=chrome --tags "@local_partners&&@javascript"
  #
  # Sem o --profile=chrome o behat procura Selenium em localhost:4444 e morre
  # com erro que parece problema de ambiente.
  #
  # E "sem escala em tempo de execucao" NAO e detalhe: por padrao o Moodle
  # escala o conteudo depois de redimensionar, e o "mobile" de 425px passa a se
  # comportar como uns 600. Uma assercao sobre celular medida assim afirma outra
  # coisa - foi o que fez este arquivo passar dizendo o que nao era.

  Background:
    Given the following config values are set as admin:
      | enablelanding | 1 | local_partners |

  Scenario: No celular a pagina cabe na tela e tudo fica em uma coluna
    Given I change viewport size to "mobile" without runtime scaling
    When I visit "/local/partners/index.php"
    Then the page should not scroll sideways
    And the ".ldgp-plan" elements should be stacked in one column
    And the ".ldgp-pillar" elements should be stacked in one column
    And the ".ldgp-step" elements should be stacked in one column

  Scenario: No desktop os planos ficam lado a lado
    Given I change viewport size to "1440x900"
    When I visit "/local/partners/index.php"
    Then the ".ldgp-plan" elements should sit in 3 columns
    And the ".ldgp-pillar" elements should sit in 4 columns
    And the page should not scroll sideways

  Scenario: O alvo de toque da barra respeita o minimo no celular
    Given I change viewport size to "mobile" without runtime scaling
    When I visit "/local/partners/index.php"
    Then every ".ldgp-bar-link" touch target should be at least 44 pixels tall

  Scenario: A barra de secoes gruda ao rolar
    Given I change viewport size to "1440x900"
    And I visit "/local/partners/index.php"
    When I click on "FAQ" "link" in the ".ldgp-bar" "css_element"
    Then the section bar should stick below the header

  Scenario: O cadastro empilha no celular e nao rola para o lado
    Given I change viewport size to "mobile" without runtime scaling
    When I visit "/local/partners/apply.php"
    Then the page should not scroll sideways
    And the ".ldgp-plancard" elements should be stacked in one column

  # O modo escuro e o padrao para quem nunca escolheu, e o alternador existe
  # porque o theme_boost do 5.2 nao tem nenhum.
  Scenario: Quem nunca escolheu ve a pagina escura, e pode trocar
    Given I change viewport size to "1440x900"
    And I visit "/local/partners/index.php"
    Then the partner page should be in "dark" mode
    When I click on "button[data-ldgp='colormode']" "css_element"
    Then the partner page should be in "light" mode

  # A HOME e a landing servem a MESMA pagina, e por isso nao podem divergir.
  #
  # Enquanto o layout da frontpage montava a navbar e o rodape do tema por cima
  # da landing - que ja traz os proprios -, quem abria a raiz do dominio via
  # duas barras e dois rodapes, um dentro do outro, e a pagina inteira espremida
  # no container de leitura do Boost. Nenhum teste pegou: o HTML estava certo, e
  # o defeito so aparecia na tela.
  Scenario: A pagina inicial serve a landing sem duplicar o cromo do tema
    # O tema e parte do cenario, e nao ambiente: quem decide servir a landing no
    # lugar da home e o layout de frontpage do theme_ldg. Sob boost a home e a
    # do Moodle, e o cenario estaria medindo outra pagina.
    # O forcelogin faz a raiz redirecionar para a tela de entrar, e o site de
    # teste do behat nasce com ele LIGADO. A landing e uma pagina publica: sem
    # este valor o cenario mediria o formulario de login.
    # Tres valores, e nenhum e enfeite:
    #
    #   theme         quem decide servir a landing na home e o layout de
    #                 frontpage do theme_ldg - sob boost a home e a do Moodle;
    #   forcelogin    a landing e publica, e o site de teste do behat nasce com
    #                 o forcelogin ligado;
    #   enablemyhome  com o Dashboard desligado, o index.php do CORE manda todo
    #                 visitante anonimo para a tela de entrar antes de decidir
    #                 qualquer layout (public/index.php, ramo do enablemyhome).
    #                 O site real tem Dashboard; o do behat, nao.
    Given the following config values are set as admin:
      | theme        | ldg |
      | forcelogin   | 0   |
      | enablemyhome | 1   |
    And the following config values are set as admin:
      | frontpagemode | landing | local_partners |
    And I change viewport size to "1440x900"
    When I am on site homepage
    Then I should see exactly 1 ".ldgp-bar" elements
    And I should see exactly 1 ".ldgp-footer" elements
    And "#usernavigation" "css_element" should not exist
    And "#page-footer" "css_element" should not exist
    And the page should not scroll sideways
    And the ".ldgp-bar" element should span the full viewport width

  # O SELETOR DE IDIOMA PRECISA ABRIR NA RAIZ, e nao so na URL do plugin.
  #
  # Ele e um dropdown do Bootstrap: o data-bs-toggle e so um atributo, e quem
  # liga comportamento a ele e o modulo theme_boost/loader. Esse modulo e
  # carregado por CADA TEMPLATE de layout do Boost, e o theme_ldg/landing -
  # escrito do zero para a raiz nao duplicar o cromo - nao trazia o bloco. O
  # resultado: a MESMA pagina com o menu vivo em /local/partners/index.php, que
  # usa o layout 'embedded', e morto em /, que usa o template do tema.
  #
  # O cenario pergunta ao RequireJS se o modulo EXECUTOU, e nao se a tag esta no
  # HTML: e a diferenca entre "o script foi pedido" e "o componente responde".
  #
  # Clicar no proprio seletor seria mais direto e nao esta ao alcance - o menu
  # so e renderizado com mais de um idioma instalado, e o site do behat tem so o
  # ingles. As duas paginas entram no cenario porque o defeito era a DIVERGENCIA
  # entre elas: a mesma landing, viva numa URL e morta na outra.
  Scenario: A pagina inicial carrega os componentes JS do Bootstrap
    Given the following config values are set as admin:
      | theme        | ldg |
      | forcelogin   | 0   |
      | enablemyhome | 1   |
    And the following config values are set as admin:
      | frontpagemode | landing | local_partners |
    When I visit "/local/partners/index.php"
    Then the page should have the Bootstrap components loaded
    When I am on site homepage
    Then the page should have the Bootstrap components loaded

  # O "Apply" e o botao primario da barra. Enquanto tambem era ancora, a mesma
  # acao aparecia duas vezes a dois centimetros de distancia.
  Scenario: A candidatura aparece uma vez so na barra
    Given I change viewport size to "1440x900"
    When I visit "/local/partners/index.php"
    Then "a.ldgp-btn--primary" "css_element" should exist in the ".ldgp-bar" "css_element"
    And ".ldgp-bar-list a[href='#ldgp-apply']" "css_element" should not exist

  # Quem ja entrou nao precisa de "Entrar", e precisa de uma saida. O botao
  # simplesmente sumia, e a barra ficava sem nenhuma saida para a sessao aberta.
  Scenario: Quem entrou ve sair no lugar de entrar
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | aluno    | Ana       | Souza    | ana@example.invalid |
    And I change viewport size to "1440x900"
    And I log in as "aluno"
    When I visit "/local/partners/index.php"
    Then "a[href*='logout.php']" "css_element" should exist in the ".ldgp-bar-actions" "css_element"
    And "a[href*='login/index.php']" "css_element" should not exist in the ".ldgp-bar-actions" "css_element"

  # O rodape e a barra de controles do cadastro ficam ESCUROS nos dois modos:
  # sao superficies de moldura, e a moldura escura e o que da a estas paginas a
  # cara que elas tem.
  Scenario: O rodape continua escuro com a pagina em claro
    Given I change viewport size to "1440x900"
    And I visit "/local/partners/index.php"
    When I click on "button[data-ldgp='colormode']" "css_element"
    Then the partner page should be in "light" mode
    And the ".ldgp-footer" element should have a dark background

  # No celular a barra tem DUAS linhas, e nao tres: medido em 390, faltavam 5px
  # para a marca caber ao lado das acoes, e o seletor de idioma devolve 22
  # quando abre mao do globo.
  Scenario: No celular a barra cabe em duas linhas
    Given I change viewport size to "390x844" without runtime scaling
    When I visit "/local/partners/index.php"
    Then the ".ldgp-bar" element should be at most 110 pixels tall
    And the page should not scroll sideways

  # Esta e a prova concreta de "funciona sob Boost e Moove", em vez da promessa.
  # A regra que esconde o campo-armadilha morava no tema, e por isso ele
  # aparecia na tela para todo visitante nos outros dois.
  Scenario Outline: O cadastro se comporta igual em qualquer tema
    Given the following config values are set as admin:
      | theme | <tema> |
    And I change viewport size to "1440x900"
    When I visit "/local/partners/apply.php"
    Then the partner page should be in "dark" mode
    And the honeypot field should be off screen
    And the page should not scroll sideways

    Examples:
      | tema  |
      | boost |
      | moove |
      | ldg   |
