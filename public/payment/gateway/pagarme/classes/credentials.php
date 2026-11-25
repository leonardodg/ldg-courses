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

use core_payment\account_gateway;
use moodle_exception;

/**
 * Credencial do vendedor, por ambiente, cifrada.
 *
 * A diferenca que importa em relacao ao paygw_asaas esta no recebedor da
 * plataforma. No Asaas a carteira da plataforma e uma so, e por isso vive nas
 * settings do site. Aqui nao: um recipient do Pagar.me e objeto INTERNO a uma
 * conta, entao a plataforma tem um rp_ diferente dentro da conta de cada
 * vendedor. O identificador acompanha a conta de pagamento, e nao o site.
 *
 * E consequencia direta do ADR-0003 - a cobranca nasce na conta do vendedor
 * porque e ele quem emite a nota - e nao uma escolha de arrumacao.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /** @var string Nome do gateway no core_payment. */
    public const GATEWAY = 'pagarme';

    /** @var string[] Campos guardados por ambiente. */
    public const FIELDS = [
        'apikey_',
        'platformrecipient_',
        'sellerrecipient_',
        'accountname_',
        'keytail_',
    ];

    /**
     * Ambiente que a plataforma esta usando agora.
     *
     * @return string sandbox|production
     */
    public static function current_environment(): string {
        $configured = (string) get_config('paygw_pagarme', 'environment');

        return $configured === pagarme_client::ENV_PRODUCTION
            ? pagarme_client::ENV_PRODUCTION
            : pagarme_client::ENV_SANDBOX;
    }

    /**
     * Usuario que o Pagar.me manda no Basic do webhook.
     *
     * @param string $environment
     * @return string
     */
    public static function webhook_user(string $environment): string {
        return trim((string) get_config('paygw_pagarme', 'webhookuser_' . $environment));
    }

    /**
     * Senha que o Pagar.me manda no Basic do webhook.
     *
     * @param string $environment
     * @return string
     */
    public static function webhook_password(string $environment): string {
        return (string) get_config('paygw_pagarme', 'webhookpassword_' . $environment);
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
     * Chave em claro do vendedor.
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
     * Recebedor da plataforma DENTRO da conta deste vendedor.
     *
     * @param int $accountid
     * @param string $environment
     * @return string
     */
    public static function platform_recipient(int $accountid, string $environment): string {
        $config = self::get_config($accountid);

        return (string) ($config['platformrecipient_' . $environment] ?? '');
    }

    /**
     * Recebedor do proprio vendedor.
     *
     * @param int $accountid
     * @param string $environment
     * @return string
     */
    public static function seller_recipient(int $accountid, string $environment): string {
        $config = self::get_config($accountid);

        return (string) ($config['sellerrecipient_' . $environment] ?? '');
    }

    /**
     * Grava o vinculo de um ambiente, preservando o outro.
     *
     * Escreve direto na config do account_gateway, e nao pelo formulario do
     * core: o formulario serializa tudo que recebe, entao campo ausente e
     * apagado no salvamento seguinte e campo presente aparece na tela - nenhum
     * dos dois serve para uma chave cifrada.
     *
     * Habilita o gateway junto, porque account::is_available() exige o gateway
     * habilitado e nao so a credencial guardada.
     *
     * @param int $accountid
     * @param string $environment
     * @param string $apikey Chave em claro.
     * @param string $platformrecipient
     * @param string $sellerrecipient
     * @param string $accountname
     * @return void
     */
    public static function store(
        int $accountid,
        string $environment,
        string $apikey,
        string $platformrecipient,
        string $sellerrecipient,
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
        $config['platformrecipient_' . $environment] = $platformrecipient;
        $config['sellerrecipient_' . $environment] = $sellerrecipient;
        $config['accountname_' . $environment] = $accountname;
        $config['keytail_' . $environment] = \core_text::substr($apikey, -6);

        $gateway->set('config', json_encode($config));
        $gateway->set('enabled', 1);
        $gateway->update();
    }

    /**
     * Remove o vinculo de UM ambiente.
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
        foreach (self::FIELDS as $prefix) {
            unset($config[$prefix . $environment]);
        }

        // Sem nenhum ambiente vinculado o gateway nao tem como cobrar, e
        // deixa-lo habilitado faria a empresa anunciar um meio de pagamento
        // que falharia no checkout.
        $other = $environment === pagarme_client::ENV_SANDBOX
            ? pagarme_client::ENV_PRODUCTION
            : pagarme_client::ENV_SANDBOX;
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
            throw new moodle_exception('errornoencryptionkey', 'paygw_pagarme');
        }

        return \core\encryption::encrypt($value);
    }

    /**
     * Decifra.
     *
     * Devolve vazio em vez de estourar quando a chave de cifragem sumiu: o
     * checkout responde "meio de pagamento indisponivel", que e recuperavel,
     * em vez de derrubar a pagina do aluno com erro de infraestrutura.
     *
     * @param string $value
     * @return string
     */
    protected static function decrypt(string $value): string {
        try {
            return \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            debugging(
                'paygw_pagarme: nao foi possivel decifrar a chave do vendedor - ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return '';
        }
    }
}
