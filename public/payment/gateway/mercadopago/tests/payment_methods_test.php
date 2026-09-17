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

namespace paygw_mercadopago;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Quais meios de pagamento uma assinatura aceita, por empresa.
 *
 * O padrao do site tem que preservar o comportamento anterior a esta
 * funcionalidade - cartao, Pix e boleto juntos - porque contas que ja existiam
 * nunca escolheram nada e nao podem passar a oferecer menos meios sozinhas.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\payment_methods::class)]
final class payment_methods_test extends \advanced_testcase {
    /**
     * Sem nenhuma configuracao, os tres meios estao habilitados.
     *
     * E o padrao que o plugin sempre teve, antes de esta configuracao
     * existir - preservar isto e o que impede a instalacao de uma empresa
     * ja no ar de perder um meio de pagamento por causa de um upgrade.
     *
     * @return void
     */
    public function test_o_padrao_do_site_habilita_os_tres_meios(): void {
        $this->resetAfterTest();

        $this->assertSame(
            payment_methods::METHODS,
            payment_methods::enabled_methods()
        );
    }

    /**
     * Desligar um meio no site tira ele do padrao, para quem nao escolheu
     * nada na propria conta.
     *
     * @return void
     */
    public function test_desligar_no_site_tira_do_padrao(): void {
        $this->resetAfterTest();

        set_config('methodboleto', 0, 'paygw_mercadopago');

        $this->assertFalse(payment_methods::enabled(payment_methods::METHOD_BOLETO));
        $this->assertSame(
            [payment_methods::METHOD_CARD, payment_methods::METHOD_PIX],
            payment_methods::enabled_methods()
        );
    }

    /**
     * A conta que escolheu o proprio conjunto de meios ignora o site.
     *
     * E a garantia central: uma empresa que so consegue emitir boleto (por
     * exemplo, por nao ter cadastro para Pix) nao fica presa ao padrao do
     * site, e as outras contas nao sao afetadas pela escolha dela.
     *
     * @return void
     */
    public function test_conta_com_escolha_propria_ignora_o_site(): void {
        $this->resetAfterTest();

        // Site oferece os tres.
        $accountid = $this->criar_conta_vinculada([
            'methodcard' => '0',
            'methodpix' => '0',
            'methodboleto' => '1',
        ]);

        $this->assertSame(
            [payment_methods::METHOD_BOLETO],
            payment_methods::enabled_methods($accountid)
        );

        // E o site, sem accountid, continua oferecendo os tres.
        $this->assertSame(payment_methods::METHODS, payment_methods::enabled_methods());
    }

    /**
     * A conta pode escolher SO UM dos tres meios, e deixar os outros dois no
     * padrao do site.
     *
     * O valor vazio ('') e o que representa "nao escolhi" - e diferente de
     * escolher desabilitado ('0').
     *
     * @return void
     */
    public function test_conta_pode_escolher_so_um_meio_e_herdar_o_resto(): void {
        $this->resetAfterTest();

        set_config('methodpix', 0, 'paygw_mercadopago');

        $accountid = $this->criar_conta_vinculada([
            'methodboleto' => '0',
        ]);

        // Methodcard e methodpix nao foram escolhidos por esta conta: card
        // herda o site (habilitado), pix herda o site (desabilitado).
        $this->assertSame(
            [payment_methods::METHOD_CARD],
            payment_methods::enabled_methods($accountid)
        );
    }

    /**
     * Conta inexistente nao quebra: cai no padrao do site.
     *
     * @return void
     */
    public function test_conta_inexistente_cai_no_padrao_do_site(): void {
        $this->resetAfterTest();

        set_config('methodboleto', 0, 'paygw_mercadopago');

        $this->assertSame(
            [payment_methods::METHOD_CARD, payment_methods::METHOD_PIX],
            payment_methods::enabled_methods(999999)
        );
    }

    /**
     * Cria uma conta de pagamento vinculada ao gateway mercadopago, com a
     * configuracao de meios informada.
     *
     * @param array $config
     * @return int accountid
     */
    protected function criar_conta_vinculada(array $config): int {
        $account = new \core_payment\account(0, (object) [
            'name' => 'Empresa de teste',
            'idnumber' => 'empresateste' . rand(1, 999999),
            'contextid' => \context_system::instance()->id,
            'enabled' => 1,
        ]);
        $account->create();
        $accountid = (int) $account->get('id');

        $gateway = new \core_payment\account_gateway(0, (object) [
            'accountid' => $accountid,
            'gateway' => 'mercadopago',
            'enabled' => 1,
            'config' => json_encode($config),
        ]);
        $gateway->create();

        return $accountid;
    }
}
