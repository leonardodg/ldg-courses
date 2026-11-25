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
 * Adesao a uma oferta gratuita.
 *
 * Oferta de graca nao passa pelo gateway - nao ha o que cobrar. Mas passa
 * pelo MESMO caminho de entrega das pagas, para que o direito, a matricula e
 * a liberacao de topico se comportem igual.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_marketplace\company;
use local_marketplace\offer;
use local_marketplace\payment\service_provider;

$offerid = required_param('offerid', PARAM_INT);
require_sesskey();

require_login();

$offer = new offer($offerid);
$company = new company($offer->get('companyid'));

// Sem esta checagem, um POST direto com o id de uma oferta paga entregaria o
// acesso de graca.
if (!$offer->is_free() || $offer->get('status') !== offer::STATUS_PUBLISHED) {
    throw new moodle_exception('invalidaccess', 'error');
}
if ($company->get('status') !== company::STATUS_ACTIVE) {
    throw new moodle_exception('invalidaccess', 'error');
}

// Paymentid 0: nao houve pagamento. deliver_order e idempotente, entao clicar
// duas vezes estende em vez de duplicar.
service_provider::deliver_order('offer', $offerid, 0, (int) $USER->id);

redirect(
    service_provider::get_success_url('offer', $offerid),
    get_string('accessgranted', 'local_marketplace'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
