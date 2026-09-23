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
 * Dados que o formato guarda por aula.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use core\persistent;

/**
 * A duracao de uma aula.
 *
 * Guarda o tempo do VIDEO, por cmid - nao o tempo que alguem assistiu. A
 * diferenca decide a privacidade do plugin inteiro: sem userid, isto e conteudo
 * do curso, e o provider segue sendo null_provider.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson extends persistent {
    /** @var string Tabela. */
    const TABLE = 'format_ldg_lesson';

    /**
     * Propriedades.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'duration' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Duracao nao pode ser negativa.
     *
     * Nulo passa: e o "nao sei", que e diferente de zero.
     *
     * @param int|null $value
     * @return bool|\lang_string
     */
    protected function validate_duration($value) {
        if ($value === null || $value >= 0) {
            return true;
        }

        return new \lang_string('invalidduration', 'format_ldg');
    }

    /**
     * A duracao gravada para uma aula, ou null.
     *
     * @param int $cmid
     * @return int|null
     */
    public static function duration_for(int $cmid): ?int {
        $record = self::get_record(['cmid' => $cmid]);

        if (!$record) {
            return null;
        }

        $duration = $record->get('duration');

        return $duration === null ? null : (int) $duration;
    }

    /**
     * As duracoes de varios cmids de uma vez.
     *
     * A lista de aulas chama isto UMA vez por pagina. Perguntar por aula daria
     * uma consulta por linha - o N+1 que ja mordeu este projeto antes.
     *
     * @param int[] $cmids
     * @return array<int, int|null> cmid => segundos
     */
    public static function durations_for(array $cmids): array {
        global $DB;

        if (empty($cmids)) {
            return [];
        }

        [$sql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);

        $records = $DB->get_records_select(self::TABLE, "cmid $sql", $params, '', 'cmid, duration');

        $out = [];

        foreach ($records as $record) {
            $out[(int) $record->cmid] = $record->duration === null ? null : (int) $record->duration;
        }

        return $out;
    }

    /**
     * Grava a duracao de uma aula.
     *
     * Nulo apaga a linha em vez de guardar um nulo: sem duracao conhecida, nao
     * ha o que guardar, e linha vazia so atrapalha quem for ler a tabela.
     *
     * @param int $cmid
     * @param int|null $duration segundos, ou null para limpar
     * @return void
     */
    public static function store_duration(int $cmid, ?int $duration): void {
        $record = self::get_record(['cmid' => $cmid]);

        if ($duration === null) {
            if ($record) {
                $record->delete();
            }

            return;
        }

        if ($record) {
            $record->set('duration', $duration);
            $record->update();

            return;
        }

        (new self(0, (object) ['cmid' => $cmid, 'duration' => $duration]))->create();
    }

    /**
     * Apaga as duracoes de um curso APAGADO.
     *
     * Nao confundir com troca de formato: ali as linhas ficam de proposito,
     * para quem experimentar o Topics e voltar nao perder o que preencheu. A
     * linha orfa nao quebra nada nesse meio-tempo, porque a tabela nao tem
     * chave estrangeira para course_modules.
     *
     * @param int $courseid
     * @return void
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;

        $cmids = $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$courseid]);

        if (empty($cmids)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($cmids);

        $DB->delete_records_select(self::TABLE, "cmid $sql", $params);
    }
}
