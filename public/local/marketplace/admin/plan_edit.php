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
 * Criar e editar plano comercial.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_marketplace\country;
use local_marketplace\form\plan_form;
use local_marketplace\plan;
use local_marketplace\plan_tier;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_marketplace_plans');

$listurl = new moodle_url('/local/marketplace/admin/plans.php');
$pageurl = new moodle_url('/local/marketplace/admin/plan_edit.php', $id ? ['id' => $id] : []);
$PAGE->set_url($pageurl);

$plan = null;
$customdata = ['planid' => 0];

if ($id) {
    $plan = plan::get_record(['id' => $id]);

    if (!$plan) {
        throw new moodle_exception('errorplannotfound', 'local_marketplace');
    }

    $customdata = [
        'planid' => (int) $plan->get('id'),
        'shortname' => $plan->get('shortname'),
    ];
}

$form = new plan_form($pageurl, $customdata);

if ($plan) {
    $data = (object) [
        'id' => (int) $plan->get('id'),
        'shortname' => $plan->get('shortname'),
        'name' => $plan->get('name'),
        'description' => $plan->get('description'),
        'monthlyfee' => $plan->get('monthlyfee'),
        'commissionpct' => $plan->get('commissionpct'),
        // O nulo vira string vazia, que e a opcao "herdar a base do site" no
        // seletor. Sem esta linha o campo era gravado e nunca lido de volta: a
        // tela mostrava "herdar" para todo plano, inclusive os que tinham base
        // propria.
        'commissionbase' => $plan->get('commissionbase') ?? '',
        'country' => $plan->get('country'),
        'hostingmodel' => $plan->get('hostingmodel'),
        'ispublic' => $plan->get('ispublic'),
        'status' => $plan->get('status'),
        'sortorder' => $plan->get('sortorder'),
    ];

    // As faixas entram nos slots na ordem em que estao gravadas. Slot vazio
    // aparece em branco, e em branco significa "sem teto" na hora de salvar.
    $i = 0;
    foreach ($plan->get_tiers() as $tier) {
        if ($i >= plan_form::TIER_SLOTS) {
            break;
        }
        $max = $tier->get('maxprice');
        $data->tiermaxprice[$i] = $max === null ? '' : format_float((float) $max, 2, false);
        $data->tiermaxresolution[$i] = $tier->get('maxresolution');
        $i++;
    }

    $form->set_data($data);
}

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($submitted = $form->get_data()) {
    $record = (object) [
        'name' => $submitted->name,
        'description' => $submitted->description ?? null,
        'monthlyfee' => (float) $submitted->monthlyfee,
        'commissionpct' => (float) $submitted->commissionpct,
        'country' => $submitted->country,
        // A moeda e derivada do pais, e nao pedida ao usuario: sao os mesmos
        // dois campos dizendo a mesma coisa, e um deles ficaria errado.
        'currency' => country::currency_for($submitted->country),
        'commissionbase' => \local_marketplace\api::commission_base_input($submitted->commissionbase ?? null),
        'hostingmodel' => $submitted->hostingmodel,
        'ispublic' => (int) $submitted->ispublic,
        'status' => $submitted->status,
        'sortorder' => (int) $submitted->sortorder,
    ];

    if ($plan) {
        foreach ((array) $record as $field => $value) {
            $plan->set($field, $value);
        }
        $plan->update();
    } else {
        $record->shortname = $submitted->shortname;
        $plan = new plan(0, $record);
        $plan->create();
    }

    // As faixas sao reescritas inteiras a cada salvamento. Casar linha a linha
    // com o que veio do formulario exigiria um id por slot e daria a mesma
    // resposta: sao no maximo quatro linhas, e nada aponta para elas.
    foreach ($plan->get_tiers() as $tier) {
        $tier->delete();
    }

    // Uma regra so, para nao virar adivinhacao: slot com preco e uma faixa
    // com teto; o PRIMEIRO slot vazio depois de uma faixa preenchida e a faixa
    // final, a sem teto. Slot vazio antes de qualquer preenchido e slot que
    // ninguem usou - sem esta distincao, os quatro slots em branco do
    // formulario virariam quatro faixas sem limite.
    $resolutions = $submitted->tiermaxresolution ?? [];
    $prices = $submitted->tiermaxprice ?? [];
    $ordem = 10;
    $preenchida = false;

    foreach ($resolutions as $i => $resolution) {
        $raw = trim((string) ($prices[$i] ?? ''));

        if ($raw === '') {
            if (!$preenchida) {
                continue;
            }

            (new plan_tier(0, (object) [
                'planid' => (int) $plan->get('id'),
                'maxprice' => null,
                'maxresolution' => $resolution,
                'sortorder' => $ordem,
            ]))->create();

            break;
        }

        (new plan_tier(0, (object) [
            'planid' => (int) $plan->get('id'),
            'maxprice' => (float) $raw,
            'maxresolution' => $resolution,
            'sortorder' => $ordem,
        ]))->create();

        $preenchida = true;
        $ordem += 10;
    }

    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'editplan' : 'addplan', 'local_marketplace'));
$form->display();
echo $OUTPUT->footer();
