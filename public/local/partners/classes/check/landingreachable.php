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

namespace local_partners\check;

use core\check\check;
use core\check\result;
use local_partners\landing;

/**
 * A landing na raiz e alcancavel por quem nao entrou?
 *
 * ESTE CHECK EXISTE PORQUE NENHUM TESTE PEGA ISTO. O phpunit nao ve
 * configuracao de site em producao, e o cenario behat que mede a home LIGA o
 * enablemyhome no proprio setup - ele prova que o codigo funciona dado o flag
 * certo, e nunca vai flagrar o flag errado.
 *
 * O estado que ele acusa custou horas: com o Painel desligado, o index.php do
 * core manda todo visitante anonimo para a tela de login ANTES de qualquer
 * codigo de tema rodar (public/index.php, ramo do enablemyhome). O comentario
 * do proprio core avisa que o forcelogin nao tem nada a ver com isso, e e
 * exatamente ali que se perde tempo procurando.
 *
 * A consequencia nao e so a landing sumir: canonica, hreflang e a entrada
 * principal do sitemap apontam todos para a raiz, entao o buscador segue cada
 * um deles ate um redirecionamento para uma pagina noindex. A descoberta por
 * buscador para inteira, e em silencio.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landingreachable extends check {
    /**
     * O nome do check na tela de status.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('checklandingreachable', 'local_partners');
    }

    /**
     * Leva direto a configuracao que resolve, e nao a uma busca.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/settings.php', ['section' => 'navigation']),
            get_string('checklandingreachableaction', 'local_partners')
        );
    }

    /**
     * O resultado.
     *
     * @return result
     */
    public function get_result(): result {
        global $CFG;

        // Sem a landing na raiz nao ha o que checar: a home e a do Moodle, e
        // quem responde por ela e o core.
        if (!landing::replaces_frontpage()) {
            return new result(
                result::NA,
                get_string('checklandingreachablena', 'local_partners'),
                ''
            );
        }

        if (empty($CFG->enablemyhome)) {
            return new result(
                result::ERROR,
                get_string('checklandingreachablefail', 'local_partners'),
                get_string('checklandingreachablefaildetail', 'local_partners')
            );
        }

        return new result(
            result::OK,
            get_string('checklandingreachableok', 'local_partners'),
            ''
        );
    }
}
