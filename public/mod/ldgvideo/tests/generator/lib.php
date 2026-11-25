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
 * Gerador de atividades de video para os testes.
 *
 * @package    mod_ldgvideo
 * @category   test
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Cria atividades de video prontas para usar.
 *
 * @package    mod_ldgvideo
 * @category   test
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ldgvideo_generator extends testing_module_generator {
    /**
     * O endereco padrao dos cenarios.
     *
     * Um video real do canal do proprio YouTube, e nao um inventado: o
     * can_embed_url() do core casa o endereco contra o regex de cada player, e
     * um "https://exemplo.com/video" nao passaria por nenhum - os cenarios
     * morreriam na validacao, longe do que estao testando.
     *
     * @var string
     */
    public const URL_PADRAO = 'https://www.youtube.com/watch?v=d2bq9QW7fZg';

    /**
     * Cria uma instancia.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $record->videourl = $record->videourl ?? self::URL_PADRAO;
        $record->aspectratio = $record->aspectratio ?? \mod_ldgvideo\url::RATIO_LANDSCAPE;
        $record->printintro = $record->printintro ?? 1;

        return parent::create_instance($record, (array) $options);
    }
}
