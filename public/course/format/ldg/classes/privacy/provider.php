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
 * Privacidade do format_ldg.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * O formato nao guarda dado pessoal nenhum.
 *
 * Vale dizer o que ele NAO e, porque a tela do portal mostra bastante coisa
 * sobre o aluno - progresso, aula concluida, aula bloqueada. Nada disso e dado
 * do formato: sai do subsistema de conclusao e das condicoes de acesso, que tem
 * os proprios providers e respondem por esses dados no relatorio de privacidade.
 * O formato so desenha o que ja existe.
 *
 * A tabela de duracao por aula, prevista para o proximo passo, tambem nao muda
 * esta resposta: ela guarda o tempo do VIDEO, por cmid, e nao o tempo que
 * alguem assistiu. Se um dia passar a guardar progresso de reproducao por
 * usuario, este arquivo deixa de ser um null_provider - e essa e a fronteira
 * que decide.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Motivo pelo qual o plugin nao guarda dado pessoal.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
