@local @local_partners
Feature: Decisao sobre uma candidatura de parceria
  Para que a plataforma so ganhe empresas que alguem aprovou
  Como administrador
  Preciso decidir cada candidatura, e a aprovacao precisa provisionar a empresa

  Background:
    Given the following config values are set as admin:
      | enablelanding            | 1 | local_partners |
      | requireemailconfirmation | 0 | local_partners |
      | enablerecaptcha          | 0 | local_partners |
    And I visit "/local/partners/apply.php"
    And I set the following fields to these values:
      | Company name                   | Editora Candidata |
      | Contact name                   | Paulo Reis        |
      | Email                          | paulo@exemplo.com |
      | Country or region of operation | Brazil            |
    And I set the field "I accept the partnership terms and the privacy policy." to "1"
    And I press "Send application"
    And I should see "Application received"
    And I log in as "admin"

  # O dono fica em BRANCO de proposito. E o caminho que exercita a criacao da
  # conta: quem se candidata pelo formulario publico normalmente nao tem login,
  # e a conta nasce com senha aleatoria mais link de redefinicao - nunca com
  # senha em texto no e-mail.
  Scenario: Aprovar cria a empresa e some da fila de pendentes
    When I visit "/local/partners/admin/applications.php"
    And I follow "View"
    And I set the following fields to these values:
      | Decision   | Approve and create the company |
      | Short name | editoracandidata               |
    And I press "Save decision"
    Then I should see "Application approved"

    # A empresa tem que existir de verdade, e nao so a candidatura ter mudado
    # de situacao.
    And I visit "/local/marketplace/admin/companies.php"
    And I should see "Editora Candidata"

  Scenario: Recusar nao cria empresa nenhuma
    When I visit "/local/partners/admin/applications.php"
    And I follow "View"
    And I set the following fields to these values:
      | Decision | Reject |
    And I press "Save decision"
    Then I should see "Application from"

    And I visit "/local/marketplace/admin/companies.php"
    And I should not see "Editora Candidata"

  Scenario: A fila mostra a situacao de cada candidatura
    When I visit "/local/partners/admin/applications.php"
    Then I should see "Editora Candidata"
    And I should see "Paulo Reis"
    And I should see "Pending"
