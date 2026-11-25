@local @local_partners
Feature: A landing publica de captacao de parceiros
  Para que empresas descubram a plataforma antes de ter conta
  Como visitante anonimo
  Preciso ver a proposta e chegar ao formulario sem fazer login

  Background:
    Given the following config values are set as admin:
      | enablelanding | 1 | local_partners |

  # O ponto destes dois cenarios e o que NAO aparece: nenhum passo faz login.
  # A landing e o formulario existem para quem ainda nao tem conta, e uma
  # regressao que exigisse login mataria a captacao inteira em silencio.

  Scenario: Visitante anonimo ve a landing sem fazer login
    When I visit "/local/partners/index.php"
    Then I should see "Become a partner"

  Scenario: Visitante anonimo alcanca o formulario sem fazer login
    When I visit "/local/partners/apply.php"
    Then I should see "Apply to sell on the platform"
    And I should see "Tell us about your operation. We answer every application."
    And I should see "Company name"
    # O botao NAO entra aqui: moodleform renderiza submit como <input value>, e
    # "I should see" le no de texto. Quem prova que ele existe e o "I press" do
    # application.feature.
    And "Send application" "button" should exist
