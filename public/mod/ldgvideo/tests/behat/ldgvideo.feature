@mod @mod_ldgvideo
Feature: A aula em video
  Para dar aula sem pagar hospedagem de video
  Como professor
  Preciso colar o endereco de um video de fora e ver que ele entra como campo, e nao como HTML solto

  # Sem @javascript de proposito, pela mesma razao do format_ldg: tudo o que se
  # verifica aqui e desenhado no SERVIDOR. O que depende de navegador - a
  # proporcao do quadro medida em pixel - fica fora, porque este ambiente nao
  # tem um, e teste que nao roda nao protege nada. Aquilo se prova no Chrome, e
  # esta registrado no README.

  Background:
    Given the following "courses" exist:
      | fullname   | shortname | enablecompletion |
      | Curso teste | curso1   | 1                |
    And the following "users" exist:
      | username  | firstname | lastname  | email           |
      | professor | Pedro     | Professor | prof@teste.com  |
      | aluno     | Ana       | Aluna     | aluno@teste.com |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | professor | curso1 | editingteacher |
      | aluno     | curso1 | student        |

  Scenario: O trecho de incorporacao colado vira uma aula, e o HTML nao sobrevive
    Given I am on the "curso1" "Course" page logged in as "professor"
    And I turn editing mode on
    When I add a "ldgvideo" activity to course "Curso teste" section "1" and I fill the form with:
      | Name          | Aula de abertura |
      | Video address | <iframe width="560" height="315" src="https://www.youtube.com/embed/d2bq9QW7fZg?si=abc" title="YouTube video player" allowfullscreen></iframe> |
    Then I should see "Aula de abertura"
    # O que ficou guardado e o endereco canonico, sem o trecho e sem o rastreio.
    And I am on the "Aula de abertura" "ldgvideo activity editing" page
    And the field "Video address" matches value "https://www.youtube.com/watch?v=d2bq9QW7fZg"

  Scenario: Endereco que nao e video e recusado
    Given I am on the "curso1" "Course" page logged in as "professor"
    And I turn editing mode on
    When I add a "ldgvideo" activity to course "Curso teste" section "1" and I fill the form with:
      | Name          | Aula quebrada    |
      | Video address | a aula de hoje   |
    Then I should see "This does not look like a video address"

  Scenario: Video hospedado na propria plataforma e recusado
    # A margem do plano Free depende disto: o professor pode subir um .mp4 num
    # rotulo do curso e colar o link do pluginfile aqui.
    Given I am on the "curso1" "Course" page logged in as "professor"
    And I turn editing mode on
    When I add a "ldgvideo" activity to course "Curso teste" section "1" and I fill the form with:
      | Name          | Aula local |
      | Video address | #wwwroot#/pluginfile.php/1/mod_resource/content/1/aula.mp4 |
    Then I should see "Videos are hosted outside the platform"

  Scenario: Player desligado nao e confundido com endereco ruim
    # As duas situacoes davam a mesma mensagem ate 06/09/2026, e a generica
    # mandava o professor conferir um link que ja estava certo.
    Given the following config values are set as admin:
      | media_plugins_sortorder | youtube |
    And I am on the "curso1" "Course" page logged in as "professor"
    And I turn editing mode on
    When I add a "ldgvideo" activity to course "Curso teste" section "1" and I fill the form with:
      | Name          | Aula do Vimeo             |
      | Video address | https://vimeo.com/226053498 |
    Then I should see "its media player is turned off on this site"

  Scenario: A descricao aparece ou some conforme o campo de aparencia
    Given the following "activities" exist:
      | activity | course | name       | intro                  | printintro |
      | ldgvideo | curso1 | Com texto  | O que voce vai aprender | 1         |
      | ldgvideo | curso1 | Sem texto  | Isto nao deve aparecer  | 0         |
    When I am on the "Com texto" "ldgvideo activity" page logged in as "aluno"
    Then I should see "O que voce vai aprender"
    When I am on the "Sem texto" "ldgvideo activity" page
    Then I should not see "Isto nao deve aparecer"

  Scenario: O aluno marca a aula como feita
    Given the following "activities" exist:
      | activity | course | name    | completion |
      | ldgvideo | curso1 | Aula um | 1          |
    When I am on the "Aula um" "ldgvideo activity" page logged in as "aluno"
    Then the manual completion button of "Aula um" is displayed as "Mark as done"

  @javascript
  Scenario: O aluno aperta o botao e a aula fica feita
    # Este precisa de navegador: a marcacao manual e feita por JavaScript desde
    # o 4.3, e sem o driver o passo morre com "does not have a form ancestor".
    # Sobe o Chrome com "moodev up --full" antes de rodar.
    Given the following "activities" exist:
      | activity | course | name    | completion |
      | ldgvideo | curso1 | Aula um | 1          |
    When I am on the "Aula um" "ldgvideo activity" page logged in as "aluno"
    And I toggle the manual completion state of "Aula um"
    Then the manual completion button of "Aula um" is displayed as "Done"

  @javascript
  Scenario Outline: O quadro acompanha a coluna e guarda a proporcao
    # A PROVA QUE NENHUM TESTE DE SERVIDOR DA. O HTML sai igual em qualquer
    # largura: o que muda e o que o navegador calcula do CSS. O core emite
    # width="560" height="315" no iframe, e so o aspect-ratio do styles.css
    # anula aquilo - se alguem mexer la, o video encolhe para 560x315 no meio
    # da coluna, sem erro e sem log. E aqui que isso aparece.
    Given the following "activities" exist:
      | activity | course | name      | aspectratio   |
      | ldgvideo | curso1 | Aula wide | <proporcao>   |
    And I change window size to "<janela>"
    When I am on the "Aula wide" "ldgvideo activity" page logged in as "aluno"
    Then the video frame should fill its column
    And the video frame should keep the "<proporcao>" ratio

    Examples:
      | janela   | proporcao |
      | 1440x900 | 16:9      |
      | 1440x900 | 4:3       |
      | 390x844  | 16:9      |
