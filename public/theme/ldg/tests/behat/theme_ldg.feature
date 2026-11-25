@theme @theme_ldg
Feature: O tema decide o que a pagina mostra ao redor do conteudo
  Para que a aula embutida no portal nao traga o site inteiro dentro dela
  Como plataforma
  Preciso esconder cabecalho, menu e rodape quando a pagina abre num quadro

  Background:
    Given the following config values are set as admin:
      | theme | ldg |
    And the following "users" exist:
      | username | firstname | lastname | email           |
      | aluno    | Ana       | Souza    | ana@example.com |
    And the following "courses" exist:
      | fullname      | shortname |
      | Xadrez Basico | xadrez    |
    And the following "course enrolments" exist:
      | user  | course | role    |
      | aluno | xadrez | student |

  # O caminho de producao e o cabecalho Sec-Fetch-Dest, que o navegador manda
  # sozinho e nenhum driver de teste deixa forjar. O parametro ldgembed existe
  # como reserva para navegador sem Fetch Metadata - e e por ele que da para
  # exercitar a MESMA decisao aqui.
  #
  # A deteccao pelo cabecalho esta no phpunit, em body_attributes_test.
  Scenario: Navegacao normal mantem o cabecalho do site
    Given I log in as "aluno"
    When I am on "Xadrez Basico" course homepage
    Then ".ldg-embedded" "css_element" should not exist

  # A classe e decidida no body_attributes e vale para a pagina inteira, entao
  # o Dashboard serve tao bem quanto o curso - e dispensa montar a URL com o id
  # do curso, que o gherkin nao tem como saber.
  Scenario: Aberta como quadro, a pagina perde o cabecalho
    Given I log in as "aluno"
    When I visit "/my/index.php?ldgembed=1"
    Then ".ldg-embedded" "css_element" should exist

  Scenario: O modo escuro e o padrao do site
    Given I log in as "aluno"
    When I am on "Xadrez Basico" course homepage
    Then ".ldg-dark" "css_element" should exist

  # A barra de acessibilidade e opt-in. Ela ja foi sempre visivel, e o resultado
  # era uma faixa fixa acima da navbar em toda pagina, para todo mundo.
  Scenario: A barra de acessibilidade nao aparece sem ninguem pedir
    Given I log in as "aluno"
    When I am on "Xadrez Basico" course homepage
    Then ".hasaccessibilitybar" "css_element" should not exist

  # Daqui para baixo exige navegador de verdade.
  #
  # Rodar com:  vendor/bin/behat --config ... --profile chrome --tags @javascript
  # e com o Chrome no ar:  moodev up --full
  @javascript
  Scenario: O alternador troca o modo de cor, e a escolha fica
    Given I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    And ".ldg-dark" "css_element" should exist
    # Sao DOIS mecanismos, e o teste separa os dois de proposito.
    #
    # O clique mexe so no data-bs-theme, que e o que o Bootstrap 5 consulta - a
    # troca e imediata e nao recarrega a pagina.
    When I click on "#toggle-darkmode" "css_element"
    Then "body[data-bs-theme='light']" "css_element" should exist
    # O aria-pressed acompanha o estado real. Um botao de alternar que anuncia
    # sempre o mesmo estado mente para quem usa leitor de tela, e o defeito nao
    # aparece em captura de tela nenhuma.
    And "#toggle-darkmode[aria-pressed='false']" "css_element" should exist

    # A classe ldg-light vem do SERVIDOR, no body_attributes. Ela so aparece
    # depois de recarregar - e por isso este passo prova o que o anterior nao
    # prova: que a preferencia foi GRAVADA. Um alternador que so mexesse no DOM
    # passaria no primeiro e falharia aqui, e o aluno veria o tema voltar ao
    # escuro na pagina seguinte.
    When I reload the page
    Then ".ldg-light" "css_element" should exist
