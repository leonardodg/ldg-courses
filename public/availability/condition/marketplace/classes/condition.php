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

namespace availability_marketplace;

use local_marketplace\entitlement;
use local_marketplace\offer;

/**
 * Libera secao ou atividade conforme o direito de acesso do aluno.
 *
 * E o que permite o curso ter topico gratis e topico pago: o topico livre nao
 * recebe condicao, os pagos recebem esta. O aluno enxerga o curso, entende o
 * que vai receber, e so o conteudo pago fica fechado - que converte melhor do
 * que esconder o curso inteiro atras de um paywall.
 *
 * Duas formas de uso:
 *   offerid = 0  -> qualquer direito vigente que inclua este curso
 *   offerid > 0  -> uma oferta especifica, para conteudo que so vem no pacote
 *                   mais caro (ex.: a mentoria do "Completo com mentoria")
 *
 * @package    availability_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int Oferta exigida; 0 = qualquer uma que inclua o curso. */
    protected $offerid = 0;

    /**
     * Constroi a condicao a partir do JSON gravado.
     *
     * @param \stdClass $structure
     */
    public function __construct($structure) {
        if (isset($structure->offerid) && is_number($structure->offerid)) {
            $this->offerid = (int) $structure->offerid;
        }
    }

    /**
     * Serializa para gravar.
     *
     * @return \stdClass
     */
    public function save() {
        return (object) [
            'type' => 'marketplace',
            'offerid' => $this->offerid,
        ];
    }

    /**
     * Monta o JSON da condicao, para uso por outro codigo.
     *
     * @param int $offerid 0 = qualquer oferta.
     * @return \stdClass
     */
    public static function get_json(int $offerid = 0) {
        return (object) ['type' => 'marketplace', 'offerid' => $offerid];
    }

    /**
     * O aluno tem direito ao conteudo?
     *
     * @param bool $not Condicao negada.
     * @param \core_availability\info $info
     * @param bool $grabthelot
     * @param int $userid
     * @return bool
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $courseid = (int) $info->get_course()->id;

        if ($this->offerid > 0) {
            $allow = false;
            foreach (entitlement::get_active_for_user($userid) as $ent) {
                if ((int) $ent->get('offerid') === $this->offerid) {
                    $allow = true;
                    break;
                }
            }
        } else {
            $allow = entitlement::user_has_course_access($userid, $courseid);
        }

        return $not ? !$allow : $allow;
    }

    /**
     * Texto exibido ao aluno quando o conteudo esta bloqueado.
     *
     * @param bool $full
     * @param bool $not
     * @param \core_availability\info $info
     * @return string
     */
    public function get_description($full, $not, \core_availability\info $info) {
        if ($this->offerid > 0) {
            $offer = offer::get_record(['id' => $this->offerid]);
            $name = $offer ? format_string($offer->get('name')) : get_string('unknownoffer', 'availability_marketplace');
            $key = $not ? 'requires_notoffer' : 'requires_offer';
            $text = get_string($key, 'availability_marketplace', $name);

            // Bloquear sem oferecer o caminho perde a venda no momento exato
            // do interesse: o aluno esta olhando o conteudo que quer.
            //
            // O LINK NAO PODE DEPENDER DO $not, e isso ja custou o recurso
            // inteiro. O $not do Moodle significa CONDICAO NEGADA - "nao deve
            // ter comprado" -, e nao "esta bloqueado". Numa restricao normal
            // ele e sempre false, entao a versao anterior mostrava o botao
            // EXATAMENTE no unico caso em que o aluno nao deveria comprar, e
            // em nenhum outro. O defeito nao aparecia em teste nenhum porque
            // ninguem olhava o texto.
            //
            // Quem decide sao duas perguntas sobre QUEM esta lendo. Ja tem o
            // direito? Entao nao ha o que comprar. Pode editar o curso? Entao
            // e o professor montando a restricao, e um "compre agora" ali e
            // ruido.
            if (!$not && $offer && self::viewer_should_buy($info)) {
                $text .= ' ' . self::buy_link($offer);
            }
            return $text;
        }
        $key = $not ? 'requires_noaccess' : 'requires_access';
        return get_string($key, 'availability_marketplace');
    }

    /**
     * Quem esta lendo esta descricao e um comprador em potencial?
     *
     * Duas negativas, e as duas sobre a PESSOA que le - nao sobre a condicao:
     * quem ja comprou nao tem o que comprar, e quem edita o curso nao e o
     * comprador.
     *
     * @param \core_availability\info $info
     * @return bool
     */
    protected function viewer_should_buy(\core_availability\info $info): bool {
        global $USER;

        if (empty($USER->id) || isguestuser()) {
            return false;
        }

        if (has_capability('moodle/course:manageactivities', $info->get_context())) {
            return false;
        }

        // A mesma pergunta que bloqueia o conteudo, feita para quem le.
        return !$this->is_available(false, $info, false, (int) $USER->id);
    }

    /**
     * Link para a vitrine da empresa dona da oferta.
     *
     * Leva a vitrine e nao ao checkout: comprar exige o modal de gateways do
     * core_payment, que precisa da pagina para funcionar. Um link direto ao
     * pagamento pularia a descricao do que esta sendo comprado.
     *
     * @param offer $offer
     * @return string
     */
    protected static function buy_link(offer $offer): string {
        $company = \local_marketplace\company::get_record(['id' => $offer->get('companyid')]);
        if (!$company) {
            return '';
        }

        $url = new \moodle_url('/local/marketplace/offers.php', [
            'company' => $company->get('shortname'),
            'highlight' => $offer->get('id'),
        ]);

        return \html_writer::link($url, get_string('buyaccess', 'availability_marketplace'), [
            'class' => 'btn btn-primary btn-sm ms-2',
        ]);
    }

    /**
     * Representacao para depuracao.
     *
     * @return string
     */
    protected function get_debug_string() {
        return $this->offerid > 0 ? 'offer#' . $this->offerid : 'any';
    }
}
