@local @local_marketplace
Feature: Os papeis de empresa
  Para quem so monta curso nao mexer na conta de pagamento da empresa
  Como dono da plataforma
  Preciso que responsavel e vendedor tenham acessos diferentes

  # A FRONTEIRA DE ARQUIVO NAO SE PROVA AQUI, e vale dizer por que. Ela e um
  # CAP_PROHIBIT no papel, e o papel vive no contexto da CATEGORIA da empresa -
  # entao ela vale onde o curso e montado, e nao no perfil pessoal de quem
  # monta. Um cenario que abrisse /user/files.php esperando "sem permissao"
  # falha, e ja falhou em 04/09/2026. O escopo exato esta fixado em
  # roles_test::test_a_proibicao_vale_na_categoria_e_nao_no_usuario.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | dono     | Dora      | Dona     | dono@teste.com |
      | vendedor | Valter    | Vendedor | vend@teste.com |
    And the following "local_marketplace > companies" exist:
      | shortname | name          | user |
      | empresa1  | Empresa Teste | dono |

  Scenario: O responsavel enxerga a empresa dele
    Given I log in as "dono"
    When I visit "/local/marketplace/company.php"
    Then I should see "Empresa Teste"

  # O CAMINHO NEGATIVO NAO CABE AQUI. Quem nao e membro cai numa
  # moodle_exception "nocompany", e o behat_hooks procura excecao depois de cada
  # passo - entao a assercao falha mesmo quando a excecao E o comportamento
  # certo. Esta armadilha esta registrada em docs/dev/behat.md, e o caso vive no
  # phpunit, onde da para afirmar sobre a capability em vez de sobre a tela.
