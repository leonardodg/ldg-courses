@block @block_marketplace
Feature: O aluno ve as assinaturas que exigem acao
  Para nao descobrir o vencimento quando ja perdeu o acesso
  Como aluno
  Preciso ver no Dashboard o que vence e o que foi cancelado

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | aluno    | Ana       | Souza    | ana@example.com   |
      | outro    | Caio      | Melo     | caio@example.com  |
      | dono     | Bruno     | Lima     | bruno@example.com |
    And the following "local_marketplace > companies" exist:
      | name           | shortname | user |
      | Escola Exemplo | escola    | dono |
    And the following "courses" exist:
      | fullname      | shortname |
      | Xadrez Basico | xadrez    |
    And the following "local_marketplace > offers" exist:
      | company | name          | courses | accessmode |
      | escola  | Pacote Xadrez | xadrez  | days       |
      | escola  | Pacote Eterno | xadrez  | lifetime   |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion |
      | marketplace | System       | 1         | my-index        | content       |

  Scenario: Assinatura com vencimento aparece, com a empresa
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         | timeend  |
      | aluno | Pacote Xadrez | +10 days |
    When I log in as "aluno"
    Then I should see "Pacote Xadrez" in the "block_marketplace" "block"
    And I should see "Escola Exemplo" in the "block_marketplace" "block"

  # Vitalicia sem cancelamento NAO entra, e isso e desenho e nao esquecimento:
  # ela nao pede nada do aluno, e ocuparia espaco para dizer "esta tudo bem".
  # Sem este cenario, a proxima sessao "conserta" isso achando que e bug.
  Scenario: Vitalicia tranquila nao ocupa espaco no bloco
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         |
      | aluno | Pacote Eterno |
    When I log in as "aluno"
    # Asserção de PAGINA, e nao "in the block": sem nada a mostrar o Moodle nao
    # renderiza o bloco, e exigir o bloco falharia com "block not found" - que e
    # exatamente o comportamento correto.
    Then I should not see "Pacote Eterno"

  Scenario: Vitalicia cancelada aparece
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         | norenew |
      | aluno | Pacote Eterno | 1       |
    When I log in as "aluno"
    Then I should see "Pacote Eterno" in the "block_marketplace" "block"

  # Isolamento entre alunos: o bloco le o $USER, e um erro aqui exporia a compra
  # de um aluno para outro.
  Scenario: A assinatura de um aluno nao aparece para o outro
    Given the following "local_marketplace > entitlements" exist:
      | user  | offer         | timeend  |
      | outro | Pacote Xadrez | +10 days |
    When I log in as "aluno"
    Then I should not see "Pacote Xadrez"
