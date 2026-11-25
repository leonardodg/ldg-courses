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
 * Testes do catalogo do curso.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

/**
 * Testes do catalogo do curso.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_ldg\catalog::class)]
final class catalog_test extends \advanced_testcase {
    /**
     * Monta um curso com uma atividade de cada tipo que importa.
     *
     * @return array [curso, modinfo]
     */
    private function complete_course(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg', 'numsections' => 2]);

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $generator->create_module('quiz', ['course' => $course->id, 'section' => 1, 'name' => 'Prova']);
        $generator->create_module('resource', ['course' => $course->id, 'section' => 1, 'name' => 'Apostila']);
        $generator->create_module('folder', ['course' => $course->id, 'section' => 2, 'name' => 'Anexos']);
        $generator->create_module('url', ['course' => $course->id, 'section' => 2, 'name' => 'Link']);
        $generator->create_module('forum', ['course' => $course->id, 'section' => 2, 'name' => 'Duvidas']);
        $generator->create_module('label', ['course' => $course->id, 'section' => 1]);

        return [$course, get_fast_modinfo($course)];
    }

    /**
     * Cada tipo cai no balde certo.
     *
     * @return void
     */
    public function test_classifica_por_tipo(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $modinfo] = $this->complete_course();
        $byname = [];

        foreach ($modinfo->get_cms() as $cm) {
            $byname[$cm->name] = catalog::classify($cm);
        }

        $this->assertSame(catalog::AULA, $byname['Aula um']);
        $this->assertSame(catalog::AULA, $byname['Prova']);
        $this->assertSame(catalog::MATERIAL, $byname['Apostila']);
        $this->assertSame(catalog::MATERIAL, $byname['Anexos']);
        $this->assertSame(catalog::MATERIAL, $byname['Link']);
        $this->assertSame(catalog::FORUM, $byname['Duvidas']);
    }

    /**
     * Rotulo fica de fora, porque nao tem o que abrir.
     *
     * O sinal e a AUSENCIA DE URL, e nao o is_of_type_that_can_display(): aquele
     * e plugin_supports(FEATURE_CAN_DISPLAY, true) - com default TRUE -, e o
     * mod_label nao declara a flag. Pelo caminho obvio, rotulo viraria aula.
     *
     * @return void
     */
    public function test_rotulo_fica_de_fora(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $modinfo] = $this->complete_course();

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'label') {
                $this->assertSame(catalog::NENHUM, catalog::classify($cm));

                return;
            }
        }

        $this->fail('O rotulo nao foi criado.');
    }

    /**
     * O catalogo separa o curso inteiro.
     *
     * @return void
     */
    public function test_separa_o_curso(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->complete_course();
        $catalog = new catalog(course_get_format($course));

        $this->assertCount(2, $catalog->get(catalog::AULA));
        $this->assertCount(3, $catalog->get(catalog::MATERIAL));
        $this->assertCount(1, $catalog->get(catalog::FORUM));
        $this->assertTrue($catalog->has(catalog::MATERIAL));
        $this->assertFalse($catalog->has(catalog::CERTIFICADO));
    }

    /**
     * Curso vazio nao tem destino nenhum, e isso nao pode ser um erro.
     *
     * @return void
     */
    public function test_curso_vazio(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $catalog = new catalog(course_get_format($course));

        $this->assertSame([], $catalog->get(catalog::AULA));
        $this->assertFalse($catalog->has(catalog::AULA));
    }

    /**
     * Atividade escondida nao aparece para o aluno.
     *
     * @return void
     */
    public function test_atividade_escondida_fica_de_fora(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Visivel']);
        $generator->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Escondida',
            'visible' => 0,
        ]);

        $this->setUser($student);
        $catalog = new catalog(course_get_format($course));
        $lessons = $catalog->get(catalog::AULA);

        $this->assertCount(1, $lessons);
        $this->assertSame('Visivel', reset($lessons)->name);
    }

    /**
     * has() so olha o balde; has_visible() olha o usuario atual.
     *
     * Destino de item UNICO (forum, certificado) sem lista propria que mostre
     * cadeado por item: has() true com o unico item bloqueado deixaria a aba
     * aparecendo e o aluno cairia num embed de "acesso negado".
     *
     * @return void
     */
    public function test_has_visible_do_forum_bloqueado_e_falso(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Aula um']);
        $generator->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Duvidas',
            'availability' => json_encode((object) [
                'op' => '&',
                'c' => [(object) ['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]],
                'showc' => [true],
            ]),
        ]);

        $this->setUser($student);
        $catalog = new catalog(course_get_format($course));

        $this->assertTrue($catalog->has(catalog::FORUM), 'O balde tem o forum, bloqueado ou nao.');
        $this->assertFalse(
            $catalog->has_visible(catalog::FORUM),
            'Unico item bloqueado nao pode deixar a aba aparecer.'
        );
        $this->assertTrue($catalog->has_visible(catalog::AULA));
    }

    /**
     * Forum liberado: has_visible() acompanha has().
     *
     * @return void
     */
    public function test_has_visible_do_forum_liberado_e_verdadeiro(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'ldg']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $generator->create_module('forum', ['course' => $course->id, 'section' => 1, 'name' => 'Duvidas']);

        $this->setUser($student);
        $catalog = new catalog(course_get_format($course));

        $this->assertTrue($catalog->has(catalog::FORUM));
        $this->assertTrue($catalog->has_visible(catalog::FORUM));
    }

    /**
     * Balde inexistente nao e erro em has_visible().
     *
     * @return void
     */
    public function test_has_visible_sem_balde_e_falso(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'ldg']);
        $catalog = new catalog(course_get_format($course));

        $this->assertFalse($catalog->has_visible(catalog::CERTIFICADO));
    }
}
