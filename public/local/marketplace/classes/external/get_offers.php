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

namespace local_marketplace\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;

/**
 * Ofertas publicadas de uma empresa, para pagina de venda externa.
 *
 * Existe como alternativa ao HTML livre na vitrine. Deixar o vendedor injetar
 * HTML nas NOSSAS paginas seria XSS entre inquilinos: o script do vendedor A
 * rodando no navegador do aluno da empresa B. Com esta funcao ele constroi a
 * pagina onde quiser, no dominio dele, e o risco fica com quem escreveu.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_offers extends external_api {
    /**
     * Parametros.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'company' => new external_value(PARAM_ALPHANUMEXT, 'Nome curto da empresa'),
            'sort' => new external_value(
                PARAM_ALPHA,
                'manual, newest, name, price ou pricedesc',
                VALUE_DEFAULT,
                offer::SORT_MANUAL
            ),
            'categoryid' => new external_value(
                PARAM_INT,
                'Filtrar por subcategoria. 0 nao filtra.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Devolve as ofertas publicadas.
     *
     * O campo "owned" so faz sentido para quem esta autenticado. Numa pagina
     * publica ele vem sempre falso, e a pagina externa mostra "comprar" para
     * todo mundo - o que esta certo: quem ja comprou descobre no checkout, e
     * a alternativa seria a API dizer o que o visitante possui sem ele ter se
     * identificado.
     *
     * @param string $companyname
     * @param string $sort
     * @param int $categoryid
     * @return array
     */
    public static function execute(string $companyname, string $sort = offer::SORT_MANUAL, int $categoryid = 0): array {
        global $USER, $OUTPUT;

        ['company' => $companyname, 'sort' => $sort, 'categoryid' => $categoryid] = self::validate_parameters(
            self::execute_parameters(),
            ['company' => $companyname, 'sort' => $sort, 'categoryid' => $categoryid]
        );

        // Valor invalido cai no padrao em vez de dar erro: a pagina externa e
        // do vendedor, e derrubar a vitrine dele por um parametro digitado
        // errado seria desproporcional.
        if (!in_array($sort, offer::sort_options(), true)) {
            $sort = offer::SORT_MANUAL;
        }

        $company = company::get_record(['shortname' => $companyname]);
        if (!$company || $company->get('status') !== company::STATUS_ACTIVE) {
            throw new \moodle_exception('invalidrecord', 'error');
        }

        // Contexto da categoria, e nao do sistema: e onde a empresa vive, e e
        // o que o format_text precisa para resolver arquivos embutidos.
        $context = $company->get_context();
        self::validate_context($context);

        // Direitos so quando ha alguem autenticado de verdade.
        $owned = [];
        if (isloggedin() && !isguestuser()) {
            foreach (entitlement::get_active_for_user((int) $USER->id, (int) $company->get('id')) as $ent) {
                $owned[(int) $ent->get('offerid')] = true;
            }
        }

        $result = [];
        foreach (offer::get_published((int) $company->get('id'), $sort) as $o) {
            if ($categoryid > 0 && !in_array($categoryid, $o->get_category_ids(), true)) {
                continue;
            }
            $offerid = (int) $o->get('id');
            $courses = [];
            foreach ($o->get_course_ids() as $courseid) {
                $course = get_course((int) $courseid, false);
                if (!$course) {
                    continue;
                }

                $image = \core_course\external\course_summary_exporter::get_course_image($course);
                if (!$image) {
                    $image = $OUTPUT->get_generated_image_for_id((int) $course->id);
                }

                $coursecontext = \context_course::instance((int) $course->id);
                $courses[] = [
                    'id' => (int) $courseid,
                    'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                    'summary' => format_text(
                        (string) $course->summary,
                        (int) $course->summaryformat,
                        ['context' => $coursecontext]
                    ),
                    'imageurl' => $image,
                ];
            }

            $result[] = [
                'id' => $offerid,
                'name' => format_string($o->get('name'), true, ['context' => $context]),
                'description' => format_text(
                    (string) $o->get('description'),
                    FORMAT_HTML,
                    ['context' => $context]
                ),
                'offertype' => $o->get('offertype'),
                'price' => (float) $o->get('price'),
                'currency' => $o->get('currency'),
                'free' => $o->is_free(),
                'accessmode' => $o->get('accessmode'),
                'accessdays' => (int) $o->get('accessdays'),
                'billingdays' => (int) $o->get('billingdays'),
                'maxcycles' => (int) $o->get('maxcycles'),
                'billingdescription' => $o->describe_billing(),
                // Onde mandar o comprador. A compra continua acontecendo na
                // plataforma: o checkout depende do modal do core_payment, e
                // replicar isso fora significaria mover credencial de pagamento
                // para uma pagina que nao controlamos.
                'buyurl' => (new \moodle_url('/local/marketplace/offers.php', [
                    'company' => $company->get('shortname'),
                    'highlight' => $offerid,
                ]))->out(false),
                'owned' => !empty($owned[$offerid]),
                'courses' => $courses,
            ];
        }

        return [
            'company' => [
                'shortname' => $company->get('shortname'),
                'name' => format_string($company->get('name'), true, ['context' => $context]),
                'pagetitle' => $company->get_page_title(),
                'pageintro' => format_text(
                    (string) $company->get('pageintro'),
                    FORMAT_HTML,
                    ['context' => $context]
                ),
                'pageaccent' => (string) $company->get('pageaccent'),
                'logourl' => ($url = $company->get_page_logo_url()) ? $url->out(false) : '',
                'cansell' => $company->can_sell(),
                'currency' => implode(',', $company->get_currencies()),
            ],
            'offers' => $result,
        ];
    }

    /**
     * Estrutura de retorno.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'company' => new external_single_structure([
                'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Nome curto'),
                'name' => new external_value(PARAM_TEXT, 'Nome da empresa'),
                'pagetitle' => new external_value(PARAM_TEXT, 'Titulo da vitrine'),
                'pageintro' => new external_value(PARAM_RAW, 'Texto de abertura, ja filtrado'),
                'pageaccent' => new external_value(PARAM_TEXT, 'Cor de destaque em hexadecimal'),
                'logourl' => new external_value(PARAM_RAW, 'URL do logo da marca, vazio se nao houver'),
                'cansell' => new external_value(PARAM_BOOL, 'Se a empresa pode vender curso pago'),
                'currency' => new external_value(PARAM_TEXT, 'Moedas em que a empresa recebe, separadas por virgula'),
            ]),
            'offers' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID da oferta'),
                    'name' => new external_value(PARAM_TEXT, 'Nome'),
                    'description' => new external_value(PARAM_RAW, 'Descricao, ja filtrada'),
                    'offertype' => new external_value(PARAM_ALPHA, 'single, bundle ou catalog'),
                    'price' => new external_value(PARAM_FLOAT, 'Preco'),
                    'currency' => new external_value(PARAM_TEXT, 'Moeda'),
                    'free' => new external_value(PARAM_BOOL, 'Se e gratuita'),
                    'accessmode' => new external_value(PARAM_ALPHA, 'lifetime, days ou recurring'),
                    'accessdays' => new external_value(PARAM_INT, 'Dias de acesso por pagamento'),
                    'billingdays' => new external_value(PARAM_INT, 'Intervalo de cobranca'),
                    'maxcycles' => new external_value(PARAM_INT, 'Maximo de cobrancas, 0 = sem limite'),
                    'billingdescription' => new external_value(PARAM_TEXT, 'Modelo de cobranca em texto'),
                    'buyurl' => new external_value(PARAM_URL, 'Para onde mandar o comprador'),
                    'owned' => new external_value(PARAM_BOOL, 'Se quem chamou ja tem acesso'),
                    'courses' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'ID do curso'),
                            'fullname' => new external_value(PARAM_TEXT, 'Nome do curso'),
                            'summary' => new external_value(PARAM_RAW, 'Descricao do curso, ja filtrada'),
                            'imageurl' => new external_value(PARAM_RAW, 'Imagem do curso, ou a gerada pelo core'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
