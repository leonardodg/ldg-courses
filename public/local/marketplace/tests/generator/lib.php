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

use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;

/**
 * Gerador de dados do marketplace, para PHPUnit e Behat.
 *
 * Nasceu de uma limitacao concreta: nao existe tela para criar oferta nem para
 * conceder direito de acesso - direito nasce de compra real, com gateway. Sem
 * este gerador, cenario de Behat para enrol, availability e block simplesmente
 * NAO ERA ESCRIVEL, porque nao havia como montar o estado.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_marketplace_generator extends component_generator_base {
    /** @var int Sequencial para nomes unicos. */
    protected $contador = 0;

    /**
     * Cria uma empresa, com a categoria e o papel de vendedor que ela implica.
     *
     * @param array|stdClass $record
     * @return company
     */
    public function create_company($record = null): company {
        global $DB;

        $record = (object) (array) $record;
        $this->contador++;

        $record->name = $record->name ?? 'Empresa ' . $this->contador;
        $record->shortname = $record->shortname ?? 'empresa' . $this->contador;

        // O dono e obrigatorio: create_company atribui o papel de vendedor a
        // ele no contexto da categoria. Sem um usuario de verdade a atribuicao
        // falharia, e o cenario morreria com erro de papel - longe da causa.
        $ownerid = (int) ($record->ownerid ?? 0);
        if (!$ownerid && !empty($record->owner)) {
            $ownerid = (int) $DB->get_field('user', 'id', ['username' => $record->owner], MUST_EXIST);
        }
        if (!$ownerid) {
            $ownerid = (int) $DB->get_field('user', 'id', ['username' => 'admin'], MUST_EXIST);
        }

        return api::create_company($record, $ownerid);
    }

    /**
     * Cria uma oferta de uma empresa.
     *
     * Aceita `courses` com nomes curtos separados por virgula: a oferta so tem
     * sentido junto dos cursos que ela libera, e exigir um segundo passo para
     * isso deixaria o cenario mais longo sem ficar mais claro.
     *
     * @param array|stdClass $record
     * @return offer
     */
    public function create_offer($record = null): offer {
        global $DB;

        $record = (object) (array) $record;
        $this->contador++;

        $companyid = (int) ($record->companyid ?? 0);
        if (!$companyid && !empty($record->company)) {
            $companyid = (int) $DB->get_field(
                company::TABLE,
                'id',
                ['shortname' => $record->company],
                MUST_EXIST
            );
        }
        if (!$companyid) {
            throw new coding_exception('oferta precisa de "company" (shortname) ou "companyid"');
        }

        $accessmode = $record->accessmode ?? offer::ACCESS_LIFETIME;

        $o = new offer();
        $o->set('companyid', $companyid);
        $o->set('name', $record->name ?? 'Oferta ' . $this->contador);
        $o->set('offertype', $record->offertype ?? offer::TYPE_SINGLE);
        $o->set('price', (float) ($record->price ?? 50.0));
        $o->set('currency', $record->currency ?? 'BRL');
        $o->set('accessmode', $accessmode);
        $o->set('accessdays', (int) ($record->accessdays ?? ($accessmode === offer::ACCESS_LIFETIME ? 0 : 30)));
        $o->set('status', $record->status ?? offer::STATUS_PUBLISHED);
        $o->create();

        if (!empty($record->courses)) {
            foreach (explode(',', $record->courses) as $shortname) {
                $shortname = trim($shortname);
                if ($shortname === '') {
                    continue;
                }
                $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
                $o->add_course($courseid);
            }
        }

        return $o;
    }

    /**
     * Concede um direito de acesso.
     *
     * `timeend` aceita o que o `strtotime` entende, alem do numero cru: um
     * cenario que precisa de "vence em tres dias" fica ilegivel com epoch, e
     * calcular a data no gherkin nao da.
     *
     * @param array|stdClass $record
     * @return entitlement
     */
    public function create_entitlement($record = null): entitlement {
        global $DB;

        $record = (object) (array) $record;

        $userid = (int) ($record->userid ?? 0);
        if (!$userid && !empty($record->user)) {
            $userid = (int) $DB->get_field('user', 'id', ['username' => $record->user], MUST_EXIST);
        }
        if (!$userid) {
            throw new coding_exception('direito precisa de "user" (username) ou "userid"');
        }

        $offerid = (int) ($record->offerid ?? 0);
        if (!$offerid && !empty($record->offer)) {
            $offerid = (int) $DB->get_field(offer::TABLE, 'id', ['name' => $record->offer], MUST_EXIST);
        }
        if (!$offerid) {
            throw new coding_exception('direito precisa de "offer" (nome) ou "offerid"');
        }

        $offer = new offer($offerid);

        $e = new entitlement();
        $e->set('userid', $userid);
        $e->set('offerid', $offerid);
        $e->set('companyid', (int) ($record->companyid ?? $offer->get('companyid')));
        $e->set('timestart', $this->when($record->timestart ?? null, time() - DAYSECS));
        $e->set('timeend', $this->when($record->timeend ?? null, 0));
        $e->set('status', $record->status ?? entitlement::STATUS_ACTIVE);
        $e->set('cycles', (int) ($record->cycles ?? 1));
        $e->set('norenew', (int) ($record->norenew ?? 0));
        $e->create();

        // Matricula em seguida, porque e o que a producao faz: quem concede o
        // direito chama o sync_user na sequencia - ver
        // service_provider::deliver_order(). A task agendada NAO cobre isto:
        // ela so ressincroniza quem acabou de VENCER, de proposito, para nao
        // varrer a base inteira a cada hora.
        //
        // Sem esta linha o gerador produzia um mundo que a producao nunca
        // produz - direito ativo e aluno de fora do curso - e o cenario falhava
        // por causa da fixture, nao do codigo. Passe 'sync' => 0 para montar
        // exatamente esse estado, quando ele for o alvo do teste.
        if (($record->sync ?? 1) && $plugin = enrol_get_plugin('marketplace')) {
            $plugin->sync_user($userid);
        }

        return $e;
    }

    /**
     * Converte data relativa em epoch.
     *
     * @param mixed $value Numero, texto para strtotime, ou null.
     * @param int $default
     * @return int
     */
    protected function when($value, int $default): int {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        $t = strtotime($value);
        if ($t === false) {
            throw new coding_exception('data que o strtotime nao entende: ' . $value);
        }

        return $t;
    }
}
