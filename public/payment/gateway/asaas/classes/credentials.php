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

use core_payment\account_gateway;
use moodle_exception;

/**
 * Credencial do vendedor, por ambiente, cifrada.
 *
 * Duas decisoes moram aqui.
 *
 * A primeira e guardar homologacao e producao LADO A LADO, e nao um par de
 * campos que muda de significado conforme um interruptor. O vendedor vincula
 * uma vez em cada ambiente e a plataforma alterna sem ninguem redigitar nada -
 * e, mais importante, uma chave de homologacao nunca tem como ser usada em
 * producao por engano, porque a chave lida depende do ambiente pedido e nao do
 * que estiver no campo naquele momento.
 *
 * A segunda e cifrar. A chave do Asaas nao expira sozinha e da acesso amplo a
 * conta bancaria do vendedor - e diferente de um token OAuth de seis meses.
 * \core\encryption guarda a chave de cifragem no moodledata, fora do banco:
 * um dump do banco, sozinho, nao abre a conta de ninguem.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /** @var string Nome do gateway no core_payment. */
    public const GATEWAY = 'asaas';

    /**
     * Ambiente que a plataforma esta usando agora.
     *
     * @return string sandbox|production
     */
    public static function current_environment(): string {
        $configured = (string) get_config('paygw_asaas', 'environment');

        return $configured === asaas_client::ENV_PRODUCTION
            ? asaas_client::ENV_PRODUCTION
            : asaas_client::ENV_SANDBOX;
    }

    /**
     * Carteira da plataforma que recebe a comissao, no ambiente pedido.
     *
     * @param string $environment
     * @return string
     */
    public static function platform_wallet(string $environment): string {
        return trim((string) get_config('paygw_asaas', 'platformwalletid_' . $environment));
    }

    /**
     * Segredo esperado no header do webhook, no ambiente pedido.
     *
     * @param string $environment
     * @return string
     */
    public static function webhook_token(string $environment): string {
        return trim((string) get_config('paygw_asaas', 'webhooktoken_' . $environment));
    }

    /**
     * Configuracao gravada para uma conta de pagamento.
     *
     * @param int $accountid
     * @return array
     */
    public static function get_config(int $accountid): array {
        $gateway = account_gateway::get_record([
            'accountid' => $accountid,
            'gateway' => self::GATEWAY,
        ]);

        return $gateway ? $gateway->get_configuration() : [];
    }

    /**
     * O vendedor esta vinculado neste ambiente?
     *
     * @param int $accountid
     * @param string $environment
     * @return bool
     */
    public static function is_linked(int $accountid, string $environment): bool {
        $config = self::get_config($accountid);

        return !empty($config['apikey_' . $environment]);
    }

    /**
     * Chave em claro do vendedor, para falar com a API.
     *
     * @param int $accountid
     * @param string $environment
     * @return string
     */
    public static function api_key(int $accountid, string $environment): string {
        $config = self::get_config($accountid);
        $stored = (string) ($config['apikey_' . $environment] ?? '');

        return $stored === '' ? '' : self::decrypt($stored);
    }

    /**
     * Carteira do vendedor, descoberta no vinculo.
     *
     * @param int $accountid
     * @param string $environment
     * @return string
     */
    public static function wallet_id(int $accountid, string $environment): string {
        $config = self::get_config($accountid);

        return (string) ($config['walletid_' . $environment] ?? '');
    }

    /**
     * Grava o vinculo de um ambiente, preservando o outro.
     *
     * Escreve direto na config do account_gateway, e nao pelo formulario do
     * core. O formulario serializa TUDO que nao e propriedade do
     * account_gateway, entao um campo ausente e apagado no salvamento seguinte
     * e um campo presente aparece na tela - nenhum dos dois serve para uma
     * chave cifrada. E o mesmo caminho que o vinculo OAuth do Mercado Pago usa
     * neste projeto, pela mesma razao.
     *
     * Habilita o gateway junto: account::is_available() exige o gateway
     * habilitado, e nao so a credencial guardada. Vincular sem habilitar
     * deixava a empresa aparecendo como "sem meio de pagamento" com o vinculo
     * concluido - um dos erros ja catalogados neste projeto.
     *
     * @param int $accountid
     * @param string $environment
     * @param string $apikey Chave em claro.
     * @param string $walletid
     * @param string $accountname Nome da conta, so para a tela.
     * @return void
     */
    public static function store(
        int $accountid,
        string $environment,
        string $apikey,
        string $walletid,
        string $accountname
    ): void {
        $gateway = account_gateway::get_record([
            'accountid' => $accountid,
            'gateway' => self::GATEWAY,
        ]);

        if (!$gateway) {
            $gateway = new account_gateway(0, (object) [
                'accountid' => $accountid,
                'gateway' => self::GATEWAY,
                'enabled' => 1,
                'config' => '{}',
            ]);
            $gateway->create();
        }

        $config = $gateway->get_configuration();
        $config['apikey_' . $environment] = self::encrypt($apikey);
        $config['walletid_' . $environment] = $walletid;
        $config['accountname_' . $environment] = $accountname;
        $config['keytail_' . $environment] = \core_text::substr($apikey, -6);

        $gateway->set('config', json_encode($config));
        $gateway->set('enabled', 1);
        $gateway->update();
    }

    /**
     * Remove o vinculo de UM ambiente.
     *
     * O outro fica. Desvincular a homologacao nao pode derrubar a producao -
     * seria interromper venda de verdade para arrumar um teste.
     *
     * @param int $accountid
     * @param string $environment
     * @return void
     */
    public static function forget(int $accountid, string $environment): void {
        $gateway = account_gateway::get_record([
            'accountid' => $accountid,
            'gateway' => self::GATEWAY,
        ]);
        if (!$gateway) {
            return;
        }

        $config = $gateway->get_configuration();
        foreach (['apikey_', 'walletid_', 'accountname_', 'keytail_'] as $prefix) {
            unset($config[$prefix . $environment]);
        }

        // Sem nenhum ambiente vinculado o gateway nao tem como cobrar. Deixar
        // habilitado faria a empresa continuar anunciando um meio de pagamento
        // que falharia no checkout.
        $other = $environment === asaas_client::ENV_SANDBOX
            ? asaas_client::ENV_PRODUCTION
            : asaas_client::ENV_SANDBOX;
        $stillusable = !empty($config['apikey_' . $other]);

        $gateway->set('config', json_encode($config));
        $gateway->set('enabled', $stillusable ? 1 : 0);
        $gateway->update();
    }

    /**
     * A instalacao tem chave de cifragem?
     *
     * @return bool
     */
    public static function encryption_ready(): bool {
        return \core\encryption::key_exists();
    }

    /**
     * Cifra.
     *
     * @param string $value
     * @return string
     * @throws moodle_exception Quando a instalacao nao tem chave de cifragem.
     */
    protected static function encrypt(string $value): string {
        if (!self::encryption_ready()) {
            throw new moodle_exception('errornoencryptionkey', 'paygw_asaas');
        }

        return \core\encryption::encrypt($value);
    }

    /**
     * Decifra.
     *
     * Devolve vazio em vez de explodir quando a chave de cifragem sumiu: o
     * checkout responde "meio de pagamento indisponivel", que e recuperavel,
     * em vez de derrubar a pagina do aluno com um erro de infraestrutura.
     *
     * @param string $value
     * @return string
     */
    protected static function decrypt(string $value): string {
        try {
            return \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            debugging('paygw_asaas: nao foi possivel decifrar a chave do vendedor - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
