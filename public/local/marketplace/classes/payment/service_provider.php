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

namespace local_marketplace\payment;

use core_payment\local\entities\payable;
use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;
use moodle_exception;
use moodle_url;

/**
 * Liga o marketplace ao subsistema de pagamento do Moodle.
 *
 * O item vendido e a OFERTA, nao o curso: e a oferta que carrega preco, prazo
 * e quais cursos libera. Por isso o itemid destes callbacks e sempre um
 * offerid.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_provider implements \core_payment\local\callback\service_provider {
    /** @var string Venda de curso - o aluno paga a empresa, com split. */
    const PAYMENT_AREA = 'offer';

    /**
     * Assinatura SaaS - a empresa paga a PLATAFORMA, sem split nenhum.
     *
     * Desenhada em 17/09/2026 (docs/ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md).
     * O itemid aqui e sempre um companyid, nunca um offerid - e por isso
     * cada metodo abaixo comeca conferindo $paymentarea antes de decidir o
     * que o itemid significa.
     *
     * @var string
     */
    const PAYMENT_AREA_PLAN = 'plan';

    /**
     * Valor, moeda e conta que recebe.
     *
     * A conta e a da EMPRESA no PAIS da oferta, no contexto da categoria dela -
     * e o que faz o dinheiro cair na conta do vendedor, e nao numa conta
     * central da plataforma. A comissao sai como split, no gateway.
     *
     * O core nao passa o usuario aqui, de proposito: valor, moeda e conta sao
     * funcao pura do itemid. E por isso que o pais vive na oferta - nao ha como
     * uma oferta ser BRL para um aluno e ARS para outro, e um plano por pais e
     * uma oferta separada.
     *
     * @param string $paymentarea
     * @param int $itemid offerid, ou companyid quando $paymentarea = PAYMENT_AREA_PLAN
     * @return payable
     */
    public static function get_payable(string $paymentarea, int $itemid): payable {
        if ($paymentarea === self::PAYMENT_AREA_PLAN) {
            return self::get_payable_plan($itemid);
        }

        $offer = new offer($itemid);
        $company = new company($offer->get('companyid'));

        $account = $company->get_payment_account((string) $offer->get('country'));
        if (!$account) {
            throw new moodle_exception('errorcannotsell', 'local_marketplace');
        }

        return new payable(
            (float) $offer->get('price'),
            $offer->get('currency'),
            (int) $account->get('id')
        );
    }

    /**
     * Valor, moeda e conta da assinatura SaaS - o OPOSTO do metodo acima.
     *
     * Quem recebe e a conta da PROPRIA PLATAFORMA (api::get_or_create_platform_account()),
     * nao a da empresa: aqui a empresa e quem PAGA. Recusa explicitamente
     * empresa sem plano ou com mensalidade zero - Start-R$0 nao tem o que
     * cobrar, e cobrar mesmo assim so aconteceria por um itemid errado
     * chegando aqui.
     *
     * @param int $companyid
     * @return payable
     */
    protected static function get_payable_plan(int $companyid): payable {
        $company = new company($companyid);
        $plan = $company->get_plan();

        if (!$plan || (float) $plan->get('monthlyfee') <= 0) {
            throw new moodle_exception('errorplannotbillable', 'local_marketplace');
        }

        $account = api::get_or_create_platform_account((string) $plan->get('country'));

        return new payable(
            (float) $plan->get('monthlyfee'),
            $plan->get('currency'),
            (int) $account->get('id')
        );
    }

    /**
     * Para onde mandar o aluno depois de pagar.
     *
     * Oferta de um curso so vai direto para ele. Combo e assinatura liberam
     * varios, entao nao ha "o curso" para onde ir - vai para a lista de
     * cursos do aluno, onde os novos ja aparecem.
     *
     * @param string $paymentarea
     * @param int $itemid offerid, ou companyid quando $paymentarea = PAYMENT_AREA_PLAN
     * @return moodle_url
     */
    public static function get_success_url(string $paymentarea, int $itemid): moodle_url {
        if ($paymentarea === self::PAYMENT_AREA_PLAN) {
            $company = new company($itemid);

            return new moodle_url('/local/marketplace/company.php', ['company' => $company->get('shortname')]);
        }

        $offer = new offer($itemid);
        $courseids = $offer->get_course_ids();

        if ($offer->get('offertype') === offer::TYPE_SINGLE && count($courseids) === 1) {
            return new moodle_url('/course/view.php', ['id' => reset($courseids)]);
        }

        return new moodle_url('/my/courses.php');
    }

    /**
     * Entrega o que foi pago.
     *
     * Cria o direito de acesso e sincroniza a matricula. Nao matricula
     * diretamente: quem decide em quais cursos o aluno entra e o
     * enrol_marketplace, a partir dos direitos vigentes. Duplicar essa logica
     * aqui abriria espaco para os dois discordarem.
     *
     * Idempotente de proposito: o Mercado Pago reenvia notificacao de webhook,
     * e uma segunda entrega nao pode gerar um segundo direito. Se ja existe
     * direito vigente para esta oferta, ESTENDE em vez de criar - que e
     * exatamente o comportamento desejado na renovacao de assinatura.
     *
     * @param string $paymentarea
     * @param int $itemid offerid, ou companyid quando $paymentarea = PAYMENT_AREA_PLAN
     * @param int $paymentid
     * @param int $userid
     * @return bool
     */
    public static function deliver_order(string $paymentarea, int $itemid, int $paymentid, int $userid): bool {
        if ($paymentarea === self::PAYMENT_AREA_PLAN) {
            return self::deliver_order_plan($itemid);
        }

        $offer = new offer($itemid);

        // ATIVO OU VENCIDO, e nao so ativo.
        //
        // Quem deixa a mensalidade vencer tem o direito marcado como expired
        // pelo cron. Procurando so o ativo, pagar a atrasada criava um SEGUNDO
        // direito para a mesma oferta em vez de reviver o primeiro: o cycles
        // voltava a 1 - e com ele o maxcycles passava a nunca terminar -, a
        // tela listava a oferta duas vezes, e todo get_record que espera um so
        // quebrava. Visto acontecer em 09/09/2026, na prova do ciclo curto.
        //
        // CANCELLED fica de fora de proposito: revogar e decisao de negocio,
        // tomada em entitlement::revoke(), e um pagamento novo nao pode
        // desfaze-la em silencio. Nesse caso nasce direito novo, e a revogacao
        // continua no historico.
        //
        // O extend() ja sabe o resto: soma ao vencimento ATUAL quando ele esta
        // no futuro, e a partir de AGORA quando ja passou - quem ficou dois
        // dias sem pagar nao ganha os dois dias de volta.
        $existing = null;
        $candidatos = entitlement::get_records(['userid' => $userid, 'offerid' => $itemid], 'id', 'DESC');
        foreach ($candidatos as $ent) {
            $status = $ent->get('status');
            if ($status === entitlement::STATUS_ACTIVE || $status === entitlement::STATUS_EXPIRED) {
                $existing = $ent;
                break;
            }
        }

        if ($existing) {
            $seconds = $offer->get_access_duration();
            if ($seconds > 0) {
                $existing->extend($seconds);
            }
            // O ciclo e contado mesmo em oferta vitalicia, onde nao ha o que
            // estender: o numero serve ao relatorio e ao limite de cobrancas,
            // e nao a data de validade.
            $existing->set('cycles', (int) $existing->get('cycles') + 1);
            // Quem paga volta a ter acesso. O extend() ja reativa quando ha
            // prazo a somar; vitalicio nao passa por ele, e sem esta linha um
            // direito vitalicio marcado como vencido ficaria pago e inativo.
            $existing->set('status', entitlement::STATUS_ACTIVE);
            $existing->update();
        } else {
            $ent = new entitlement();
            $ent->set('userid', $userid);
            $ent->set('offerid', $itemid);
            $ent->set('companyid', (int) $offer->get('companyid'));
            $ent->set('timestart', time());
            $ent->set('timeend', $offer->calculate_expiry());
            $ent->set('cycles', 1);
            $ent->create();
        }

        $plugin = enrol_get_plugin('marketplace');
        if ($plugin) {
            $plugin->sync_user($userid);
        } else {
            // Sem o enrol o direito existe mas nao vira acesso. E uma falha de
            // configuracao, nao do pagamento - por isso registra e segue, em
            // vez de recusar a entrega de algo que o aluno ja pagou.
            debugging(
                'local_marketplace: enrol_marketplace desabilitado; direito criado sem matricula.',
                DEBUG_DEVELOPER
            );
        }

        return true;
    }

    /**
     * Entrega a assinatura SaaS - so estende o vencimento da mensalidade.
     *
     * NAO cria entitlement nem sale: os dois pressupoem uma oferta de curso
     * (offerid NOT NULL, e sale carrega campos de split entre empresa e
     * plataforma) - aqui nao ha curso nenhum, e a plataforma fica com 100%
     * do valor. O "direito" desta cobranca e so o proprio company.planexpiry.
     *
     * Mesmo intervalo de \local_marketplace\api::PLAN_CYCLE_DAYS que
     * api::recurrence_for('local_marketplace', $companyid, PAYMENT_AREA_PLAN)
     * declara para o gateway - os dois leem a mesma constante para nao
     * poderem divergir.
     *
     * @param int $companyid
     * @return bool
     */
    protected static function deliver_order_plan(int $companyid): bool {
        $company = new company($companyid);

        if (!$company->get_plan()) {
            return false;
        }

        $company->extend_plan(api::PLAN_CYCLE_DAYS * DAYSECS);

        return true;
    }
}
