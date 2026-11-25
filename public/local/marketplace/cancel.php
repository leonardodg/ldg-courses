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
 * Cancelamento de assinatura pelo aluno.
 *
 * Cancelar NAO revoga. Quem pagou 30 dias e cancela no dia 10 fica com os 20
 * que restam - ele pagou por eles, e tirar seria cobrar por servico nao
 * prestado. O que o cancelamento faz e parar os avisos de vencimento e deixar
 * o acesso terminar sozinho.
 *
 * Revogacao imediata existe, e outra coisa: status=cancelled, feito pelo
 * vendedor em caso de estorno ou fraude, onde o dinheiro voltou.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;

$entid = required_param('id', PARAM_INT);
$undo = optional_param('undo', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$ent = new entitlement($entid);

$company = company::get_record(['id' => (int) $ent->get('companyid')]);
$offer = offer::get_record(['id' => (int) $ent->get('offerid')]);
if (!$company || !$offer) {
    throw new moodle_exception('invalidrecord', 'error');
}

// O dono do direito cancela o proprio. Quem gere as vendas da EMPRESA tambem,
// porque o aluno que quer sair costuma escrever para o responsavel em vez de
// achar a tela - e mandar de volta para a tela e a forma mais rapida de o
// cancelamento nao acontecer e a cobranca seguir.
//
// A capability e verificada no contexto da CATEGORIA da empresa, e nao no
// sistema: quem gere uma empresa nao gere a assinatura de outra.
$owner = (int) $ent->get('userid') === (int) $USER->id;
$manager = has_capability('local/marketplace:managesales', $company->get_context());

if (!$owner && !$manager) {
    throw new moodle_exception('invalidaccess', 'error');
}

// Para onde voltar depois. O aluno volta para a vitrine, onde renova se mudar
// de ideia; o gestor volta para a lista de assinantes, que e de onde ele veio -
// mandar o gestor para a vitrine da empresa dele nao ajuda em nada.
$storefront = $owner
    ? new moodle_url('/local/marketplace/offers.php', ['company' => $company->get('shortname')])
    : new moodle_url('/local/marketplace/report.php', [
        'company' => $company->get('shortname'),
        'view' => 'subscriptions',
    ]);
$url = new moodle_url('/local/marketplace/cancel.php', ['id' => $entid]);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('cancelsubscription', 'local_marketplace'));
$PAGE->set_heading(format_string($company->get('name')));

// Voltar atras nao precisa de confirmacao: reativar a renovacao nao tira nada
// de ninguem.
if ($undo) {
    require_sesskey();
    $ent->set('norenew', 0);
    $ent->update();
    redirect(
        $storefront,
        get_string('cancelundone', 'local_marketplace'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if (!$confirm) {
    $until = (int) $ent->get('timeend');

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        $until > 0
            ? get_string('cancelconfirm', 'local_marketplace', (object) [
                'offer' => format_string($offer->get('name')),
                'date' => userdate($until, get_string('strftimedaydate')),
            ])
            : get_string(
                'cancelconfirmlifetime',
                'local_marketplace',
                format_string($offer->get('name'))
            ),
        new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
        $storefront
    );
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

$ent->set('norenew', 1);
$ent->update();

// Marcar a coluna nao para de cobrar. Onde o gateway criou assinatura de
// verdade - o Asaas cria -, quem cancela precisa dizer isso a ele, senao o
// aluno que pediu para sair continua sendo debitado todo mes.
//
// Vem DEPOIS do update: se a chamada externa cair, o cancelamento no Moodle
// ja valeu. O contrario deixaria o aluno preso a uma tela que falha.
api::stop_recurring_billing('local_marketplace', (int) $ent->get('offerid'), (int) $ent->get('userid'));

redirect(
    $storefront,
    get_string(
        'canceldone',
        'local_marketplace',
        (int) $ent->get('timeend') > 0
            ? userdate((int) $ent->get('timeend'), get_string('strftimedaydate'))
        : '-'
    ),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
