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
 * Relatorios da empresa: transacoes, cursos vendidos, alunos e assinaturas.
 *
 * O que estes relatorios NAO fazem: estimar o liquido. A taxa do gateway varia
 * por meio de pagamento e por prazo de recebimento, e nao volta na notificacao.
 * Uma coluna "liquido" calculada por percentual fixo divergiria do extrato - e
 * relatorio financeiro que discorda do extrato e pior que nenhum.
 *
 * As tres primeiras abas saem da VENDA; a de alunos sai do DIREITO DE ACESSO.
 * Nao e detalhe: quem pegou uma oferta gratuita, ou teve o direito criado a
 * mao, e aluno da empresa sem nunca ter aparecido numa venda.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_payment\helper;
use local_marketplace\company;
use local_marketplace\offer;
use local_marketplace\entitlement;

$shortname = required_param('company', PARAM_ALPHANUMEXT);
$view = optional_param('view', 'transactions', PARAM_ALPHA);
$from = optional_param('from', 0, PARAM_INT);

require_login();

$company = company::get_record(['shortname' => $shortname]);
if (!$company) {
    throw new moodle_exception('invalidrecord', 'error');
}

$context = $company->get_context();
$url = new moodle_url('/local/marketplace/report.php', ['company' => $shortname, 'view' => $view]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('reportsection', 'local_marketplace'));
$PAGE->set_heading(format_string($company->get('name')));

require_capability('local/marketplace:viewreport', $context);

$accounts = $company->get_payment_accounts();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportsection', 'local_marketplace'), 3);

