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

use local_marketplace\company;
use local_marketplace\offer;

/**
 * Formulario da condicao no editor do curso.
 *
 * @package    availability_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Strings usadas pelo JS do formulario.
     *
     * @return array
     */
    protected function get_javascript_strings() {
        return ['title', 'label_offer', 'anyoffer'];
    }

    /**
     * Ofertas da empresa dona do curso, para o seletor.
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param \section_info|null $section
     * @return array
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        $offers = [(object) ['id' => 0, 'name' => get_string('anyoffer', 'availability_marketplace')]];

        $company = self::get_company_for_course($course);
        if ($company) {
            foreach (offer::get_published($company->get('id')) as $offer) {
                $offers[] = (object) [
                    'id' => (int) $offer->get('id'),
                    'name' => format_string($offer->get('name')),
                ];
            }
        }

        return [$offers];
    }

    /**
     * A condicao so faz sentido em curso de empresa do marketplace.
     *
     * Sem isto ela apareceria na lista de restricoes de qualquer curso do
     * site, inclusive os que nada tem a ver com venda.
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param \section_info|null $section
     * @return bool
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return self::get_company_for_course($course) !== null;
    }

    /**
     * Empresa dona do curso, pela categoria dele.
     *
     * @param \stdClass $course
     * @return company|null
     */
    protected static function get_company_for_course($course): ?company {
        if (empty($course->category)) {
            return null;
        }
        return company::for_course((int) $course->id);
    }
}
