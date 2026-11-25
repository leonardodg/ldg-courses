@local @local_marketplace
Feature: A pagina de assinaturas do aluno
  Para renovar antes de perder o acesso
  Como aluno
  Preciso ver o que assinei, quando vence e onde pagar

  # ESTA FEATURE NASCEU DE UM ERRO EM PRODUCAO, e o erro nao era de borda.
  #
  # A pagina chamava get_records(['userid' => X], 'timeend DESC'). O persistent
  # monta o ORDER BY como "$sort . ' ' . $order", entao saia
  # "ORDER BY timeend DESC ASC" - SQL invalido. A pagina morria em
  # dml_read_exception para QUALQUER aluno logado, inclusive quem nunca comprou
  # nada, porque a consulta vem antes da checagem de lista vazia.
  #
  # Passou despercebido porque nada abria a pagina: phpunit nao renderiza tela,
  # e nao havia cenario aqui. Foi descoberto abrindo a pagina como aluno de
  # verdade, com direito ativo, em 08/09/2026.
  #
  # Por isso os dois cenarios: o com dados e o SEM. O segundo parece
  # desnecessario e e justamente o que o bug quebrava tambem.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | aluno1   | Ana       | Aluna    | aluno1@teste.com |
      | dono     | Dora      | Dona     | dono@teste.com   |
    And the following "local_marketplace > companies" exist:
      | shortname | name          | user |
      | empresa1  | Empresa Teste | dono |

  Scenario: O aluno ve a assinatura que tem, com o vencimento
    Given the following "local_marketplace > offers" exist:
      | company  | name              | accessmode | accessdays | price |
      | empresa1 | Assinatura mensal | recurring  | 30         | 20    |
    And the following "local_marketplace > entitlements" exist:
      | user   | offer             |
      | aluno1 | Assinatura mensal |
    And I log in as "aluno1"
    When I visit "/local/marketplace/mysubscriptions.php"
    Then I should see "Assinatura mensal"
    And I should see "Empresa Teste"

  # Sem direito nenhum a pagina precisa dizer isso, e nao explodir. Era este o
  # caminho que provava que o problema estava na CONSULTA, e nao nos dados: a
  # consulta vinha ANTES da checagem de lista vazia, entao quebrava ate para
  # quem nao tinha nada.
  #
  # A assercao e pelo titulo mais a mensagem, e nao por "nao vejo a palavra
  # erro": a pagina de excecao do Moodle nao renderiza nenhum dos dois, e
  # procurar a palavra solta da falso positivo em marcacao legitima.
  Scenario: Aluno sem assinatura nenhuma ve um aviso, e nao um erro
    Given I log in as "aluno1"
    When I visit "/local/marketplace/mysubscriptions.php"
    Then I should see "My subscriptions"
    And I should see "You have not bought anything yet."

  # A ordenacao e o que estava quebrado. Duas assinaturas com vencimentos
  # diferentes provam que o ORDER BY roda - com o bug, nem a pagina abria.
  Scenario: As assinaturas saem da que vence mais tarde para a que vence antes
    Given the following "local_marketplace > offers" exist:
      | company  | name          | accessmode | accessdays | price |
      | empresa1 | Plano curto   | days       | 10         | 10    |
      | empresa1 | Plano longo   | days       | 90         | 50    |
    And the following "local_marketplace > entitlements" exist:
      | user   | offer       | timeend      |
      | aluno1 | Plano curto | +10 days |
      | aluno1 | Plano longo | +90 days |
    And I log in as "aluno1"
    When I visit "/local/marketplace/mysubscriptions.php"
    Then I should see "Plano curto"
    And I should see "Plano longo"
    And "Plano longo" "text" should appear before "Plano curto" "text"
