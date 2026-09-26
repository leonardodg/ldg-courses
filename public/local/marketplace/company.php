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
 * Painel da empresa: o que o vendedor precisa para operar.
 *
 * Existe porque o Moodle NAO tem navegacao para conta de pagamento em
 * contexto de categoria. As tres telas do core (accounts, manage_account,
 * manage_gateway) sao alcancaveis apenas pela lista, e a lista consulta
 * get_payment_accounts_to_manage(context_system::instance()), que filtra por
 * contexto EXATO. A conta da empresa vive na categoria dela, entao nunca
 * aparece ali - so por URL montada a mao.
 *
 * Poderiamos ter criado a conta no contexto do sistema para ganhar a tela
 * nativa, mas ai todo vendedor com a capability veria e editaria a conta dos
 * outros. Num marketplace isso e inaceitavel; a tela e o preco de manter o
 * isolamento.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_marketplace\company;
use local_marketplace\member;
use local_marketplace\offer;
use local_marketplace\payment\service_provider;
use local_marketplace\plan;

$shortname = optional_param('company', '', PARAM_ALPHANUMEXT);

require_login();

// Sem parametro, mostra as empresas do usuario. Um vendedor pode participar
// de mais de uma.
if ($shortname === '') {
    $companies = company::get_by_member((int) $USER->id);
    if (count($companies) === 1) {
        redirect(new moodle_url('/local/marketplace/company.php', [
            'company' => reset($companies)->get('shortname'),
        ]));
    }
    $shortname = $companies ? reset($companies)->get('shortname') : '';
    if ($shortname === '') {
        throw new moodle_exception('nocompany', 'local_marketplace');
    }
}

$company = company::get_record(['shortname' => $shortname]);
if (!$company) {
    throw new moodle_exception('invalidrecord', 'error');
}

$context = $company->get_context();
$url = new moodle_url('/local/marketplace/company.php', ['company' => $shortname]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($company->get('name')));
$PAGE->set_heading(format_string($company->get('name')));

// Ser membro nao basta para administrar; e a capability que decide, e ela e
// avaliada no contexto da categoria da empresa.
require_capability('local/marketplace:managecompany', $context);

// Trocar de plano NAO cobra nada aqui - so aponta company.planid para o
// escolhido. A cobranca de verdade acontece so quando o gerente clica em
// "pagar assinatura", que le o plano JA selecionado
// (service_provider::get_payable_plan()). Sem essa separacao, mudar de ideia
// no <select> ja teria efeito colateral de dinheiro.
if (data_submitted() && confirm_sesskey() && optional_param('changeplan', 0, PARAM_BOOL)) {
    $novoplanoid = required_param('planid', PARAM_INT);
    $novoplano = plan::get_record(['id' => $novoplanoid, 'status' => plan::STATUS_ACTIVE]);

    if ($novoplano) {
        $company->set('planid', $novoplanoid);
        $company->update();
        // Best-effort - a troca de plano ja aconteceu; ver
        // api::sync_video_library_resolution().
        \local_marketplace\api::sync_video_library_resolution($company);
    }

    redirect($url);
}

// Conectar a library da PROPRIA conta Bunny do produtor (Frente A, BYOS).
// So aparece pro plano certo - o formulario abaixo nem mostra o campo fora
// disso, mas o servidor confere de novo aqui, porque URL se forja.
if (data_submitted() && confirm_sesskey() && optional_param('connectbyos', 0, PARAM_BOOL)) {
    $bunnylibraryid = required_param('bunnylibraryid', PARAM_INT);
    $apikey = required_param('apikey', PARAM_RAW_TRIMMED);
    $securitykey = optional_param('securitykey', '', PARAM_RAW_TRIMMED);
    $cdnhostname = optional_param('cdnhostname', '', PARAM_HOST);

    if ($bunnylibraryid > 0 && $apikey !== '') {
        \local_marketplace\api::connect_byos_library(
            $company,
            $bunnylibraryid,
            $apikey,
            $securitykey !== '' ? $securitykey : null,
            $cdnhostname !== '' ? $cdnhostname : null
        );
    }

    redirect($url);
}

echo $OUTPUT->header();

// Meio de pagamento.
echo $OUTPUT->heading(get_string('paymentsection', 'local_marketplace'), 3);

// Uma secao por PAIS: a empresa tem uma conta em cada mercado onde vende, e
// cada uma tem os proprios gateways. Juntar tudo numa lista so faria o vendedor
// argentino ver botao de gateway que nao opera na Argentina.
$accounts = $company->get_payment_accounts();

