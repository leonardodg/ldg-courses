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

namespace paygw_asaas;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A credencial do vendedor: cifrada, e separada por ambiente.
 *
 * Duas garantias sao testadas aqui, e as duas custam dinheiro se falharem: a
 * chave nao pode estar legivel no banco, e a chave de homologacao nao pode ser
 * usada em producao.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_asaas\credentials::class)]
final class credentials_test extends \advanced_testcase {
    /** @var int Conta de pagamento de teste. */
    protected int $accountid;

    /**
     * Conta de pagamento e chave de cifragem.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $account = new \core_payment\account(0, (object) [
            'name' => 'Conta Teste',
            'idnumber' => 'teste',
            'contextid' => \context_system::instance()->id,
            'enabled' => 1,
        ]);
        $account->create();

        $this->accountid = (int) $account->get('id');
    }

    /**
     * A chave volta inteira, e nao aparece em claro no banco.
     *
     * @return void
     */
    public function test_key_is_stored_encrypted(): void {
        global $DB;

        $key = '$aact_hmlg_chave-secreta-do-vendedor';
        credentials::store($this->accountid, asaas_client::ENV_SANDBOX, $key, 'wallet-1', 'Vendedor');

        $this->assertSame($key, credentials::api_key($this->accountid, asaas_client::ENV_SANDBOX));

        $raw = $DB->get_field('payment_gateways', 'config', [
            'accountid' => $this->accountid,
            'gateway' => 'asaas',
        ]);
        $this->assertStringNotContainsString('chave-secreta-do-vendedor', $raw);
    }

    /**
     * Os dois ambientes convivem sem se misturar.
     *
     * @return void
     */
    public function test_environments_are_independent(): void {
        credentials::store($this->accountid, asaas_client::ENV_SANDBOX, 'chave-hmlg', 'wallet-hmlg', 'Teste');
        credentials::store($this->accountid, asaas_client::ENV_PRODUCTION, 'chave-prod', 'wallet-prod', 'Real');

        $this->assertSame('chave-hmlg', credentials::api_key($this->accountid, asaas_client::ENV_SANDBOX));
        $this->assertSame('chave-prod', credentials::api_key($this->accountid, asaas_client::ENV_PRODUCTION));
        $this->assertSame('wallet-hmlg', credentials::wallet_id($this->accountid, asaas_client::ENV_SANDBOX));
        $this->assertSame('wallet-prod', credentials::wallet_id($this->accountid, asaas_client::ENV_PRODUCTION));
    }

    /**
     * Desvincular a homologacao NAO derruba a producao.
     *
     * Seria interromper venda de verdade para arrumar um teste.
     *
     * @return void
     */
    public function test_forgetting_one_environment_keeps_the_other(): void {
        credentials::store($this->accountid, asaas_client::ENV_SANDBOX, 'chave-hmlg', 'wallet-hmlg', 'Teste');
        credentials::store($this->accountid, asaas_client::ENV_PRODUCTION, 'chave-prod', 'wallet-prod', 'Real');

        credentials::forget($this->accountid, asaas_client::ENV_SANDBOX);

        $this->assertFalse(credentials::is_linked($this->accountid, asaas_client::ENV_SANDBOX));
        $this->assertTrue(credentials::is_linked($this->accountid, asaas_client::ENV_PRODUCTION));

        $gateway = \core_payment\account_gateway::get_record([
            'accountid' => $this->accountid,
            'gateway' => 'asaas',
        ]);
        $this->assertEquals(1, $gateway->get('enabled'), 'ainda da para cobrar, entao continua habilitado');
    }

    /**
     * Sem nenhum ambiente vinculado o gateway e desabilitado.
     *
     * Deixar habilitado faria a empresa anunciar um meio de pagamento que
     * quebraria no checkout.
     *
     * @return void
     */
    public function test_forgetting_the_last_environment_disables_the_gateway(): void {
        credentials::store($this->accountid, asaas_client::ENV_SANDBOX, 'chave-hmlg', 'wallet-hmlg', 'Teste');

        credentials::forget($this->accountid, asaas_client::ENV_SANDBOX);

        $gateway = \core_payment\account_gateway::get_record([
            'accountid' => $this->accountid,
            'gateway' => 'asaas',
        ]);
        $this->assertEquals(0, $gateway->get('enabled'));
    }

    /**
     * Vincular ja habilita.
     *
     * account::is_available() exige o gateway habilitado, e nao so a credencial
     * guardada. Sem isto a empresa aparece como "sem meio de pagamento" com o
     * vinculo concluido - erro ja cometido neste projeto.
     *
     * @return void
     */
    public function test_linking_enables_the_gateway(): void {
        credentials::store($this->accountid, asaas_client::ENV_SANDBOX, 'chave', 'wallet', 'Teste');

        $gateway = \core_payment\account_gateway::get_record([
            'accountid' => $this->accountid,
            'gateway' => 'asaas',
        ]);
        $this->assertEquals(1, $gateway->get('enabled'));
    }

    /**
     * Conta sem vinculo devolve vazio, e nao erro.
     *
     * @return void
     */
    public function test_unlinked_account_returns_empty(): void {
        $this->assertSame('', credentials::api_key($this->accountid, asaas_client::ENV_SANDBOX));
        $this->assertFalse(credentials::is_linked($this->accountid, asaas_client::ENV_SANDBOX));
    }

    /**
     * O ambiente em uso vem da configuracao, com homologacao como padrao.
     *
     * Errar para o lado do sandbox custa um teste que nao funciona; errar para
     * producao custa uma cobranca real.
     *
     * @return void
     */
    public function test_current_environment_defaults_to_sandbox(): void {
        unset_config('environment', 'paygw_asaas');
        $this->assertSame(asaas_client::ENV_SANDBOX, credentials::current_environment());

        set_config('environment', 'coisa-invalida', 'paygw_asaas');
        $this->assertSame(asaas_client::ENV_SANDBOX, credentials::current_environment());

        set_config('environment', asaas_client::ENV_PRODUCTION, 'paygw_asaas');
        $this->assertSame(asaas_client::ENV_PRODUCTION, credentials::current_environment());
    }

    /**
     * Carteira e token do webhook sao lidos por ambiente.
     *
     * @return void
     */
    public function test_platform_settings_are_per_environment(): void {
        set_config('platformwalletid_sandbox', 'wallet-hmlg', 'paygw_asaas');
        set_config('platformwalletid_production', 'wallet-prod', 'paygw_asaas');
        set_config('webhooktoken_sandbox', 'token-hmlg', 'paygw_asaas');

        $this->assertSame('wallet-hmlg', credentials::platform_wallet(asaas_client::ENV_SANDBOX));
        $this->assertSame('wallet-prod', credentials::platform_wallet(asaas_client::ENV_PRODUCTION));
        $this->assertSame('token-hmlg', credentials::webhook_token(asaas_client::ENV_SANDBOX));
        $this->assertSame('', credentials::webhook_token(asaas_client::ENV_PRODUCTION));
    }
}
