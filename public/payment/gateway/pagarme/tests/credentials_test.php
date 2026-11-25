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

namespace paygw_pagarme;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A credencial cifrada, por ambiente.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(credentials::class)]
final class credentials_test extends \advanced_testcase {
    /** @var int Conta de pagamento usada nos testes. */
    protected int $accountid;

    /**
     * Uma conta de pagamento de verdade, e chave de cifragem.
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
            'name' => 'Conta de teste',
            'idnumber' => '',
            'contextid' => \context_system::instance()->id,
            'enabled' => 1,
        ]);
        $account->create();
        $this->accountid = (int) $account->get('id');
    }

    public function test_a_chave_e_guardada_cifrada(): void {
        global $DB;

        credentials::store(
            $this->accountid,
            pagarme_client::ENV_SANDBOX,
            'sk_test_segredo123',
            'rp_plataforma',
            'rp_vendedor',
            'Conta do vendedor'
        );

        $this->assertSame(
            'sk_test_segredo123',
            credentials::api_key($this->accountid, pagarme_client::ENV_SANDBOX)
        );

        // E, principalmente: nao esta em claro no banco. Um dump do banco,
        // sozinho, nao abre a conta de ninguem.
        $raw = $DB->get_field('payment_gateways', 'config', [
            'accountid' => $this->accountid,
            'gateway' => 'pagarme',
        ]);
        $this->assertStringNotContainsString('sk_test_segredo123', (string) $raw);
    }

    public function test_os_dois_recebedores_sao_guardados(): void {
        credentials::store(
            $this->accountid,
            pagarme_client::ENV_SANDBOX,
            'sk_test_x',
            'rp_plataforma',
            'rp_vendedor',
            'Conta'
        );

        $this->assertSame(
            'rp_plataforma',
            credentials::platform_recipient($this->accountid, pagarme_client::ENV_SANDBOX)
        );
        $this->assertSame(
            'rp_vendedor',
            credentials::seller_recipient($this->accountid, pagarme_client::ENV_SANDBOX)
        );
    }

    public function test_os_ambientes_sao_independentes(): void {
        credentials::store(
            $this->accountid,
            pagarme_client::ENV_SANDBOX,
            'sk_test_homolog',
            'rp_h',
            'rp_hv',
            'Homologacao'
        );
        credentials::store(
            $this->accountid,
            pagarme_client::ENV_PRODUCTION,
            'sk_producao',
            'rp_p',
            'rp_pv',
            'Producao'
        );

        // Uma chave de homologacao nunca pode ser lida como se fosse de
        // producao: a chave lida depende do ambiente pedido.
        $this->assertSame(
            'sk_test_homolog',
            credentials::api_key($this->accountid, pagarme_client::ENV_SANDBOX)
        );
        $this->assertSame(
            'sk_producao',
            credentials::api_key($this->accountid, pagarme_client::ENV_PRODUCTION)
        );
    }

    public function test_vincular_habilita_o_gateway(): void {
        global $DB;

        credentials::store(
            $this->accountid,
            pagarme_client::ENV_SANDBOX,
            'sk_test_x',
            'rp_p',
            'rp_v',
            'Conta'
        );

        // O metodo account::is_available() exige o gateway habilitado, e nao
        // so a credencial guardada. Vincular sem habilitar deixava a empresa
        // aparecendo como "sem meio de pagamento" com o vinculo concluido.
        $this->assertSame(1, (int) $DB->get_field('payment_gateways', 'enabled', [
            'accountid' => $this->accountid,
            'gateway' => 'pagarme',
        ]));
    }

    public function test_esquecer_um_ambiente_preserva_o_outro(): void {
        credentials::store($this->accountid, pagarme_client::ENV_SANDBOX, 'sk_test_h', 'rp_p', 'rp_v', 'H');
        credentials::store($this->accountid, pagarme_client::ENV_PRODUCTION, 'sk_p', 'rp_p', 'rp_v', 'P');

        credentials::forget($this->accountid, pagarme_client::ENV_SANDBOX);

        // Desvincular a homologacao nao pode derrubar a producao: seria
        // interromper venda de verdade para arrumar um teste.
        $this->assertSame('', credentials::api_key($this->accountid, pagarme_client::ENV_SANDBOX));
        $this->assertSame('sk_p', credentials::api_key($this->accountid, pagarme_client::ENV_PRODUCTION));
    }

    public function test_esquecer_o_ultimo_ambiente_desabilita_o_gateway(): void {
        global $DB;

        credentials::store($this->accountid, pagarme_client::ENV_SANDBOX, 'sk_test_x', 'rp_p', 'rp_v', 'C');
        credentials::forget($this->accountid, pagarme_client::ENV_SANDBOX);

        $this->assertSame(0, (int) $DB->get_field('payment_gateways', 'enabled', [
            'accountid' => $this->accountid,
            'gateway' => 'pagarme',
        ]));
    }

    public function test_conta_sem_vinculo_devolve_vazio(): void {
        $this->assertSame('', credentials::api_key($this->accountid, pagarme_client::ENV_SANDBOX));
        $this->assertFalse(credentials::is_linked($this->accountid, pagarme_client::ENV_SANDBOX));
    }

    public function test_o_ambiente_padrao_e_homologacao(): void {
        // Errar para homologacao aqui custa uma venda que nao acontece.
        // Errar para producao custaria uma cobranca real num teste.
        $this->assertSame(pagarme_client::ENV_SANDBOX, credentials::current_environment());

        set_config('environment', pagarme_client::ENV_PRODUCTION, 'paygw_pagarme');
        $this->assertSame(pagarme_client::ENV_PRODUCTION, credentials::current_environment());
    }

    public function test_o_segredo_do_webhook_e_por_ambiente(): void {
        set_config('webhookuser_sandbox', 'usuario_h', 'paygw_pagarme');
        set_config('webhookpassword_sandbox', 'senha_h', 'paygw_pagarme');

        $this->assertSame('usuario_h', credentials::webhook_user(pagarme_client::ENV_SANDBOX));
        $this->assertSame('senha_h', credentials::webhook_password(pagarme_client::ENV_SANDBOX));
        $this->assertSame('', credentials::webhook_user(pagarme_client::ENV_PRODUCTION));
    }
}
