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
 * Ofertas de uma empresa.
 *
 * Vitrine minima: lista o que esta a venda e dispara o modal de pagamento do
 * core_payment. Na Fase 4 vira a pagina publica do vendedor, com o tema dele.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_payment\helper;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;

$shortname = required_param('company', PARAM_ALPHANUMEXT);

// Oferta a destacar. Chega de quem clicou no botao de um topico bloqueado: sem
// isto o aluno cai numa lista e tem que adivinhar qual das ofertas destrava o
// conteudo que ele estava vendo.
$highlight = optional_param('highlight', 0, PARAM_INT);
$sort = optional_param('sort', offer::SORT_MANUAL, PARAM_ALPHA);
$filtercat = optional_param('cat', 0, PARAM_INT);
$filtertype = optional_param('type', '', PARAM_ALPHA);

if (!in_array($sort, offer::sort_options(), true)) {
    $sort = offer::SORT_MANUAL;
}

require_login();

$company = company::get_record(['shortname' => $shortname]);
if (!$company || $company->get('status') !== company::STATUS_ACTIVE) {
    throw new moodle_exception('invalidrecord', 'error');
}

$url = new moodle_url('/local/marketplace/offers.php', ['company' => $shortname]);
$PAGE->set_context($company->get_context());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($company->get_page_title());
$PAGE->set_heading($company->get_page_title());

// CSS proprio do vendedor, servido como arquivo. Entra depois do tema, entao
// consegue sobrescrever - que e o ponto de permitir CSS.
$csurl = $company->get_page_css_url();
if ($csurl) {
    $PAGE->requires->css($csurl);
}

echo $OUTPUT->header();

// A cor de destaque vira uma variavel CSS, nao regra solta. Assim o CSS do
// vendedor pode usa-la, e o valor passou pela validacao de hexadecimal - o
// unico campo do cadastro que chega dentro de uma declaracao.
$accent = (string) $company->get('pageaccent');
if ($accent !== '') {
    echo html_writer::tag(
        'style',
        '.local-marketplace-offers{--mp-accent:' . s($accent) . '}'
        . '.local-marketplace-offers .btn-primary{background:' . s($accent) . ';border-color:' . s($accent) . '}'
    );
}

$logo = $company->get_page_logo_url();
if ($logo) {
    echo html_writer::div(
        html_writer::empty_tag('img', [
            'src' => $logo->out(false),
            'alt' => format_string($company->get('name')),
            'class' => 'local-marketplace-logo',
            'style' => 'max-height:96px;width:auto',
            // Logo e decorativo em relacao ao conteudo: carregar cedo evita o
            // salto de layout que empurraria as ofertas para baixo.
            'loading' => 'eager',
        ]),
        'mb-3'
    );
}

$intro = (string) $company->get('pageintro');
if (trim($intro) !== '') {
    // O format_text filtra o HTML: o vendedor escreve texto de venda, nao script.
    echo html_writer::div(
        format_text($intro, FORMAT_HTML, ['context' => $company->get_context()]),
        'local-marketplace-intro mb-4'
    );
}

$offers = offer::get_published((int) $company->get('id'), $sort);

// Subcategorias que de fato tem oferta publicada. Listar toda subcategoria da
// empresa mostraria filtro que nao filtra nada - a que existe mas ainda nao tem
// curso a venda.
$subcats = [];
foreach ($offers as $o) {
    foreach ($o->get_category_ids() as $catid) {
        if ($catid !== (int) $company->get('categoryid')) {
            $subcats[$catid] = true;
        }
    }
}
$subcats = array_keys($subcats);

// Aplica os filtros DEPOIS de levantar as subcategorias, senao a lista de
// filtros encolheria a cada filtro aplicado e o aluno ficaria sem como voltar.
if ($filtercat > 0) {
    $offers = array_filter($offers, fn($o) => in_array($filtercat, $o->get_category_ids(), true));
}
if ($filtertype !== '') {
    $offers = array_filter($offers, fn($o) => $o->get('offertype') === $filtertype);
}

