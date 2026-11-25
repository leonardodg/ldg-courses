@availability @availability_marketplace
Feature: Conteudo liberado mediante compra
  Para vender parte de um curso sem duplicar o curso
  Como vendedor
  Preciso trancar uma atividade atras do direito de acesso

  Background:
    Given the following config values are set as admin:
      | enableavailability | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | aluno    | Ana       | Souza    | ana@example.com   |
      | dono     | Bruno     | Lima     | bruno@example.com |
    And the following "local_marketplace > companies" exist:
      | name           | shortname | user |
      | Escola Exemplo | escola    | dono |
    And the following "courses" exist:
      | fullname      | shortname |
      | Xadrez Basico | xadrez    |
    And the following "course enrolments" exist:
      | user  | course | role    |
      | aluno | xadrez | student |
    And the following "local_marketplace > offers" exist:
      | company | name          | courses |
      | escola  | Pacote Xadrez | xadrez  |
      | escola  | Pacote Outro  | xadrez  |

  # A restricao entra como JSON direto, e nao clicando na tela: o formulario de
  # restricao e todo @javascript, e o que este cenario quer provar e a DECISAO
  # da condicao, nao o formulario do core.
  Scenario: Sem direito, a aula paga fica trancada
    Given the following "activities" exist:
      | activity | course | name      | availability                                                       |
      | page     | xadrez | Aula Paga | {"op":"&","c":[{"type":"marketplace","offerid":0}],"showc":[true]} |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    Then I should see "Aula Paga"
    And I should see "Not available unless"

  Scenario: Com direito, a aula paga abre
    Given the following "activities" exist:
      | activity | course | name      | availability                                                       |
      | page     | xadrez | Aula Paga | {"op":"&","c":[{"type":"marketplace","offerid":0}],"showc":[true]} |
    And the following "local_marketplace > entitlements" exist:
      | user  | offer         |
      | aluno | Pacote Xadrez |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    And I follow "Aula Paga"
    Then I should see "Aula Paga"
    And I should not see "Not available unless"

  # A DATA vale mais que o status, tambem aqui: a linha diz "active", o prazo
  # passou, e a aula fecha. E o mesmo furo do enrol, e ele tinha que ser tapado
  # nos DOIS consumidores - um deles sozinho deixaria o outro caminho aberto.
  Scenario: Direito vencido volta a trancar a aula
    Given the following "activities" exist:
      | activity | course | name      | availability                                                       |
      | page     | xadrez | Aula Paga | {"op":"&","c":[{"type":"marketplace","offerid":0}],"showc":[true]} |
    And the following "local_marketplace > entitlements" exist:
      | user  | offer         | timeend | status |
      | aluno | Pacote Xadrez | -1 days | active |
    When I log in as "aluno"
    And I am on "Xadrez Basico" course homepage
    Then I should see "Not available unless"

# A restricao por oferta ESPECIFICA nao esta aqui de proposito: o JSON da
# condicao exige o id numerico da oferta, que so existe depois de o gerador
# rodar - e nao ha como referenciar isso no gherkin. Ela esta no phpunit, em
# condition_test::test_oferta_declarada_e_exclusiva.
