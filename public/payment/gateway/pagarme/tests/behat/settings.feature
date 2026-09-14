@payment @paygw @paygw_pagarme
Feature: Configuracao do gateway Pagar.me
  Para o aluno nunca chegar ao checkout de uma conta que nao recebe
  Como dono da plataforma
  Preciso configurar o plugin e vincular a conta do vendedor antes de habilitar

  # O SPLIT NAO SE PROVA AQUI. Behat nenhum enxerga dinheiro trocando de conta.
  # A prova daquele trecho e o roteiro de docs/data-validation/pagarme-sandbox.md,
  # e ela continua bloqueada pela conta de homologacao.
  #
  # O que se prova aqui e o que e do Moodle: a secao existe com o nome certo, o
  # endereco do webhook aparece para ser cadastrado, e a trava que impede
  # habilitar sem vinculo.

  Background:
    # A tela de contas de pagamento so lista gateway HABILITADO
    # (paygw::get_enabled_plugins). Sem isto a coluna do Pagar.me nem existe na
    # linha da conta, e a falha nao diz o porque.
    Given the following config values are set as admin:
      | paygw_plugins_sortorder | paypal,pagarme |
    And I log in as "admin"

  # A secao e "paymentgateway<nome>", e nao "paygw_<nome>". Errar o nome nao da
  # erro de codigo: da "Section error" na tela, e so quando alguem abre.
  Scenario: A secao do plugin guarda a configuracao de site
    When I visit "/admin/settings.php?section=paymentgatewaypagarme"
    Then I should see "Environment and webhook"
    When I set the field "Webhook user" to "leo@exemplo.br"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I visit "/admin/settings.php?section=paymentgatewaypagarme"
    Then the field "Webhook user" matches value "leo@exemplo.br"

  # O endereco tem que ser lido da tela e colado no painel do Pagar.me. Se ele
  # sumir daqui, o webhook nunca e cadastrado e a venda fica pendente para
  # sempre.
  Scenario: O endereco do webhook aparece para ser cadastrado no painel
    When I visit "/admin/settings.php?section=paymentgatewaypagarme"
    Then I should see "/payment/gateway/pagarme/webhook.php"

  # A API so aceita entre 15 e 60 minutos. O valor e guardado como digitado, e
  # quem apara e o pix_expires_in() - provado por phpunit.
  Scenario: A validade do QR Code e configuravel
    When I visit "/admin/settings.php?section=paymentgatewaypagarme"
    And I set the field "QR code validity, in minutes" to "45"
    And I press "Save changes"
    When I visit "/admin/settings.php?section=paymentgatewaypagarme"
    Then the field "QR code validity, in minutes" matches value "45"

  @javascript
  Scenario: O gateway nao habilita sem a conta vinculada
    Given the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Pagar.me" "link" in the "Empresa1" "table_row"
    And I set the field "Enable" to "1"
    And I press "Save changes"
    Then I should see "No Pagar.me account is linked for Sandbox."

  # Os dois ambientes aparecem lado a lado, e nao um par de campos que muda de
  # significado conforme um interruptor: assim uma chave de homologacao nunca
  # tem como ser usada em producao por engano.
  @javascript
  Scenario: A tela oferece vincular nos dois ambientes
    Given the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Pagar.me" "link" in the "Empresa1" "table_row"
    Then I should see "Sandbox"
    And I should see "Production"
    And I should see "No account linked for Sandbox."

  # A chave do vendedor e guardada cifrada, e a chave de cifragem mora no
  # moodledata. Sem ela o formulario nem aparece - e essa e a decisao certa:
  # guardar credencial de conta bancaria em texto puro para nao incomodar
  # ninguem seria trocar um aviso por um vazamento.
  #
  # Este cenario existe porque o site do behat nasce SEM a chave, entao ele
  # mede o caminho que uma instalacao nova percorre de verdade.
  @javascript
  Scenario: O vinculo recusa acontecer sem chave de cifragem no site
    Given the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Pagar.me" "link" in the "Empresa1" "table_row"
    And I click on "Link account" "link"
    Then I should see "Link a Pagar.me account"
    And I should see "admin/cli/generate_key.php"
    And "Secret key" "field" should not exist
