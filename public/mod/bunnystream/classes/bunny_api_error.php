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

/**
 * Erro devolvido pela API da Bunny.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bunny_api_error extends \moodle_exception {
    /** @var int Codigo HTTP devolvido pela Bunny. */
    public $statuscode;

    /**
     * Construtor.
     *
     * @param int $code
     * @param string $message
     */
    public function __construct(int $code, string $message) {
        $this->statuscode = $code;
        parent::__construct('error', 'mod_bunnystream', '', null, $message);
    }
}
