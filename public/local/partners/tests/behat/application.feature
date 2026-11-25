@local @local_partners
Feature: Envio de candidatura de parceria
  Para que uma empresa possa se candidatar sem ter conta no site
  Como visitante anonimo
  Preciso enviar o formulario e receber confirmacao do envio

  Background:
    Given the following config values are set as admin:
      | enablelanding            | 1 | local_partners |
      | requireemailconfirmation | 0 | local_partners |
      | enablerecaptcha          | 0 | local_partners |

  Scenario: Candidatura enviada por visitante anonimo entra na fila
    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                       | Editora Beta          |
      | Contact name                       | Maria Silva           |
      | Email                              | maria@exemplo.com     |
      | Country or region of operation     | Brazil                |
      | Expected active learners per month | 100 to 1,000 learners |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    Then I should see "Application received"
    And I should see "Your application is in. We read every one and answer by email."

    # A fila e a prova de que gravou: a tela de obrigado apareceria igual se o
    # honeypot tivesse descartado o envio em silencio.
    And I log in as "admin"
    And I visit "/local/partners/admin/applications.php"
    And I should see "Editora Beta"
    And I should see "Pending"

  Scenario: Pais, faixa e momento do aceite chegam a tela de quem decide
    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                       | Editora do Pais         |
      | Contact name                       | Rita Alves              |
      | Email                              | rita@exemplo.com        |
      | Country or region of operation     | Portugal                |
      | Expected active learners per month | More than 5,000 learners |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    And I should see "Application received"

    When I log in as "admin"
    And I visit "/local/partners/admin/applications.php"
    And I should see "Editora do Pais"
    # A fila lista o nome como texto e leva ao detalhe por um botao "View"; ha
    # uma candidatura so neste cenario.
    And I follow "View"
    Then I should see "Portugal"
    And I should see "More than 5,000 learners"

    # O aceite mostra QUANDO, e nao "sim". Que o valor gravado seja um momento
    # e nao um booleano fica provado no phpunit
    # (test_o_aceite_grava_o_momento_e_nao_um_sim); aqui o que se prova e que a
    # linha chega a tela de quem decide.
    And I should see "Terms accepted on"

  Scenario: Sem aceitar os termos a candidatura nao e gravada
    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora Sem Aceite  |
      | Contact name                   | Bruno Dias          |
      | Email                          | bruno@exemplo.com   |
      | Country or region of operation | Brazil              |
    And I press "Send application"
    Then I should see "Accept the partnership terms and the privacy policy to continue."
    And I should not see "Application received"

    # E a checagem que vale e a do SERVIDOR: o required do moodleform vira
    # atributo HTML, e atributo HTML some com um curl.
    And I log in as "admin"
    And I visit "/local/partners/admin/applications.php"
    And I should not see "Editora Sem Aceite"

  Scenario: Com confirmacao ligada a candidatura NAO entra na fila
    Given the following config values are set as admin:
      | requireemailconfirmation | 1 | local_partners |
    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora Nao Confirmada |
      | Contact name                   | Joana Souza            |
      | Email                          | joana@exemplo.com      |
      | Country or region of operation | Brazil                 |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    Then I should see "Check your inbox. Your application reaches us as soon as you open the link we just sent."

    # Enquanto o e-mail nao for provado, aquilo nao e uma candidatura: e o que
    # alguem digitou. Ela existe no banco, mas fora da fila.
    And I log in as "admin"
    And I visit "/local/partners/admin/applications.php"
    And I should see "Awaiting email confirmation"
    And I should not see "Pending"

  Scenario: CNPJ invalido e recusado antes de gravar
    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora do CNPJ Torto |
      | Contact name                   | Carlos Lima           |
      | Email                          | carlos@exemplo.com    |
      | Company tax ID (CNPJ)          | 11222333000180        |
      | Country or region of operation | Brazil                |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    Then I should see "This is not a valid company tax ID."
    And I should not see "Application received"

  Scenario: Candidatura em aberto bloqueia um segundo envio do mesmo e-mail
    Given I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora Primeira |
      | Contact name                   | Ana Costa        |
      | Email                          | ana@exemplo.com  |
      | Country or region of operation | Brazil           |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    And I should see "Application received"

    When I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora Segunda |
      | Contact name                   | Ana Costa       |
      | Email                          | ana@exemplo.com |
      | Country or region of operation | Brazil          |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    Then I should see "There is already an open application for this email or tax ID. We will be in touch."
