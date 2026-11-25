@enrol @enrol_marketplace
Feature: Matricula que nasce do direito de acesso
  Para que o aluno entre no curso que comprou, e saia quando o acesso vence
  Como plataforma
  Preciso que a matricula acompanhe o direito, sem ninguem clicar

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | aluno    | Ana       | Souza    | ana@example.com   |
      | dono     | Bruno     | Lima     | bruno@example.com |
    And the following "local_marketplace > companies" exist:
      | name           | shortname | user |
      | Escola Exemplo | escola    | dono |
    And the following "courses" exist:
      | fullname       | shortname |
      | Xadrez Basico  | xadrez    |
    And the following "local_marketplace > offers" exist:
      | company | name         | courses |
      | escola  | Pacote Xadrez| xadrez  |

  # O phpunit ja prova o sync_user por dentro. O que so o behat mostra e a
  # consequencia: a pagina do curso responde ou nao para o aluno.
  Scenario: Sem direito, o aluno nao entra no curso
    Given I log in as "aluno"
    When I am on "Xadrez Basico" course homepage
    Then I should see "You cannot enrol yourself in this course"

  Scenario: Com direito, a task matricula e o aluno entra
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         |
      | aluno | Pacote Xadrez |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    Then I should see "Xadrez Basico"
    And I should not see "You cannot enrol yourself in this course"

  Scenario: Direito com prazo ainda valido deixa entrar
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         | timeend |
      | aluno | Pacote Xadrez | +2 days |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    Then I should not see "You cannot enrol yourself in this course"

  # A DATA vale mais que o status, provado pela ponta: a linha diz "active", o
  # prazo passou, e o aluno nao entra. Entre o vencimento e o proximo cron essa
  # e a unica coisa que segura o curso pago.
  #
  # A transicao matriculado -> suspenso NAO esta aqui, e nao por esquecimento: o
  # behat monta o estado uma vez e nao altera fixture no meio do cenario. Ela
  # esta no phpunit, em test_perder_o_direito_suspende_sem_apagar, que confere
  # tambem o que a tela nao mostra - que a matricula continua existindo.
  Scenario: Direito ja vencido nao deixa entrar, mesmo com status ativo
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         | timeend | status |
      | aluno | Pacote Xadrez | -1 days | active |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    Then I should see "You cannot enrol yourself in this course"

  # Uma oferta com dois cursos e uma matricula so nao seria erro visivel em
  # nenhuma tela: o aluno simplesmente nao acharia o segundo curso.
  Scenario: Oferta com dois cursos matricula nos dois
    Given the following "courses" exist:
      | fullname      | shortname |
      | Xadrez Avancado | xadrez2 |
    And the following "local_marketplace > offers" exist:
      | company | name           | courses         |
      | escola  | Pacote Duplo   | xadrez, xadrez2 |
    And the following "local_marketplace > entitlements" exist:
      | user  | offer        |
      | aluno | Pacote Duplo |
    When I log in as "aluno"
    And I am on "Xadrez Avancado" course homepage
    Then I should not see "You cannot enrol yourself in this course"
