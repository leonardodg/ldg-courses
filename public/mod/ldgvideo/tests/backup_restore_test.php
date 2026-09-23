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

namespace mod_ldgvideo;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * O video sobrevive a duplicacao da atividade.
 *
 * @package    mod_ldgvideo
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_ldgvideo\url::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Duplicar a atividade preserva o endereco e a proporcao.
     *
     * SEM ISTO O BACKUP PASSA E NAO SERVE. Os dois campos so viajam porque
     * estao nomeados no backup_ldgvideo_stepslib; esquecer um deles produz uma
     * copia que abre sem video, e nada no processo reclama.
     *
     * A duplicacao usa o mesmo caminho do backup e do restore, e roda em
     * segundos - um backup e restore de curso inteiro provaria o mesmo e
     * custaria muito mais tempo de suite.
     *
     * @return void
     */
    public function test_duplicar_preserva_o_video(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $original = $this->getDataGenerator()->create_module('ldgvideo', [
            'course' => $course->id,
            'name' => 'Aula vertical',
            'videourl' => 'https://vimeo.com/226053498',
            'aspectratio' => url::RATIO_PORTRAIT,
        ]);

        // Usa cmactions::duplicate, e nao duplicate_module(): a funcao solta esta
        // depreciada desde o 5.2 (MDL-86858), e um debugging() no meio da suite
        // faz o PHPUnit reprovar.
        $copy = (new \core_courseformat\local\cmactions($course))->duplicate($original->cmid);

        $record = $DB->get_record('ldgvideo', ['id' => $copy->instance], '*', MUST_EXIST);

        $this->assertSame('https://vimeo.com/226053498', $record->videourl);
        $this->assertSame(url::RATIO_PORTRAIT, $record->aspectratio);
        $this->assertNotEquals($original->id, $record->id);
    }
}
