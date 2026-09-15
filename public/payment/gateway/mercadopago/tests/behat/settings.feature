@payment @paygw @paygw_mercadopago
Feature: Configuracao do gateway do Mercado Pago
  Para o aluno nunca chegar ao checkout de uma conta que nao recebe
  Como dono da plataforma
  Preciso configurar a aplicacao e vincular a conta do vendedor antes de habilitar

  # O CHECKOUT NAO SE PROVA AQUI. A partir do botao de pagar, o aluno sai do
  # site para o dominio do Mercado Pago, e o split acontece la. A prova daquele
  # trecho e o roteiro de docs/data-validation/mercadopago-split.md, com duas
  # contas de verdade - behat nenhum enxerga dinheiro trocando de conta.
  #
  # O que se prova aqui e o que e do Moodle: a secao existe com o nome certo, e
  # a trava que impede habilitar sem token.

  Background:
    # A tela de contas de pagamento so lista gateway HABILITADO
    # (paygw::get_enabled_plugins). Sem isto, a coluna do Mercado Pago
    # simplesmente nao existe na linha da conta, e a falha nao diz o porque.
    Given the following config values are set as admin:
      | paygw_plugins_sortorder | paypal,mercadopago |
    And I log in as "admin"

  # A secao e "paymentgateway<nome>", e nao "paygw_<nome>". Errar o nome nao da
  # erro de codigo: da "Section error" na tela, e so quando alguem abre.
  Scenario: A secao da aplicacao guarda as credenciais da plataforma
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    Then I should see "Platform applications"
    When I set the field "Client ID · Preferences API (Checkout Pro)" to "2401225442871147"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    Then the field "Client ID · Preferences API (Checkout Pro)" matches value "2401225442871147"

  # Tres aplicacoes, cada uma com credencial propria. O rotulo carrega o nome da
  # aplicacao porque tres campos "Client ID" identicos na mesma tela convidam a
  # colar a credencial errada - e credencial trocada nao da erro de tela, da
  # OAuth que falha com mensagem generica.
  Scenario: Cada tipo de integracao tem o proprio bloco de credenciais
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    Then I should see "Preferences API (Checkout Pro)"
    And I should see "Subscriptions"
    And I should see "Checkout Bricks"
    And "Client ID · Subscriptions" "field" should exist
    And "Client ID · Checkout Bricks" "field" should exist
    And "Webhook signing secret · Subscriptions" "field" should exist

  # As credenciais de uma aplicacao nao encostam nas da outra. Sem esta prova, a
  # troca de nomes de configuracao passaria despercebida ate alguem perder um
  # vinculo em producao.
  Scenario: Salvar uma aplicacao nao mexe nas outras
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    And I set the field "Client ID · Preferences API (Checkout Pro)" to "2401225442871147"
    And I set the field "Client ID · Subscriptions" to "6990306155285574"
    And I set the field "Client ID · Checkout Bricks" to "2598194068751669"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    Then the field "Client ID · Preferences API (Checkout Pro)" matches value "2401225442871147"
    And the field "Client ID · Subscriptions" matches value "6990306155285574"
    And the field "Client ID · Checkout Bricks" matches value "2598194068751669"

  # A comissao nao mora neste plugin. O campo existiu, nao era lido por ninguem,
  # e saiu em 08/09/2026 - ver o README do plugin.
  Scenario: A tela da aplicacao nao oferece campo de comissao
    When I visit "/admin/settings.php?section=paymentgatewaymercadopago"
    Then I should not see "Default commission"

  @javascript
  Scenario: O gateway nao habilita sem nenhuma conta vinculada
    Given the following config values are set as admin:
      | clientid     | 2401225442871147 | paygw_mercadopago |
      | clientsecret | segredo          | paygw_mercadopago |
    And the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Mercado Pago" "link" in the "Empresa1" "table_row"
    And I set the field "Enable" to "1"
    And I press "Save changes"
    Then I should see "Link the Mercado Pago account before enabling this gateway."

  # O link de autorizacao precisa do accountid, e ele JA existe quando a tela
  # abre: o manage_gateway.php constroi o persistente com a conta, tenha o
  # gateway sido salvo ou nao. Por isso o vinculo e oferecido de primeira.
  #
  # A consequencia esta registrada aqui de proposito: o recado
  # "savebeforelinking", do gateway.php, so apareceria com accountid zero, e
  # por este caminho isso nao acontece. E defesa que nunca dispara.
  @javascript
  Scenario: A tela oferece vincular assim que o gateway e aberto
    Given the following config values are set as admin:
      | clientid     | 2401225442871147 | paygw_mercadopago |
      | clientsecret | segredo          | paygw_mercadopago |
    And the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Mercado Pago" "link" in the "Empresa1" "table_row"
    Then I should see "Mercado Pago accounts"
    And "Link account for Preferences API (Checkout Pro)" "link" should exist
    And I should not see "Save this gateway first"

  # A tela mostra UMA LINHA POR APLICACAO CONFIGURADA, e so por essas: oferecer
  # o botao de uma aplicacao sem client_id levaria o vendedor a um erro que ele
  # nao tem como resolver, porque quem preenche aquilo e o administrador.
  @javascript
  Scenario: So aparece a aplicacao que o administrador configurou
    Given the following config values are set as admin:
      | clientid                  | 2401225442871147 | paygw_mercadopago |
      | clientsecret              | segredo          | paygw_mercadopago |
      | clientid_bricks           | 2598194068751669 | paygw_mercadopago |
      | clientsecret_bricks       | segredo          | paygw_mercadopago |
    And the following "core_payment > payment accounts" exist:
      | name     |
      | Empresa1 |
    When I navigate to "Payments > Payment accounts" in site administration
    And I click on "Mercado Pago" "link" in the "Empresa1" "table_row"
    Then "Link account for Preferences API (Checkout Pro)" "link" should exist
    And "Link account for Checkout Bricks" "link" should exist
    And "Link account for Subscriptions" "link" should not exist
