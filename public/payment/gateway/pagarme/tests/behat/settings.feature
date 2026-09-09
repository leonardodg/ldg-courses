@payment @paygw @paygw_pagarme
Feature: Configurar o gateway Pagar.me
  Para cobrar por uma oferta com split
  Como administrador
  Preciso configurar o plugin e vincular a conta do vendedor

  Background:
    Given I log in as "admin"

  Scenario: A tela de configuracao guarda o ambiente e o segredo do webhook
    When I navigate to "Plugins > Payment gateways > Pagar.me" in site administration
    And I set the following fields to these values:
      | Environment     | Sandbox        |
      | Webhook user    | leo@exemplo.br |
      | Payment method  | Pix            |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Webhook user" matches value "leo@exemplo.br"

  Scenario: A validade do QR Code aceita o que a API aceita
    When I navigate to "Plugins > Payment gateways > Pagar.me" in site administration
    And I set the field "QR code validity, in minutes" to "45"
    And I press "Save changes"
    Then the field "QR code validity, in minutes" matches value "45"

  Scenario: O endereco do webhook aparece para ser cadastrado no painel
    When I navigate to "Plugins > Payment gateways > Pagar.me" in site administration
    Then I should see "/payment/gateway/pagarme/webhook.php"

  @javascript
  Scenario: O gateway nao habilita sem conta vinculada
    Given the following "core_payment > payment accounts" exist:
      | name              |
      | Conta do vendedor |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Conta do vendedor" "link"
    And I click on "Pagar.me" "link"
    And I set the field "Enable" to "1"
    And I press "Save changes"
    Then I should see "No Pagar.me account is linked for Sandbox."

  @javascript
  Scenario: A tela do gateway oferece o vinculo nos dois ambientes
    Given the following "core_payment > payment accounts" exist:
      | name              |
      | Conta do vendedor |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Conta do vendedor" "link"
    And I click on "Pagar.me" "link"
    Then I should see "Sandbox"
    And I should see "Production"
    And I should see "No account linked for Sandbox."