if (!$accounts) {
    echo $OUTPUT->notification(get_string('errornoaccount', 'local_marketplace'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Navegacao.
$views = [
    'transactions' => get_string('reportviewtransactions', 'local_marketplace'),
    'courses' => get_string('reportviewcourses', 'local_marketplace'),
    'students' => get_string('reportviewstudents', 'local_marketplace'),
    'subscriptions' => get_string('reportviewsubscriptions', 'local_marketplace'),
];
$tabs = [];
foreach ($views as $key => $label) {
    $tabs[] = html_writer::link(
        new moodle_url('/local/marketplace/report.php', ['company' => $shortname, 'view' => $key]),
        $label,
        ['class' => 'btn btn-sm ' . ($view === $key ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $tabs), 'mb-3');

// Assinaturas seguem a validade do direito, nao a data da venda: filtrar por
// periodo ali esconderia justamente quem esta prestes a vencer.
if ($view !== 'subscriptions') {
    $periods = [
        0 => get_string('reportall', 'local_marketplace'),
        30 => get_string('reportdays', 'local_marketplace', 30),
        90 => get_string('reportdays', 'local_marketplace', 90),
        365 => get_string('reportdays', 'local_marketplace', 365),
    ];
    $links = [];
    foreach ($periods as $days => $label) {
        $links[] = html_writer::link(
            new moodle_url(
                '/local/marketplace/report.php',
                ['company' => $shortname, 'view' => $view, 'from' => $days]
            ),
            $label,
            ['class' => 'btn btn-sm ' . ((int) $from === (int) $days ? 'btn-secondary' : 'btn-outline-secondary')]
        );
    }
    echo html_writer::div(implode(' ', $links), 'mb-3');
}

// Vendas concluidas, de QUALQUER gateway.
//
// Ate a versao anterior isto era um SELECT em paygw_mercadopago. Com um
// segundo meio de pagamento aquilo passaria a mentir por omissao: a venda
// existiria, o aluno estaria matriculado, e o total simplesmente nao contaria
// aquele dinheiro - sem erro e sem aviso.
$since = ($from > 0 && $view !== 'subscriptions') ? time() - ($from * DAYSECS) : 0;
$sales = \local_marketplace\sale::get_for_company((int) $company->get('id'), $since);

// TRANSACOES.
if ($view === 'transactions') {
    if (!$sales) {
        echo $OUTPUT->notification(get_string('reportnosales', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    $totals = [];
    foreach ($sales as $s) {
        $cur = $s->currency;
        $totals[$cur] = $totals[$cur] ?? ['gross' => 0.0, 'fee' => 0.0, 'count' => 0];
        $totals[$cur]['gross'] += (float) $s->amount;
        $totals[$cur]['fee'] += (float) $s->feeamount;
        $totals[$cur]['count']++;
    }

    // Moedas separadas: somar BRL com ARS produziria um numero sem significado.
    foreach ($totals as $cur => $t) {
        $summary = new html_table();
        $summary->attributes['class'] = 'generaltable w-auto';
        $summary->data = [
            [get_string('reportsales', 'local_marketplace'), $t['count']],
            [get_string('reportgross', 'local_marketplace'), helper::get_cost_as_string($t['gross'], $cur)],
            [get_string('reportcommission', 'local_marketplace'), helper::get_cost_as_string($t['fee'], $cur)],
        ];
        echo html_writer::tag('h4', s($cur), ['class' => 'h6 mt-3']);
        echo html_writer::table($summary);
    }

    echo $OUTPUT->notification(get_string('reportnetnotice', 'local_marketplace'), 'info');

    $table = new html_table();
    $table->head = [
        get_string('date'),
        get_string('offername', 'local_marketplace'),
        get_string('user'),
        get_string('reportgross', 'local_marketplace'),
        get_string('reportcommission', 'local_marketplace'),
        get_string('reportcommissionterms', 'local_marketplace'),
        get_string('reportgateway', 'local_marketplace'),
        get_string('reportexternalid', 'local_marketplace'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    // Estornar devolve dinheiro de verdade e revoga o acesso, e nao tem
    // desfazer. Nao vai para papel nenhum por padrao - nem o gerente tem, a
    // menos que o administrador da plataforma conceda.
    $podeestornar = has_capability('local/marketplace:refundsale', $company->get_context());

    foreach ($sales as $s) {
        $o = offer::get_record(['id' => (int) $s->offerid]);
        $u = \core_user::get_user((int) $s->userid, '*', IGNORE_MISSING);
        $table->data[] = [
            userdate((int) $s->timecreated, get_string('strftimedatetimeshort')),
            $o ? format_string($o->get('name')) : '#' . (int) $s->offerid,
            $u ? fullname($u) : '?',
            helper::get_cost_as_string((float) $s->amount, $s->currency),
            helper::get_cost_as_string((float) $s->feeamount, $s->currency),
            // Os termos APLICADOS naquela venda, e nao os de hoje: e por isso
            // que eles foram fotografados na linha.
            format_float((float) $s->feepercent, 2) . '% '
                . get_string('commissionbase' . $s->feebase, 'local_marketplace')
                . html_writer::tag(
                    'div',
                    get_string('commissionsource' . $s->feesource, 'local_marketplace'),
                    ['class' => 'text-muted small']
                ),
            s($s->gateway),
            $s->externalid ? s($s->externalid) : '-',
            // O botao some quando o gateway diz que nao da: assinatura no meio
            // do ciclo, cobranca ainda nao paga, ou estorno ja feito. Melhor
            // nao existir do que aparecer e falhar no clique de quem esta
            // resolvendo um problema.
            $podeestornar && \local_marketplace\api::refund_blocker((int) $s->paymentid) === ''
                ? html_writer::link(
                    new moodle_url('/local/marketplace/refund.php', ['payment' => (int) $s->paymentid]),
                    get_string('refundsale', 'local_marketplace'),
                    ['class' => 'btn btn-sm btn-outline-danger']
                )
                : '',
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}

// CURSOS VENDIDOS.
if ($view === 'courses') {
    if (!$sales) {
        echo $OUTPUT->notification(get_string('reportnosales', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    // Uma venda de combo conta INTEIRA para cada curso que ela libera. Ratear
    // o valor entre os cursos daria a impressao de uma receita por curso que
    // nao existe: ninguem comprou "um terco do combo". A soma da coluna
    // ultrapassa o faturamento de proposito, e o aviso abaixo diz isso.
    $percourse = [];
    foreach ($sales as $s) {
        $o = offer::get_record(['id' => (int) $s->offerid]);
        if (!$o) {
            continue;
        }
        foreach ($o->get_course_ids() as $courseid) {
            $courseid = (int) $courseid;
            $percourse[$courseid] = $percourse[$courseid] ?? ['count' => 0, 'gross' => 0.0, 'cur' => $s->currency];
            $percourse[$courseid]['count']++;
            $percourse[$courseid]['gross'] += (float) $s->amount;
        }
    }

    if (!$percourse) {
        echo $OUTPUT->notification(get_string('reportnocourses', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    uasort($percourse, fn($a, $b) => $b['gross'] <=> $a['gross']);

    $table = new html_table();
    $table->head = [
        get_string('course'),
        get_string('reportsaleswith', 'local_marketplace'),
        get_string('reportgross', 'local_marketplace'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($percourse as $courseid => $d) {
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
        $name = $course
            ? html_writer::link(
                new moodle_url('/course/view.php', ['id' => $courseid]),
                format_string($course->fullname)
            )
            : '#' . $courseid;
        $table->data[] = [
            $name,
            $d['count'],
            helper::get_cost_as_string($d['gross'], $d['cur']),
        ];
    }

    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->notification(get_string('reportcoursesnotice', 'local_marketplace'), 'info');
}

// ALUNOS.
//
// Sai do direito de acesso, e nao da venda: um aluno que ganhou acesso a uma
// oferta gratuita, ou que teve o direito criado a mao, e aluno do mesmo jeito.
// Ler a venda deixaria essa gente de fora, e a pergunta aqui e "quem sao os
// meus alunos", nao "quem me pagou".
if ($view === 'students') {
    $rows = entitlement::get_for_company((int) $company->get('id'));

    if (!$rows) {
        echo $OUTPUT->notification(get_string('reportnostudents', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    $active = entitlement::count_active_students((int) $company->get('id'));

    $summary = new html_table();
    $summary->attributes['class'] = 'generaltable w-auto';
    $summary->data = [
        // Alunos distintos com direito vigente. Um aluno com tres ofertas e um
        // aluno so - contar linhas daria um numero maior que a base real.
        [get_string('reportstudentsactive', 'local_marketplace'), $active],
        [get_string('reportstudentsrows', 'local_marketplace'), count($rows)],
    ];
    echo html_writer::table($summary);

    $now = time();
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('email'),
        get_string('offername', 'local_marketplace'),
        get_string('reportpayments', 'local_marketplace'),
        get_string('reportstudentsince', 'local_marketplace'),
        get_string('reportaccessuntil', 'local_marketplace'),
        get_string('companystatus', 'local_marketplace'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    // Cancelar a assinatura de um aluno exige managesales, e nao viewreport:
    // ver quem assinou e leitura, cancelar mexe no dinheiro e no acesso de
    // outra pessoa. Quem so acompanha numero nao ve o botao.
    $podegerir = has_capability('local/marketplace:managesales', $company->get_context());

    /**
     * Botoes da linha do assinante.
     *
     * O reenvio existe porque "nao recebi o boleto" e o motivo mais comum de
     * uma mensalidade nao ser paga, e ate agora a unica saida do gerente era
     * copiar o link na mao - se ele soubesse onde achar.
     *
     * @param \stdClass $row Linha de entitlement::get_for_company()
     * @return string
     */
    function acoes_da_assinatura(\stdClass $row): string {
        $botoes = [];

        if ($row->status === entitlement::STATUS_ACTIVE && !$row->norenew) {
            $botoes[] = html_writer::link(
                new moodle_url('/local/marketplace/resend.php', ['id' => $row->id]),
                get_string('resendinvoice', 'local_marketplace'),
                ['class' => 'btn btn-sm btn-outline-primary']
            );
            $botoes[] = html_writer::link(
                new moodle_url('/local/marketplace/cancel.php', ['id' => $row->id]),
                get_string('cancelsubscription', 'local_marketplace'),
                ['class' => 'btn btn-sm btn-outline-secondary']
            );
        }

        return implode(' ', $botoes);
    }

    foreach ($rows as $row) {
        $timeend = (int) $row->timeend;

        // A situacao gravada so muda quando o cron roda, entao confiar so nela
        // mostraria como vigente quem ja venceu ontem a noite. A data manda.
        if ($row->status !== entitlement::STATUS_ACTIVE) {
            $badge = html_writer::tag('span', get_string('reportsubcancelled', 'local_marketplace'), [
                'class' => 'badge bg-secondary',
            ]);
        } else if ($timeend > 0 && $timeend <= $now) {
            $badge = html_writer::tag('span', get_string('reportsubexpired', 'local_marketplace'), [
                'class' => 'badge bg-danger',
            ]);
        } else {
            $badge = html_writer::tag('span', get_string('reportsubactive', 'local_marketplace'), [
                'class' => 'badge bg-success',
            ]);
        }

        if ($row->norenew) {
            $badge .= ' ' . html_writer::tag('span', get_string('reportsubnorenew', 'local_marketplace'), [
                'class' => 'badge bg-warning text-dark',
            ]);
        }

        $table->data[] = [
            html_writer::link(
                new moodle_url('/user/view.php', ['id' => $row->userid, 'course' => SITEID]),
                fullname($row)
            ),
            s($row->email),
            format_string($row->offername),
            (int) $row->cycles,
            userdate((int) $row->timecreated, get_string('strftimedaydate')),
            $timeend > 0 ? userdate($timeend, get_string('strftimedaydate')) : get_string('accesslifetime', 'local_marketplace'),
            $badge,
            // So para assinatura vigente que ainda cobra: cancelar o que ja
            // acabou, ou o que ja foi cancelado, nao para cobranca nenhuma.
            $podegerir ? acoes_da_assinatura($row) : '',
        ];
    }

    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->notification(get_string('reportstudentsnotice', 'local_marketplace'), 'info');
}

// ASSINATURAS.
if ($view === 'subscriptions') {
    echo $OUTPUT->notification(get_string('reportsubsnotice', 'local_marketplace'), 'warning');

    // Assinatura aqui e oferta com accessmode=recurring. Nao ha plano nem
    // parcela: cada renovacao e uma compra avulsa que estende a validade.
    $recurring = [];
    foreach (offer::get_records(['companyid' => (int) $company->get('id')]) as $o) {
        if ($o->get('accessmode') === offer::ACCESS_RECURRING) {
            $recurring[(int) $o->get('id')] = $o;
        }
    }

    if (!$recurring) {
        echo $OUTPUT->notification(get_string('reportnosubs', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($recurring), SQL_PARAMS_NAMED);
    $ents = $DB->get_records_select(
        'local_marketplace_entitlement',
        "companyid = :companyid AND offerid $insql",
        array_merge(['companyid' => (int) $company->get('id')], $inparams),
        'timeend DESC'
    );

    if (!$ents) {
        echo $OUTPUT->notification(get_string('reportnosubs', 'local_marketplace'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    // Quantas vezes cada aluno pagou cada assinatura. E o mais proximo de
    // "mensalidades pagas" que os dados permitem: cada venda registrada e um
    // pagamento efetivo daquela oferta, em qualquer gateway.
    $paid = [];
    foreach ($sales as $s) {
        $key = (int) $s->userid . ':' . (int) $s->offerid;
        $paid[$key] = $paid[$key] ?? ['n' => 0, 'last' => 0];
        $paid[$key]['n']++;
        $paid[$key]['last'] = max($paid[$key]['last'], (int) $s->timecreated);
    }

    $now = time();
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('offername', 'local_marketplace'),
        get_string('reportpaymentmethod', 'local_marketplace'),
        get_string('reportpayments', 'local_marketplace'),
        get_string('reportlastpayment', 'local_marketplace'),
        get_string('reportaccessuntil', 'local_marketplace'),
        get_string('companystatus', 'local_marketplace'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($ents as $e) {
        $u = \core_user::get_user((int) $e->userid, '*', IGNORE_MISSING);
        $o = $recurring[(int) $e->offerid] ?? null;
        $key = (int) $e->userid . ':' . (int) $e->offerid;
        $timeend = (int) $e->timeend;

        if ($e->status !== 'active') {
            $badge = html_writer::tag(
                'span',
                get_string('reportsubcancelled', 'local_marketplace'),
                ['class' => 'badge bg-secondary']
            );
        } else if ($timeend > 0 && $timeend <= $now) {
            $badge = html_writer::tag(
                'span',
                get_string('reportsubexpired', 'local_marketplace'),
                ['class' => 'badge bg-danger']
            );
        } else if ($timeend > 0 && ($timeend - $now) < (7 * DAYSECS)) {
            $badge = html_writer::tag(
                'span',
                get_string('reportsubduesoon', 'local_marketplace', max(1, (int) ceil(($timeend - $now) / DAYSECS))),
                ['class' => 'badge bg-warning text-dark']
            );
        } else {
            $badge = html_writer::tag(
                'span',
                get_string('reportsubactive', 'local_marketplace'),
                ['class' => 'badge bg-success']
            );
        }

        $method = \local_marketplace\api::payment_method_for('local_marketplace', (int) $e->offerid, (int) $e->userid);

        $table->data[] = [
            $u ? fullname($u) : '?',
            $o ? format_string($o->get('name')) : '#' . (int) $e->offerid,
            $method ?? '-',
            $paid[$key]['n'] ?? 0,
            !empty($paid[$key]['last'])
                ? userdate($paid[$key]['last'], get_string('strftimedateshort'))
                : '-',
            $timeend > 0 ? userdate($timeend, get_string('strftimedateshort')) : '-',
            $badge,
        ];
    }

    echo html_writer::div(html_writer::table($table), 'table-responsive');
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/company.php', ['company' => $shortname]),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
