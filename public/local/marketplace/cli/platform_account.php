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
 * Cria (ou so mostra, se ja existir) a conta de pagamento da PROPRIA
 * PLATAFORMA - a que recebe a assinatura SaaS que a empresa parceira paga,
 * nao a que recebe a venda de curso.
 *
 * IDEMPOTENTE: rodar de novo devolve a mesma conta, nunca cria uma segunda.
 *
 * Existe porque essa conta nasce sozinha, na hora do primeiro checkout de
 * plano (service_provider::get_payable_plan()) - e nesse momento ela ainda
 * nao tem gateway nenhum vinculado, entao o modal de pagamento apareceria
 * vazio para quem clicar primeiro. Rodar este script ANTES resolve isso: cria
 * a conta e devolve o link direto pra vincular cada gateway.
 *
 * Uso:
 *   php local/marketplace/cli/platform_account.php
 *   php local/marketplace/cli/platform_account.php --country=AR
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_marketplace\api;

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'country' => 'BR'],
    ['h' => 'help', 'c' => 'country']
);

if ($options['help']) {
    cli_writeln("Cria (ou mostra) a conta de pagamento da PLATAFORMA, para um pais.

Uso:
  php local/marketplace/cli/platform_account.php [--country=BR]

Opcoes:
  -h, --help            Mostra esta ajuda
  -c, --country=BR      Pais ISO-3166 alpha-2 (padrao: BR)
");
    exit(0);
}

$account = api::get_or_create_platform_account((string) $options['country']);
$accountid = (int) $account->get('id');

cli_writeln('Conta: ' . $account->get('name'));
cli_writeln('accountid: ' . $accountid);
cli_writeln('idnumber: ' . $account->get('idnumber'));
cli_writeln('');
cli_writeln('Vincule o(s) gateway(s) direto por estes enderecos (exige');
cli_writeln('login de administrador com moodle/payment:manageaccounts):');
cli_writeln('');
foreach (['mercadopago', 'asaas', 'pagarme'] as $gateway) {
    cli_writeln('  ' . (new moodle_url('/payment/manage_gateway.php', [
        'accountid' => $accountid,
        'gateway' => $gateway,
    ]))->out(false));
}

exit(0);