if (!$offers) {
    echo $OUTPUT->notification(get_string('nooffers', 'local_marketplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// A empresa sem meio de pagamento configurado so pode oferecer curso gratuito.
// Avisar aqui evita o aluno clicar em comprar e receber erro do gateway.
$cansell = $company->can_sell();
if (!$cansell) {
    echo $OUTPUT->notification(get_string('nopaymentaccount', 'local_marketplace'), 'warning');
}

// Controles so aparecem quando ha o que ordenar ou filtrar. Uma empresa com
// duas ofertas nao precisa de barra de filtro ocupando o topo da vitrine.
$total = count(offer::get_published((int) $company->get('id')));
if ($total > 2 || $subcats) {
    $baseurl = new moodle_url('/local/marketplace/offers.php', ['company' => $shortname]);

    echo html_writer::start_div('local-marketplace-controls d-flex flex-wrap gap-3 align-items-end mb-4');

    // Ordenacao.
    $sortlabels = [];
    foreach (offer::sort_options() as $opt) {
        $sortlabels[$opt] = get_string('sort' . $opt, 'local_marketplace');
    }
    echo html_writer::div(
        html_writer::tag('label', get_string('sortby', 'local_marketplace'), [
            'for' => 'mp-sort',
            'class' => 'form-label small text-muted mb-1',
        ]) .
        $OUTPUT->single_select(
            new moodle_url($baseurl, array_filter(['cat' => $filtercat, 'type' => $filtertype])),
            'sort',
            $sortlabels,
            $sort,
            null,
            'mp-sort'
        )
    );

    // Subcategorias.
    if ($subcats) {
        $catlabels = [0 => get_string('filterallcategories', 'local_marketplace')];
        foreach ($subcats as $catid) {
            $cat = core_course_category::get($catid, IGNORE_MISSING, true);
            if ($cat) {
                $catlabels[$catid] = $cat->get_formatted_name();
            }
        }
        echo html_writer::div(
            html_writer::tag('label', get_string('filtercategory', 'local_marketplace'), [
                'for' => 'mp-cat',
                'class' => 'form-label small text-muted mb-1',
            ]) .
            $OUTPUT->single_select(
                new moodle_url($baseurl, array_filter(['sort' => $sort, 'type' => $filtertype])),
                'cat',
                $catlabels,
                $filtercat,
                null,
                'mp-cat'
            )
        );
    }

    // Tipo de oferta.
    $typelabels = [
        '' => get_string('filteralltypes', 'local_marketplace'),
        offer::TYPE_SINGLE => get_string('typesingle', 'local_marketplace'),
        offer::TYPE_BUNDLE => get_string('typebundle', 'local_marketplace'),
        offer::TYPE_CATALOG => get_string('typecatalog', 'local_marketplace'),
    ];
    echo html_writer::div(
        html_writer::tag('label', get_string('filtertype', 'local_marketplace'), [
            'for' => 'mp-type',
            'class' => 'form-label small text-muted mb-1',
        ]) .
        $OUTPUT->single_select(
            new moodle_url($baseurl, array_filter(['sort' => $sort, 'cat' => $filtercat])),
            'type',
            $typelabels,
            $filtertype,
            null,
            'mp-type'
        )
    );

    echo html_writer::end_div();

    // Sem resultado apos filtrar e diferente de empresa sem oferta: aqui o
    // aluno precisa saber que basta limpar o filtro.
    if (!$offers) {
        echo $OUTPUT->notification(get_string('nooffersfiltered', 'local_marketplace'), 'info');
        echo html_writer::link($baseurl, get_string('filterclear', 'local_marketplace'), [
            'class' => 'btn btn-secondary',
        ]);
        echo $OUTPUT->footer();
        exit;
    }
}

echo html_writer::start_div('local-marketplace-offers');

foreach ($offers as $offer) {
    $offerid = (int) $offer->get('id');
    $free = $offer->is_free();

    // Quem ja tem direito vigente nao ve botao de comprar: veria um preco por
    // algo que ja pode acessar.
    $owned = false;
    $held = null;
    foreach (entitlement::get_active_for_user((int) $USER->id, (int) $company->get('id')) as $ent) {
        if ((int) $ent->get('offerid') === $offerid) {
            $owned = true;
            $held = $ent;
            break;
        }
    }

    // Renovar e a excecao a regra acima. Sem debito automatico, o unico jeito
    // de continuar assinando e comprar de novo - e esconder o botao de quem
    // esta prestes a vencer garantiria a perda de acesso.
    $cancelled = $held && (int) $held->get('norenew') === 1;
    $canrenew = false;
    if ($held && !$cancelled && $offer->get('accessmode') === offer::ACCESS_RECURRING) {
        $end = (int) $held->get('timeend');
        $canrenew = $end > 0
            && ($end - time()) < (\local_marketplace\task\notify_expiring::NOTICE_DAYS * DAYSECS)
            && $offer->accepts_cycle((int) $held->get('cycles'));
    }

    $wanted = ($highlight === $offerid);

    echo html_writer::start_div('card mb-3' . ($wanted ? ' border-primary' : ''), $wanted ? ['id' => 'offer-wanted'] : []);
    echo html_writer::start_div('card-body');

    if ($wanted) {
        echo $OUTPUT->notification(get_string('offerunlocks', 'local_marketplace'), 'info');
    }

    echo html_writer::tag('h3', format_string($offer->get('name')), ['class' => 'card-title h5']);

    if ($offer->get('description')) {
        echo html_writer::div(format_text($offer->get('description')), 'card-text');
    }

    $courses = $offer->get_course_ids();
    echo html_writer::div(
        get_string('offerincludes', 'local_marketplace', count($courses)) . ' · ' .
        $offer->describe_billing(),
        'text-muted small mb-2'
    );

    // Cards com imagem, titulo e descricao do proprio curso. Com planos em
    // niveis - basico, intermediario, completo - a diferenca entre eles E a
    // lista: "inclui 4 cursos" nao deixa o aluno escolher.
    //
    // Os dados vem do cadastro do curso, nao de campos duplicados na oferta.
    // Duplicar faria o vendedor manter a mesma informacao em dois lugares, e
    // os dois divergiriam na primeira edicao feita so num deles.
    if ($courses && $offer->get('offertype') !== offer::TYPE_CATALOG) {
        echo html_writer::start_div('row g-3 mb-3');
        foreach ($courses as $courseid) {
            $course = get_course((int) $courseid, false);
            if (!$course) {
                continue;
            }

            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            if (!$image) {
                // Sem imagem cadastrada o core gera um padrao a partir do id,
                // o mesmo que aparece na lista de cursos. Card sem imagem
                // nenhuma quebraria o alinhamento da grade.
                $image = $OUTPUT->get_generated_image_for_id((int) $course->id);
            }

            $coursecontext = context_course::instance((int) $course->id);
            $summary = format_text(
                (string) $course->summary,
                (int) $course->summaryformat,
                ['context' => $coursecontext]
            );

            echo html_writer::start_div('col-12 col-md-6 col-lg-4');
            echo html_writer::start_div('card h-100');
            echo html_writer::empty_tag('img', [
                'src' => $image,
                'alt' => '',
                'class' => 'card-img-top',
                'style' => 'aspect-ratio:16/9;object-fit:cover',
                'loading' => 'lazy',
            ]);
            echo html_writer::start_div('card-body p-3');
            echo html_writer::tag('h4', format_string($course->fullname), ['class' => 'card-title h6 mb-2']);
            if (trim(strip_tags($summary)) !== '') {
                echo html_writer::div(
                    shorten_text(strip_tags($summary), 160),
                    'card-text small text-muted mb-0'
                );
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    if ($owned && $canrenew) {
        echo $OUTPUT->notification(
            get_string(
                'renewnotice',
                'local_marketplace',
                userdate((int) $held->get('timeend'), get_string('strftimedaydate'))
            ),
            'warning'
        );
        echo html_writer::div(
            helper::get_cost_as_string((float) $offer->get('price'), $offer->get('currency')),
            'h4 mb-2'
        );
        $attributes = helper::gateways_modal_link_params(
            'local_marketplace',
            'offer',
            $offerid,
            format_string($offer->get('name'))
        );
        $attributes['id'] = 'pay-offer-' . $offerid;
        $attributes['class'] = 'btn btn-primary';
        echo html_writer::tag('button', get_string('renewnow', 'local_marketplace'), $attributes);
    } else if ($owned) {
        $until = $held ? (int) $held->get('timeend') : 0;

        if ($cancelled) {
            // Cancelada, mas o acesso continua ate a data paga. Dizer as duas
            // coisas juntas evita o aluno achar que perdeu o que ja pagou.
            echo $OUTPUT->notification(
                $until > 0
                    ? get_string(
                        'cancelledbut',
                        'local_marketplace',
                        userdate($until, get_string('strftimedaydate'))
                    )
                    : get_string('cancelledlifetime', 'local_marketplace'),
                'info'
            );
            echo html_writer::link(
                new moodle_url(
                    '/local/marketplace/cancel.php',
                    ['id' => $held->get('id'), 'undo' => 1, 'sesskey' => sesskey()]
                ),
                get_string('cancelundo', 'local_marketplace'),
                ['class' => 'btn btn-sm btn-secondary']
            );
        } else {
            echo $OUTPUT->notification(get_string('alreadyowned', 'local_marketplace'), 'success');

            // Assinatura vigente mostra ate quando vale. Sem isso o aluno so
            // descobre a data quando o acesso acaba.
            if ($until > 0) {
                echo html_writer::div(
                    get_string(
                        'accessuntil',
                        'local_marketplace',
                        userdate($until, get_string('strftimedaydate'))
                    ),
                    'text-muted small mb-2'
                );
            }

            // So faz sentido cancelar o que se renova. Numa compra avulsa ou
            // vitalicia nao ha renovacao a interromper.
            if ($held && $offer->get('accessmode') === offer::ACCESS_RECURRING) {
                echo html_writer::link(
                    new moodle_url('/local/marketplace/cancel.php', ['id' => $held->get('id')]),
                    get_string('cancelsubscription', 'local_marketplace'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
            }
        }
    } else if ($free) {
        // Oferta gratuita nao passa pelo gateway: nao ha o que cobrar.
        echo html_writer::link(
            new moodle_url('/local/marketplace/claim.php', ['offerid' => $offerid, 'sesskey' => sesskey()]),
            get_string('getfree', 'local_marketplace'),
            ['class' => 'btn btn-primary']
        );
    } else if ($cansell) {
        echo html_writer::div(
            helper::get_cost_as_string((float) $offer->get('price'), $offer->get('currency')),
            'h4 mb-2'
        );
        $attributes = helper::gateways_modal_link_params(
            'local_marketplace',
            'offer',
            $offerid,
            format_string($offer->get('name'))
        );
        // O id precisa ser unico por botao: o helper devolve sempre o mesmo,
        // e a pagina lista varias ofertas.
        $attributes['id'] = 'pay-offer-' . $offerid;
        $attributes['class'] = 'btn btn-primary';
        echo html_writer::tag('button', get_string('buynow', 'local_marketplace'), $attributes);
    } else {
        echo html_writer::tag('span', get_string('unavailable', 'local_marketplace'), ['class' => 'text-muted']);
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

$PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');

echo $OUTPUT->footer();