if (!$accounts) {
    echo $OUTPUT->notification(get_string('errornoaccount', 'local_marketplace'), 'error');
} else {
    foreach ($accounts as $countrycode => $account) {
        echo $OUTPUT->heading(\local_marketplace\country::describe($countrycode), 4);

        $enabled = [];
        $configured = [];
        foreach ($account->get_gateways() as $name => $gw) {
            if (!$gw->get('id')) {
                continue;
            }
            if ($gw->get('enabled')) {
                $enabled[] = $name;
            }
            // Ha credencial guardada mesmo que o gateway esteja desligado. Sem
            // separar os dois casos, uma empresa ja vinculada aparecia como
            // "sem meio de pagamento" - mandando o vendedor refazer um vinculo
            // que ja estava feito.
            if ($gw->get_configuration()) {
                $configured[] = $name;
            }
        }

        if ($company->can_sell($countrycode)) {
            echo $OUTPUT->notification(
                get_string('cansellyes', 'local_marketplace', implode(', ', $enabled)),
                'success'
            );
        } else if ($configured) {
            echo $OUTPUT->notification(get_string('linkednotenabled', 'local_marketplace'), 'warning');
        } else {
            echo $OUTPUT->notification(get_string('nopaymentaccount', 'local_marketplace'), 'warning');
        }

        // Um botao por gateway que atende aquele pais. A lista vem do que cada
        // gateway declara atender, e nao de um nome escrito aqui: o literal
        // 'mercadopago' que existia neste lugar era o que impedia um segundo
        // meio de pagamento de aparecer.
        $buttons = [];
        foreach (\local_marketplace\api::gateways_for_country($countrycode) as $name) {
            $buttons[] = html_writer::link(
                new moodle_url('/payment/manage_gateway.php', [
                    'accountid' => $account->get('id'),
                    'gateway' => $name,
                ]),
                get_string('pluginname', 'paygw_' . $name),
                ['class' => 'btn btn-sm ' . (in_array($name, $enabled, true) ? 'btn-outline-primary' : 'btn-primary')]
            );
        }

        echo html_writer::div(
            $buttons
                ? implode(' ', $buttons)
                : $OUTPUT->notification(get_string('nogatewayforcountry', 'local_marketplace'), 'warning'),
            'mb-4'
        );
    }
}

// Plano - a assinatura SaaS que ESTA empresa paga a PLATAFORMA. E o oposto
// da secao de meio de pagamento acima: ali e a empresa recebendo do aluno,
// aqui e a empresa pagando a nos.
echo $OUTPUT->heading(get_string('planssection', 'local_marketplace'), 3);

$planoatual = $company->get_plan();

if ($planoatual) {
    echo $OUTPUT->notification(
        get_string('currentplan', 'local_marketplace', format_string($planoatual->get('name'))),
        'info'
    );

    if ((float) $planoatual->get('monthlyfee') > 0) {
        $expiry = (int) $company->get('planexpiry');
        echo html_writer::div(
            $expiry > 0
                ? get_string('planexpiryon', 'local_marketplace', userdate($expiry, get_string('strftimedaydate')))
                : get_string('plannotpaidyet', 'local_marketplace'),
            'text-muted small mb-2'
        );

        echo html_writer::div(
            \core_payment\helper::get_cost_as_string((float) $planoatual->get('monthlyfee'), $planoatual->get('currency')),
            'h5 mb-2'
        );

        // Mesmo padrao de offers.php: o modal do core escolhe o gateway, o
        // gateway escolhido resolve o pagamento pela paymentarea 'plan' -
        // que aponta para a conta da PLATAFORMA, nao da empresa.
        $attrs = \core_payment\helper::gateways_modal_link_params(
            'local_marketplace',
            service_provider::PAYMENT_AREA_PLAN,
            (int) $company->get('id'),
            format_string($planoatual->get('name'))
        );
        $attrs['id'] = 'pay-plan-' . $company->get('id');
        $attrs['class'] = 'btn btn-primary mb-4';
        echo html_writer::tag('button', get_string('paysubscription', 'local_marketplace'), $attrs);

        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
    }
} else {
    echo $OUTPUT->notification(get_string('noplan', 'local_marketplace'), 'warning');
}

// Trocar de plano. So os ATIVOS aparecem - um plano arquivado nao pode ser
// escolhido de novo, mesmo que a empresa ja estivesse nele antes.
$opcoesplano = [];
foreach (plan::get_public_plans() as $p) {
    $opcoesplano[(int) $p->get('id')] = format_string($p->get('name'));
}

if ($opcoesplano) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'form-inline mb-4',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'changeplan', 'value' => '1']);
    echo html_writer::select(
        $opcoesplano,
        'planid',
        (int) $company->get('planid'),
        false,
        ['class' => 'form-select d-inline-block w-auto me-2']
    );
    echo html_writer::tag(
        'button',
        get_string('selectplan', 'local_marketplace'),
        ['type' => 'submit', 'class' => 'btn btn-outline-primary']
    );
    echo html_writer::end_tag('form');
}

