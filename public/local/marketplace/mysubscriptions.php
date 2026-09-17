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
 * As assinaturas e os pagamentos do aluno.
 *
 * O bloco mostra o que exige acao; esta pagina mostra o resto - inclusive o que
 * ja venceu e o que ja foi pago. Separado de proposito: numa barra lateral, um
 * historico que cresce a cada mes empurraria para baixo justamente o aviso de
 * vencimento.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_payment\helper;
use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;
use local_marketplace\task\notify_expiring;

require_login();

$url = new moodle_url('/local/marketplace/mysubscriptions.php');
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mysubscriptions', 'local_marketplace'));
$PAGE->set_heading(get_string('mysubscriptions', 'local_marketplace'));

echo $OUTPUT->header();

// Inclui vencidos e cancelados: e historico, nao painel de controle.
//
// A direcao vai em PARAMETRO SEPARADO. O persistent monta o ORDER BY como
// "$sort . ' ' . $order", entao 'timeend DESC' aqui produzia
// "ORDER BY timeend DESC ASC" - SQL invalido, e a pagina inteira morria em
// dml_read_exception para qualquer aluno, inclusive quem nunca comprou nada.
$ents = entitlement::get_records(['userid' => (int) $USER->id], 'timeend', 'DESC');

if (!$ents) {
    echo $OUTPUT->notification(get_string('nosubscriptions', 'local_marketplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$now = time();
$notice = notify_expiring::NOTICE_DAYS * DAYSECS;

echo $OUTPUT->heading(get_string('mysubsactive', 'local_marketplace'), 3);

$table = new html_table();
$table->head = [
    get_string('offername', 'local_marketplace'),
    get_string('company', 'local_marketplace'),
    get_string('reportpayments', 'local_marketplace'),
    get_string('reportaccessuntil', 'local_marketplace'),
    get_string('companystatus', 'local_marketplace'),
    '',
];
$table->attributes['class'] = 'generaltable';

foreach ($ents as $ent) {
    $offer = offer::get_record(['id' => (int) $ent->get('offerid')]);
    $c = company::get_record(['id' => (int) $ent->get('companyid')]);
    if (!$offer || !$c) {
        continue;
    }

    $end = (int) $ent->get('timeend');
    $cancelled = (int) $ent->get('norenew') === 1;
    $recurring = $offer->get('accessmode') === offer::ACCESS_RECURRING;
    $expired = $end > 0 && $end <= $now;

    if ($ent->get('status') !== entitlement::STATUS_ACTIVE) {
        $badge = html_writer::tag(
            'span',
            get_string('reportsubcancelled', 'local_marketplace'),
            ['class' => 'badge bg-secondary']
        );
    } else if ($expired) {
        $badge = html_writer::tag(
            'span',
            get_string('reportsubexpired', 'local_marketplace'),
            ['class' => 'badge bg-danger']
        );
    } else if ($cancelled) {
        $badge = html_writer::tag(
            'span',
            get_string('blockcancelled', 'block_marketplace'),
            ['class' => 'badge bg-secondary']
        );
    } else if ($end > 0 && ($end - $now) < $notice) {
        $badge = html_writer::tag(
            'span',
            get_string('reportsubduesoon', 'local_marketplace', max(1, (int) ceil(($end - $now) / DAYSECS))),
            ['class' => 'badge bg-warning text-dark']
        );
    } else {
        $badge = html_writer::tag(
            'span',
            get_string('reportsubactive', 'local_marketplace'),
            ['class' => 'badge bg-success']
        );
    }

    // Pagar aparece para quem vence, venceu ou cancelou e mudou de ideia. Nao
    // aparece para assinatura que esgotou os ciclos - o botao levaria a uma
    // compra que a regra da oferta nao admite.
    $actions = '';
    $canpay = $recurring
        && $ent->get('status') === entitlement::STATUS_ACTIVE
        && $offer->accepts_cycle((int) $ent->get('cycles'))
        && ($expired || $cancelled || ($end > 0 && ($end - $now) < $notice));

    // A FATURA em aberto, quando existe, vale mais que a vitrine: numa
    // assinatura o gateway ja gerou a cobranca do ciclo, e com boleto ela ja tem
    // linha digitavel. Mandar para a vitrine e pedir que o aluno compre de novo
    // algo que ja esta cobrado.
    $fatura = api::pending_invoice_for('local_marketplace', (int) $offer->get('id'), (int) $USER->id);

    if ($fatura) {
        $actions = html_writer::link(
            $fatura['url'],
            get_string('payinvoice', 'local_marketplace'),
            ['class' => 'btn btn-sm btn-primary', 'target' => '_blank', 'rel' => 'noopener']
        );
        if ($fatura['line'] !== '') {
            $actions .= html_writer::tag(
                'div',
                html_writer::tag('code', s($fatura['line'])),
                ['class' => 'small text-muted mt-1']
            );
        }
    } else if ($canpay) {
        $actions = html_writer::link(
            new moodle_url('/local/marketplace/offers.php', [
                'company' => $c->get('shortname'),
                'highlight' => $offer->get('id'),
            ]),
            get_string('renewnow', 'local_marketplace'),
            ['class' => 'btn btn-sm btn-primary']
        );
    } else if ($recurring && !$cancelled && !$expired && $ent->get('status') === entitlement::STATUS_ACTIVE) {
        $actions = html_writer::link(
            new moodle_url('/local/marketplace/cancel.php', ['id' => $ent->get('id')]),
            get_string('cancelsubscription', 'local_marketplace'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
    }

    // A troca aparece JUNTO de qualquer outra acao, e nao no lugar dela: quem
    // paga por Pix/boleto pode querer trocar para cartao sem que isso
    // dependa de ter ou nao uma fatura aberta agora.
    if ($recurring && !$cancelled && !$expired && $ent->get('status') === entitlement::STATUS_ACTIVE) {
        $switchurl = api::switch_to_card_url_for('local_marketplace', (int) $offer->get('id'), (int) $USER->id);
        if ($switchurl) {
            $actions .= html_writer::div(
                html_writer::link(
                    $switchurl,
                    get_string('switchtocard', 'local_marketplace'),
                    ['class' => 'btn btn-sm btn-outline-primary mt-1']
                )
            );
        }
    }

    $table->data[] = [
        format_string($offer->get('name')),
        format_string($c->get('name')),
        (int) $ent->get('cycles'),
        $end > 0 ? userdate($end, get_string('strftimedaydate')) : '-',
        $badge,
        $actions,
    ];
}

echo html_writer::div(html_writer::table($table), 'table-responsive');

// Pagamentos.
echo $OUTPUT->heading(get_string('mysubspayments', 'local_marketplace'), 3);

// De qualquer gateway: o aluno pode ter comprado um curso pelo Pix de um meio
// e outro pelo cartao de outro, e a lista dele precisa mostrar os dois.
$payments = \local_marketplace\sale::get_for_user((int) $USER->id);

if (!$payments) {
    echo $OUTPUT->notification(get_string('nopayments', 'local_marketplace'), 'info');
} else {
    $ptable = new html_table();
    $ptable->head = [
        get_string('date'),
        get_string('offername', 'local_marketplace'),
        get_string('cost'),
        get_string('reportgateway', 'local_marketplace'),
        get_string('reportexternalid', 'local_marketplace'),
    ];
    $ptable->attributes['class'] = 'generaltable';

    foreach ($payments as $p) {
        $o = offer::get_record(['id' => (int) $p->offerid]);
        $ptable->data[] = [
            userdate((int) $p->timecreated, get_string('strftimedatetimeshort')),
            $o ? format_string($o->get('name')) : '#' . (int) $p->offerid,
            helper::get_cost_as_string((float) $p->amount, $p->currency),
            s($p->gateway),
            $p->externalid ? s($p->externalid) : '-',
        ];
    }

    echo html_writer::div(html_writer::table($ptable), 'table-responsive');
}

echo $OUTPUT->footer();
