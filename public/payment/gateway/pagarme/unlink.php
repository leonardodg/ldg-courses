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
 * Remove o vinculo de UM ambiente.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use paygw_pagarme\credentials;
use paygw_pagarme\gateway;

$accountid = required_param('accountid', PARAM_INT);
$environment = required_param('environment', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$account = new \core_payment\account($accountid);
$context = $account->get_context();
require_capability('moodle/payment:manageaccounts', $context);

$returnurl = new moodle_url('/payment/manage_gateway.php', [
    'accountid' => $accountid,
    'gateway' => credentials::GATEWAY,
]);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/payment/gateway/pagarme/unlink.php', [
    'accountid' => $accountid,
    'environment' => $environment,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('unlink', 'paygw_pagarme'));
$PAGE->set_heading(get_string('unlink', 'paygw_pagarme'));

if ($confirm && confirm_sesskey()) {
    credentials::forget($accountid, $environment);
    redirect($returnurl, get_string('unlinkdone', 'paygw_pagarme'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('unlinkconfirm', 'paygw_pagarme', gateway::environment_label($environment))
        . ' ' . get_string('unlinknotice', 'paygw_pagarme'),
    new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $returnurl
);
echo $OUTPUT->footer();
