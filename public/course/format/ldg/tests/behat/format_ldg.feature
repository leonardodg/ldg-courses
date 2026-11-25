@format @format_ldg
Feature: O portal do aluno
  Para acompanhar o curso sem se perder
  Como aluno
  Preciso ver a aula em foco com a lista de aulas ao lado, e saber o que ainda nao posso abrir

  # Sem @javascript de proposito. Tudo o que se verifica aqui e desenhado no
  # SERVIDOR: a lista, o cadeado, o quadro e a troca de coluna por edicao. O que
  # depende de navegador - a altura do quadro e a gravacao da duracao - fica de
  # fora, porque este ambiente nao tem um, e teste que nao roda nao protege
  # nada.

  Background:
    Given the following "courses" exist:
      | fullname  | shortname | format | enablecompletion |
      | Curso LDG | ldgcurso  | ldg    | 1                |
    And the following "users" exist:
      | username  | firstname | lastname  | email           |
      | aluno     | Ana       | Aluna     | aluno@teste.com |
      | professor | Pedro     | Professor | prof@teste.com  |
    And the following "course enrolments" exist:
      | user      | course   | role           |
      | aluno     | ldgcurso | student        |
      | professor | ldgcurso | editingteacher |
    And the following "activities" exist:
      | activity | course   | section | name      | completion |
      | page     | ldgcurso | 1       | Aula um   | 1          |
      | page     | ldgcurso | 1       | Aula dois | 1          |
      | page     | ldgcurso | 2       | Aula tres | 1          |

  Scenario: O aluno ve a lista de aulas agrupada por modulo
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then I should see "Aula um"
    And I should see "Aula dois"
    And I should see "Aula tres"
    And ".ldg-lessonlist" "css_element" should exist

  Scenario: A aula em foco aparece embutida, e a lista marca qual e
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then "iframe.ldg-lesson__frame" "css_element" should exist
    And ".ldg-lessonlist__lesson.is-current" "css_element" should exist

  Scenario: O curso com conclusao mostra a barra de progresso
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-portal__progress" "css_element" should exist
    And ".ldg-lessonlist__module-bar" "css_element" should exist

  Scenario: Aula bloqueada nao vira link
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "activities" exist:
      | activity | course   | section | name         | completion | availability                                                                     |
      | page     | ldgcurso | 2       | Aula trancada | 1         | {"op":"&","c":[{"type":"date","d":">=","t":4102444800}],"showc":[true]}           |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-lessonlist__lesson.is-locked" "css_element" should exist
    And "//a[contains(@class,'ldg-lessonlist__link')][contains(., 'Aula trancada')]" "xpath_element" should not exist

  Scenario: Com edicao ligada a coluna principal volta a ser a pilha de secoes
    Given I am on the "ldgcurso" "Course" page logged in as "professor"
    When I turn editing mode on
    Then "[data-region='ldg-duration']" "css_element" should exist
    # Arrastar atividade e o menu de acoes vivem nos ganchos que o core poe na
    # marcacao das secoes, e nenhum funciona dentro de um quadro embutido.
    And "iframe.ldg-lesson__frame" "css_element" should not exist
    And "[data-for='course_sectionlist']" "css_element" should exist

  Scenario: Voltar para o formato de secoes nao quebra o curso
    Given I am on the "ldgcurso" "course editing" page logged in as "professor"
    And I expand all fieldsets
    When I set the field "Format" to "Custom sections"
    And I press "Save and display"
    Then I should see "Aula um"
    And I should see "Aula dois"
    And I should see "Aula tres"
    # A tela volta a ser a do formato de secoes, e nada do portal sobra.
    And ".ldg-lessonlist" "css_element" should not exist
    And "iframe.ldg-lesson__frame" "css_element" should not exist
    # NAO se checa "Debug info" aqui: esse texto existe no JSON de strings de
    # idioma que o Moodle embute para o JavaScript, e a assercao falharia sempre.
    # Pagina com excecao ja reprova sozinha, pelo behat_hooks.

  # O chrome do portal. Continua sem @javascript porque a troca de layout e
  # decidida no SERVIDOR, pelo hook: o que se verifica aqui e qual layout o
  # Moodle escolheu, e isso chega no HTML. A conferencia de acessibilidade com
  # axe-core exige navegador e por isso nao esta aqui.
  #
  # O TEMA PRECISA SER O LDG, e isso nao e detalhe de arranjo: o portal so
  # existe se o tema ativo declarar o layout 'ldgportal'. O site do behat nasce
  # com o boost, e sem esta linha os cenarios falham - o que, na primeira vez,
  # foi a prova de que o guard do hook funciona.
  Scenario: O aluno ve o portal, sem o chrome do Moodle
    Given the following config values are set as admin:
      | theme | ldg |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-portal__header" "css_element" should exist
    And ".ldg-portal__exit" "css_element" should exist
    # O menu de usuario nao pode sumir junto com a navbar, senao o aluno fica
    # preso no curso, sem perfil e sem sair.
    And "[data-region='usermenu']" "css_element" should exist
    And "nav.navbar" "css_element" should not exist

  # Este cenario e o que separa "professor" de "editando". Sem ele, o seguinte
  # passaria por acidente mesmo que a regra fosse pelo PAPEL - e a regra e pela
  # edicao.
  Scenario: O professor sem editar tambem ve o portal
    Given the following config values are set as admin:
      | theme | ldg |
    When I am on the "ldgcurso" "Course" page logged in as "professor"
    Then ".ldg-portal__header" "css_element" should exist
    And "nav.navbar" "css_element" should not exist

  # Sem isto o professor tinha que LIGAR A EDICAO para chegar nas notas: a
  # navegacao do curso mora na navbar, e o portal nao tem navbar.
  Scenario: Quem gerencia leva a navegacao do curso para dentro do portal
    Given the following config values are set as admin:
      | theme | ldg |
    When I am on the "ldgcurso" "Course" page logged in as "professor"
    Then ".ldg-portal__coursenav" "css_element" should exist

  # E o outro lado da mesma regra. A navegacao secundaria do core NAO e
  # exclusiva de professor - o aluno tem uma, com Curso e Notas -, e sem a trava
  # de capacidade ela voltava para ele: seria reintroduzir no portal justamente
  # o chrome que o portal existe para tirar.
  Scenario: O aluno nao ve a navegacao do curso
    Given the following config values are set as admin:
      | theme | ldg |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-portal__header" "css_element" should exist
    And ".ldg-portal__coursenav" "css_element" should not exist

  Scenario: Com a edicao ligada volta o chrome do Moodle
    Given the following config values are set as admin:
      | theme | ldg |
    And I am on the "ldgcurso" "Course" page logged in as "professor"
    When I turn editing mode on
    Then "nav.navbar" "css_element" should exist
    And ".ldg-portal__header" "css_element" should not exist

  # O formato tem que ser instalavel com QUALQUER tema: e o motivo de o hook
  # conferir se o layout existe antes de trocar. Com o boost, o curso abre no
  # chrome do proprio boost, sem portal e sem erro.
  Scenario: Com outro tema o curso abre no chrome daquele tema
    Given the following config values are set as admin:
      | theme | boost |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-lessonlist" "css_element" should exist
    And ".ldg-portal__header" "css_element" should not exist
    And "nav.navbar" "css_element" should exist
    # A ESTRUTURA sobrevive sem o tema, e e por isso que ela mora no
    # styles.css do formato: o Moodle o carrega para qualquer tema.
    And ".ldg-portal__body" "css_element" should exist

  # Os quatro destinos. Sem @javascript porque sao links de verdade: o destino
  # viaja na URL e a pagina volta montada do servidor.
  Scenario: O aluno troca de destino e a aula em foco nao se perde
    Given the following config values are set as admin:
      | theme | ldg |
    And the following "activities" exist:
      | activity | course   | section | name       |
      | resource | ldgcurso | 1       | Apostila   |
      | forum    | ldgcurso | 2       | Duvidas    |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    Then ".ldg-portal__nav" "css_element" should exist
    # Pelas CLASSES, e nao pelo rotulo: o site do behat roda em ingles, e o
    # teste nao pode depender do idioma para provar navegacao.
    And ".ldg-portal__navitem--materials" "css_element" should exist
    And ".ldg-portal__navitem--forum" "css_element" should exist
    # Certificado nao existe neste curso, entao o destino nao aparece.
    And ".ldg-portal__navitem--certificate" "css_element" should not exist

  Scenario: O material aparece no destino dele, e nao na lista de aulas
    Given the following config values are set as admin:
      | theme | ldg |
    And the following "activities" exist:
      | activity | course   | section | name     |
      | resource | ldgcurso | 1       | Apostila |
    When I am on the "ldgcurso" "Course" page logged in as "aluno"
    # A apostila saiu da lista de aulas.
    Then I should not see "Apostila" in the ".ldg-lessonlist" "css_element"
    When I click on ".ldg-portal__navitem--materials" "css_element"
    Then ".ldg-materiallist" "css_element" should exist
    And I should see "Apostila"
