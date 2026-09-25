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

namespace mod_bunnystream;

use local_marketplace\company;
use local_marketplace\library_account;

/**
 * Resolve as credenciais da Bunny a partir do curso, nunca de config global.
 *
 * No plugin de referencia (amirtds/moodle-mod_bunnystream) isto lia um
 * singleton em settings.php - uma library so, para a instalacao inteira.
 * Aqui a credencial e por EMPRESA: cada curso pertence a uma empresa do
 * marketplace (local_marketplace\company::for_course()), e cada empresa tem
 * a propria library (local_marketplace\library_account::get_for()). Dois
 * cursos de empresas diferentes nunca compartilham API key.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /**
     * Credenciais em claro, a partir da empresa dona de um curso.
     *
     * @param int $courseid
     * @return \stdClass
     * @throws not_configured_exception Curso fora de empresa, ou empresa sem library.
     */
    public static function for_course(int $courseid): \stdClass {
        $company = company::for_course($courseid);
        if (!$company) {
            throw new not_configured_exception();
        }

        $library = library_account::get_for((int) $company->get('id'));
        if (!$library) {
            throw new not_configured_exception();
        }

        $cfg = self::from_library($library);
        if ($cfg->api_key === '') {
            // Decifrar falhou (chave de cifragem da instalacao sumiu) - trata
            // como nao configurado, que e recuperavel, em vez de um estouro
            // de infraestrutura na cara do professor.
            throw new not_configured_exception();
        }

        return $cfg;
    }

    /**
     * URL do webhook desta empresa, para colar no painel da Bunny.
     *
     * Cada library tem o proprio segredo - nunca uma URL global, como no
     * plugin de referencia.
     *
     * @param int $courseid
     * @return string|null Nulo quando o curso nao tem empresa/library ainda.
     */
    public static function webhook_url_for_course(int $courseid): ?string {
        global $CFG;

        $company = company::for_course($courseid);
        if (!$company) {
            return null;
        }

        $library = library_account::get_for((int) $company->get('id'));
        if (!$library) {
            return null;
        }

        return $CFG->wwwroot . '/mod/bunnystream/webhook.php?token=' . urlencode((string) $library->get('webhooksecret'));
    }

    /**
     * Credenciais em claro, a partir de uma library ja resolvida.
     *
     * @param library_account $library
     * @return \stdClass
     */
    public static function from_library(library_account $library): \stdClass {
        $cfg = new \stdClass();
        $cfg->library_id = (string) $library->get('bunnylibraryid');
        $cfg->api_key = $library->get_api_key();
        $cfg->cdn_hostname = $library->get('cdnhostname');
        $securitykey = $library->get_security_key();
        $cfg->security_key = $securitykey !== '' ? $securitykey : null;

        return $cfg;
    }
}
