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

use block_marketplace\onboarding;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;
use local_marketplace\task\notify_expiring;

/**
 * As assinaturas do aluno.
 *
 * Existe porque sem debito automatico o aluno precisa AGIR para continuar
 * assinando, e nao ha onde ele veja o que tem e quando vence. O e-mail de aviso
 * chega uma vez; o bloco fica.
 *
 * Mostra so o que exige atencao ou decisao. O historico de pagamentos vai para
 * uma pagina propria: numa barra lateral, uma lista que cresce a cada mes
 * empurraria para baixo justamente o que precisa ser visto.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_marketplace extends block_base {
    /**
     * Titulo.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_marketplace');
    }

    /**
     * Onde pode ser adicionado.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'site-index' => true, 'course-view' => true];
    }

    /**
     * Conteudo.
     *
     * @return stdClass|null
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        if ($this->is_dashboard_context()) {
            $incomplete = $this->find_managed_incomplete_company((int) $USER->id);
            if ($incomplete) {
                return $this->content_for_owner($incomplete, (int) $USER->id);
            }
        }

        return $this->content_for_student((int) $USER->id);
    }

    /**
     * Primeira empresa que o usuario GERENCIA e que ainda esta incompleta.
     *
     * reset(company::get_by_member()) escolhia a primeira em ordem alfabetica
     * de nome, as cegas: um usuario que gerencia duas empresas via o checklist
     * da empresa ERRADA so porque o nome dela vem antes no alfabeto - e a
     * capability so era checada contra essa mesma escolha arbitraria, entao um
     * membro nao-gerente de uma empresa completa escondia o checklist da
     * empresa que ele de fato gerencia. Aqui percorre TODAS as empresas do
     * usuario e devolve a primeira em que ele tem a capability de gerenciar E
     * que ainda precisa de atencao.
     *
     * @param int $userid
     * @return company|null
     */
    private function find_managed_incomplete_company(int $userid): ?company {
        foreach (company::get_by_member($userid) as $company) {
            if (!has_capability('local/marketplace:managecompany', $company->get_context())) {
                continue;
            }
            if (!onboarding::progress($company)['complete']) {
                return $company;
            }
        }

        return null;
    }

    /**
     * Bloco no Dashboard ou na home do site - nao dentro de curso.
     *
     * O checklist de "meu cadastro de empresa" nao pode aparecer na pagina de
     * curso de QUALQUER empresa que o usuario visite: o bloco tambem entra em
     * 'course-view' (applicable_formats), e isto so afetava o widget de aluno
     * antes de existir uma visao de dono. Segue o mesmo padrao usado pelo
     * core em blocks/html/block_html.php.
     *
     * @return bool
     */
    private function is_dashboard_context(): bool {
        if (!$this->page) {
            return false;
        }

        return in_array($this->page->pagetype, ['my-index', 'site-index'], true);
    }

    /**
     * Checklist de ativacao, para quem e membro de uma empresa ainda
     * incompleta E tem a capability de gerencia-la. Empresa completa mostra
     * o widget de assinatura do proprio aluno em vez de nada - dono/vendedor
     * tambem pode ter assinatura vencendo em outra empresa.
     *
     * @param company $company
     * @param int $userid
     * @return stdClass
     */
    private function content_for_owner(company $company, int $userid): stdClass {
        $progress = onboarding::progress($company);
        if ($progress['complete']) {
            return $this->content_for_student($userid);
        }

        $labels = [
            onboarding::STEP_GATEWAY => get_string('stepgateway', 'block_marketplace'),
            onboarding::STEP_PLAN => get_string('stepplan', 'block_marketplace'),
        ];
        $states = onboarding::step_state($company);

        $items = [];
        foreach ($labels as $step => $label) {
            $done = $states[$step] === onboarding::STATE_DONE;
            $items[] = html_writer::div(
                ($done ? '&check; ' : '') . $label,
                $done ? 'small text-success' : 'small'
            );
        }

        $this->content->text = html_writer::div(
            get_string('onboardingprogress', 'block_marketplace', $progress['percent']),
            'fw-semibold mb-2'
        ) . implode('', $items);

        $this->content->footer = html_writer::link(
            new moodle_url('/local/marketplace/company.php', ['company' => $company->get('shortname')]),
            get_string('onboardingcontinue', 'block_marketplace')
        );

        return $this->content;
    }

    /**
     * Comportamento original: assinaturas ativas do aluno.
     *
     * @param int $userid
     * @return stdClass
     */
    private function content_for_student(int $userid): stdClass {
        $ents = entitlement::get_active_for_user($userid);
        if (!$ents) {
            return $this->content;
        }

        $now = time();
        $notice = notify_expiring::NOTICE_DAYS * DAYSECS;
        $items = [];

        foreach ($ents as $ent) {
            $offer = offer::get_record(['id' => (int) $ent->get('offerid')]);
            $companyrec = company::get_record(['id' => (int) $ent->get('companyid')]);
            if (!$offer || !$companyrec) {
                continue;
            }

            $end = (int) $ent->get('timeend');
            $cancelled = (int) $ent->get('norenew') === 1;
            $recurring = $offer->get('accessmode') === offer::ACCESS_RECURRING;

            // Vitalicia sem cancelamento nao pede nada do aluno e nao entra:
            // ocuparia espaco para dizer "esta tudo bem".
            if ($end === 0 && !$cancelled) {
                continue;
            }

            $parts = [];
            $parts[] = html_writer::tag('strong', format_string($offer->get('name')));
            $parts[] = html_writer::div(format_string($companyrec->get('name')), 'small text-muted');

            if ($end > 0) {
                $days = (int) ceil(($end - $now) / DAYSECS);
                $urgent = ($end - $now) < $notice;
                $parts[] = html_writer::div(
                    get_string(
                        $cancelled ? 'blockendson' : 'blockrenewson',
                        'block_marketplace',
                        userdate($end, get_string('strftimedaydate'))
                    ),
                    'small ' . ($urgent ? 'text-danger fw-semibold' : 'text-muted')
                );

                if ($urgent && !$cancelled && $recurring && $offer->accepts_cycle((int) $ent->get('cycles'))) {
                    $parts[] = html_writer::link(
                        new moodle_url('/local/marketplace/offers.php', [
                            'company' => $companyrec->get('shortname'),
                            'highlight' => $offer->get('id'),
                        ]),
                        get_string('blockpaynow', 'block_marketplace', $days),
                        ['class' => 'btn btn-sm btn-primary mt-1']
                    );
                }
            }

            if ($cancelled) {
                $parts[] = html_writer::div(
                    get_string('blockcancelled', 'block_marketplace'),
                    'small fst-italic'
                );
            }

            $items[] = html_writer::div(implode('', $parts), 'mb-3');
        }

        if (!$items) {
            return $this->content;
        }

        $this->content->text = implode('', $items);
        $this->content->footer = html_writer::link(
            new moodle_url('/local/marketplace/mysubscriptions.php'),
            get_string('blockhistory', 'block_marketplace')
        );

        return $this->content;
    }

    /**
     * Uma instancia por pagina basta.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }
}
