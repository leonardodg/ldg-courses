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

use PHPUnit\Framework\Attributes\CoversClass;
use core\check\result;

/**
 * O check que acusa a landing inalcancavel.
 *
 * O ESTADO QUE ELE ACUSA NAO TEM COMO SER PEGO POR OUTRO TESTE. O cenario
 * behat que mede a home LIGA o enablemyhome no proprio setup, entao prova que o
 * codigo funciona dado o flag certo e nunca flagra o flag errado - que foi
 * exatamente o que aconteceu em producao. O que este teste protege e o check em
 * si: se ele parar de acusar, volta-se a nao ter aviso nenhum.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_partners\check\landingreachable::class)]
final class landingreachable_test extends \advanced_testcase {
    /**
     * Com a landing na home e o Painel desligado, o check ACUSA.
     *
     * @return void
     */
    public function test_painel_desligado_com_a_landing_na_home_e_erro(): void {
        global $CFG;

        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');
        set_config('frontpagemode', 'landing', 'local_partners');
        $CFG->enablemyhome = 0;

        $this->assertSame(result::ERROR, (new landingreachable())->get_result()->get_status());
    }

    /**
     * Com o Painel ligado, esta tudo certo.
     *
     * @return void
     */
    public function test_painel_ligado_e_ok(): void {
        global $CFG;

        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');
        set_config('frontpagemode', 'landing', 'local_partners');
        $CFG->enablemyhome = 1;

        $this->assertSame(result::OK, (new landingreachable())->get_result()->get_status());
    }

    /**
     * Sem a landing na home nao ha o que checar.
     *
     * Quem responde pela home nesse caso e o core, e um aviso nosso ali seria
     * ruido: o administrador escolheu a home do Moodle de proposito.
     *
     * @return void
     */
    public function test_sem_a_landing_na_home_o_check_nao_se_aplica(): void {
        global $CFG;

        $this->resetAfterTest();

        set_config('enablelanding', 1, 'local_partners');
        set_config('frontpagemode', 'default', 'local_partners');
        $CFG->enablemyhome = 0;

        $this->assertSame(result::NA, (new landingreachable())->get_result()->get_status());
    }
}