// Video BYOS (Frente A) - so aparece no plano que exige o produtor trazer a
// propria conta Bunny. Nos outros planos a library e provisionada sozinha
// (Frente B), e nao ha nada aqui para o vendedor fazer.
if ($planoatual && $planoatual->get('hostingmodel') === plan::HOSTING_BYOS) {
    echo $OUTPUT->heading(get_string('byossection', 'local_marketplace'), 3);
    echo html_writer::div(get_string('byosintro', 'local_marketplace'), 'text-muted small mb-2');

    $library = \local_marketplace\library_account::get_for((int) $company->get('id'));
    if ($library) {
        echo $OUTPUT->notification(
            get_string('byosconnected', 'local_marketplace', $library->get('bunnylibraryid')),
            'success'
        );
    } else {
        echo $OUTPUT->notification(get_string('byosnotconnected', 'local_marketplace'), 'warning');
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'mb-4',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'connectbyos', 'value' => '1']);

    echo html_writer::start_div('mb-2');
    echo html_writer::label(get_string('byoslibraryid', 'local_marketplace'), 'id_bunnylibraryid');
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'min' => '1', 'name' => 'bunnylibraryid', 'id' => 'id_bunnylibraryid',
        'value' => $library ? $library->get('bunnylibraryid') : '',
        'class' => 'form-control', 'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::label(get_string('byosapikey', 'local_marketplace'), 'id_apikey');
    echo html_writer::div(get_string('byosapikey_help', 'local_marketplace'), 'form-text text-muted');
    echo html_writer::empty_tag('input', [
        'type' => 'password', 'name' => 'apikey', 'id' => 'id_apikey',
        'class' => 'form-control', 'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::label(get_string('byossecuritykey', 'local_marketplace'), 'id_securitykey');
    echo html_writer::empty_tag('input', [
        'type' => 'password', 'name' => 'securitykey', 'id' => 'id_securitykey', 'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::label(get_string('byoscdnhostname', 'local_marketplace'), 'id_cdnhostname');
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'cdnhostname', 'id' => 'id_cdnhostname',
        'value' => $library ? $library->get('cdnhostname') : '',
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::tag(
        'button',
        get_string('byosconnect', 'local_marketplace'),
        ['type' => 'submit', 'class' => 'btn btn-primary']
    );
    echo html_writer::end_tag('form');
}

// Ofertas.
echo $OUTPUT->heading(get_string('offerssection', 'local_marketplace'), 3);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/offer_edit.php', ['company' => $shortname]),
        get_string('offercreate', 'local_marketplace'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

$offers = offer::get_records(['companyid' => $company->get('id')], 'sortorder, name');

if (!$offers) {
    echo $OUTPUT->notification(get_string('nooffers', 'local_marketplace'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('offername', 'local_marketplace'),
        get_string('offertype', 'local_marketplace'),
        get_string('cost'),
        get_string('courses'),
        get_string('offeraccess', 'local_marketplace'),
        get_string('companystatus', 'local_marketplace'),
        '',
    ];
    foreach ($offers as $o) {
        $table->data[] = [
            format_string($o->get('name')),
            get_string('type' . $o->get('offertype'), 'local_marketplace'),
            $o->is_free()
                ? get_string('free', 'local_marketplace')
                : \core_payment\helper::get_cost_as_string((float) $o->get('price'), $o->get('currency')),
            count($o->get_course_ids()),
            $o->describe_billing(),
            get_string('status' . $o->get('status'), 'local_marketplace'),
            html_writer::link(
                new moodle_url('/local/marketplace/offer_edit.php', [
                    'company' => $shortname,
                    'id' => $o->get('id'),
                ]),
                get_string('edit'),
                ['class' => 'btn btn-sm btn-secondary']
            ),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/marketplace/offers.php', ['company' => $shortname]),
        get_string('viewstorefront', 'local_marketplace'),
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/course/index.php', ['categoryid' => $company->get('categoryid')]),
        get_string('managecourses', 'local_marketplace'),
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    (has_capability('local/marketplace:viewreport', $context)
        ? html_writer::link(
            new moodle_url('/local/marketplace/report.php', ['company' => $shortname]),
            get_string('reportsection', 'local_marketplace'),
            ['class' => 'btn btn-secondary']
        )
        : ''),
    'mb-4'
);

// Vendedores.
echo $OUTPUT->heading(get_string('members', 'local_marketplace'), 3);

$table = new html_table();
$table->head = [get_string('fullname'), get_string('role')];
foreach (member::get_by_company((int) $company->get('id')) as $m) {
    $user = core_user::get_user((int) $m->get('userid'));
    $table->data[] = [
        $user ? fullname($user) : '?',
        get_string('member' . $m->get('memberrole'), 'local_marketplace'),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
