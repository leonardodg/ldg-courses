<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Capabilities do marketplace.
 *
 * O onboarding e self-service: qualquer usuario autenticado cria empresa.
 * O que limita nao e aprovacao manual, sao dois portoes estruturais:
 *
 *   1. VENDER exige a empresa ter meio de pagamento configurado. Isso NAO e uma
 *      capability - e estado, verificado em company::can_sell(). Capability
 *      responderia "tem permissao?", e a pergunta aqui e "esta habilitado?".
 *
 *   2. CRIAR CURSO e dado pelo papel de vendedor, que nao concede as
 *      capabilities de upload de arquivo. Ver db/install.php.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Criar empresa. NAO e concedida a ninguem por padrao, e NAO e
    // auto-atendimento: nenhum usuario autenticado ganha isto sozinho.
    //
    // Criar empresa cria uma CATEGORIA, que e objeto global: aparece na arvore
    // que todos os usuarios veem. Dar isso a quem acabou de se cadastrar seria
    // entregar a estrutura do site a desconhecidos.
    //
    // A parceria e fechada fora do sistema e o administrador provisiona a
    // empresa. O site admin passa por aqui de qualquer forma.
    'local/marketplace:createcompany' => [
        'riskbitmask' => RISK_CONFIG | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],

    // Administrar a empresa: dados cadastrais, membros, tema, dominio.
    // Atribuida no contexto da CATEGORIA da empresa, nao no sistema.
    'local/marketplace:managecompany' => [
        'riskbitmask' => RISK_PERSONAL | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Configurar o meio de pagamento da empresa.
    // Separada de managecompany porque mexe em credencial financeira.
    'local/marketplace:managepayment' => [
        'riskbitmask' => RISK_PERSONAL | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],

    // Publicar curso na categoria da empresa.
    'local/marketplace:publishcourse' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],

    // Ver o relatorio financeiro da empresa (bruto, taxa MP, comissao, liquido).
    'local/marketplace:viewreport' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],

    // Gerir as vendas e assinaturas da empresa: cancelar a assinatura de um
    // aluno, reenviar fatura, acompanhar ciclo.
    //
    // Separada da viewreport de proposito, e a diferenca e AGIR versus OLHAR.
    // Ver quem assinou e leitura; cancelar a assinatura de outra pessoa mexe
    // no dinheiro dela e no acesso dela. Quem so acompanha numero nao precisa
    // do segundo.
    'local/marketplace:managesales' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],

    // Estornar uma venda: devolve o dinheiro no gateway e revoga o acesso.
    //
    // NAO VAI PARA NENHUM PAPEL POR PADRAO, nem para o gerente da empresa.
    // Estorno e irreversivel do lado de fora - o dinheiro sai da conta do
    // vendedor, a comissao volta da plataforma, e nao ha "desfazer". Fica com
    // o administrador da plataforma, que a concede por empresa quando confiar
    // em quem vai usar.
    //
    // O caminho normal e o aluno procurar o responsavel da empresa, e ele
    // decidir. Se a empresa for madura para operar isto sozinha, basta
    // atribuir esta capability ao papel dela no contexto da categoria.
    'local/marketplace:refundsale' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [],
    ],

    // Administrar TODAS as empresas. Do dono da plataforma, nao do vendedor.
    'local/marketplace:manageall' => [
        'riskbitmask' => RISK_PERSONAL | RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
