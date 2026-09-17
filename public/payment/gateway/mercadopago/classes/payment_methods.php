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

/**
 * Quais meios de pagamento uma assinatura aceita - e por qual empresa.
 *
 * Antes desta classe subscribe.php oferecia cartao, Pix e boleto sempre juntos,
 * para toda conta. Isso deixa de valer no dia em que uma empresa so tem CPF
 * cadastrado para boleto no Mercado Pago, ou decide nao lidar com estorno de
 * Pix - a tela dela nao pode continuar oferecendo o que ela nao consegue
 * cobrar.
 *
 * MESMO DESENHO DO card_capture: tres valores por meio, no PLUGIN
 * (get_config) e por conta (account_gateway::get_configuration()), e o vazio
 * na conta significa "usa o padrao do plugin". Este padrao e do
 * paygw_mercadopago, e nao da plataforma inteira - Asaas e Pagar.me sao
 * plugins separados, com os proprios padroes. Contas que existiam antes desta
 * opcao continuam oferecendo os tres meios, porque o padrao preserva o
 * comportamento anterior.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_methods {
    /** @var string Cartao de credito, com o card_capture escolhido. */
    const METHOD_CARD = 'card';

    /** @var string Pix - fatura por ciclo, sem cartao guardado. */
    const METHOD_PIX = 'pix';

    /** @var string Boleto (bolbradesco) - fatura por ciclo, sem cartao guardado. */
    const METHOD_BOLETO = 'boleto';

    /** @var string[] Os tres meios, na ordem em que a tela oferece. */
    const METHODS = [self::METHOD_CARD, self::METHOD_PIX, self::METHOD_BOLETO];

    /**
     * Este meio esta habilitado para esta conta?
     *
     * @param string $method Um de METHODS
     * @param int $accountid Conta de pagamento da empresa, 0 para so o padrao do plugin
     * @return bool
     */
    public static function enabled(string $method, int $accountid = 0): bool {
        $configurado = self::configured_value($method, $accountid);

        if ($configurado !== '') {
            return $configurado === '1';
        }

        $doplugin = get_config('paygw_mercadopago', 'method' . $method);

        // Nunca configurado no plugin (instalacao ainda nao visitou a tela de
        // configuracoes): o padrao HISTORICO deste plugin e habilitado, e
        // get_config() devolve false tanto para "nunca configurado" quanto
        // para "desligado" - so o primeiro caso cai aqui.
        return $doplugin === false ? true : (bool) $doplugin;
    }

    /**
     * Os meios habilitados para esta conta, na ordem de METHODS.
     *
     * @param int $accountid Conta de pagamento da empresa, 0 para so o padrao do plugin
     * @return string[]
     */
    public static function enabled_methods(int $accountid = 0): array {
        return array_values(array_filter(
            self::METHODS,
            static fn(string $metodo): bool => self::enabled($metodo, $accountid)
        ));
    }

    /**
     * O valor configurado para este meio, na conta ou vazio quando ela nao
     * escolheu - antes de cair no padrao do plugin.
     *
     * @param string $method
     * @param int $accountid
     * @return string '' (usa o padrao do plugin), '1' ou '0'
     */
    protected static function configured_value(string $method, int $accountid): string {
        if ($accountid > 0) {
            $daconta = gateway::account_configuration($accountid)['method' . $method] ?? '';

            if ($daconta !== '' && $daconta !== null) {
                return (string) $daconta;
            }
        }

        return '';
    }

    /**
     * As opcoes do meio, para um <select> por conta - "usar o padrao do
     * plugin", habilitado ou desabilitado.
     *
     * @return array<string,string>
     */
    public static function form_options(): array {
        return [
            '' => get_string('methodusesite', 'paygw_mercadopago'),
            '1' => get_string('methodenabled', 'paygw_mercadopago'),
            '0' => get_string('methoddisabled', 'paygw_mercadopago'),
        ];
    }
}
