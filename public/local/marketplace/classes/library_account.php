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

namespace local_marketplace;

use core\persistent;
use lang_string;
use moodle_exception;

/**
 * Library da Bunny de uma empresa, dentro da conta UNICA da plataforma.
 *
 * Mesmo padrao de company_account (empresa x recurso externo), mas 1:1: nao
 * ha dimensao de pais aqui, so uma library por empresa. A chave de conta da
 * PLATAFORMA (usada so para criar libraries novas) nao mora nesta tabela -
 * fica numa unica config cifrada do plugin, porque e uma so, nao uma por
 * empresa. O que mora aqui e a chave da LIBRARY em si, que o player usa para
 * assinar o embed.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class library_account extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_library';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'companyid' => ['type' => PARAM_INT],
            'bunnylibraryid' => ['type' => PARAM_INT],
            'apikey' => ['type' => PARAM_RAW],
            'securitykey' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'cdnhostname' => [
                'type' => PARAM_RAW,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'maxresolution' => [
                'type' => PARAM_ALPHANUM,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'webhooksecret' => ['type' => PARAM_ALPHANUM, 'default' => ''],
        ];
    }

    /**
     * Gera o segredo do webhook antes de gravar, quando ninguem definiu um.
     *
     * E como o mod_bunnystream sabe de qual empresa veio um POST no
     * webhook - cada library tem o proprio segredo, nunca um singleton.
     *
     * @return void
     */
    protected function before_create() {
        if ($this->raw_get('webhooksecret') === null || $this->raw_get('webhooksecret') === '') {
            $this->raw_set('webhooksecret', bin2hex(random_bytes(32)));
        }
    }

    /**
     * So o teto de resolucao vale, quando preenchido.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_maxresolution($value) {
        if ($value === null) {
            return true;
        }

        if (!in_array($value, plan_tier::RESOLUTIONS, true)) {
            return new lang_string('errorinvalidresolution', 'local_marketplace', $value);
        }

        return true;
    }

    /**
     * Nao pode haver duas libraries para a mesma empresa.
     *
     * O indice unico ja garante isso no banco, mas ali o erro sai como
     * exception de integridade no meio de um salvamento. Aqui sai como
     * mensagem de campo, que e o que a pessoa consegue corrigir.
     *
     * @param int $value
     * @return true|lang_string
     */
    protected function validate_companyid($value) {
        $existing = self::get_record(['companyid' => (int) $value]);
        if ($existing && $existing->get('id') != $this->get('id')) {
            return new lang_string('errorlibrarytaken', 'local_marketplace');
        }

        return true;
    }

    /**
     * A library de uma empresa.
     *
     * @param int $companyid
     * @return library_account|null
     */
    public static function get_for(int $companyid): ?library_account {
        $record = self::get_record(['companyid' => $companyid]);

        return $record ?: null;
    }

    /**
     * A library dona de um id da Bunny.
     *
     * Usado por quem so tem o `bunnylibraryid` guardado (o video ja existe,
     * mas o curso/empresa nao foi passado) - o `mod_bunnystream` resolve o
     * dono de um video existente por aqui, sem precisar do companyid.
     *
     * @param int $bunnylibraryid
     * @return library_account|null
     */
    public static function get_by_bunnylibraryid(int $bunnylibraryid): ?library_account {
        $record = self::get_record(['bunnylibraryid' => $bunnylibraryid]);

        return $record ?: null;
    }

    /**
     * A library dona de um segredo de webhook.
     *
     * @param string $secret
     * @return library_account|null
     */
    public static function get_by_webhook_secret(string $secret): ?library_account {
        if ($secret === '') {
            return null;
        }

        $record = self::get_record(['webhooksecret' => $secret]);

        return $record ?: null;
    }

    /**
     * Chave da library, em claro, para falar com a API da Bunny.
     *
     * @return string
     */
    public function get_api_key(): string {
        return self::decrypt((string) $this->raw_get('apikey'));
    }

    /**
     * Chave de seguranca, em claro, para assinar a URL/token de embed.
     *
     * Nula ate o proximo sub-passo habilitar a autenticacao por token na
     * library - a Bunny nao devolve isto na criacao.
     *
     * @return string Vazio quando ainda nao habilitada.
     */
    public function get_security_key(): string {
        $stored = $this->raw_get('securitykey');

        return $stored === null ? '' : self::decrypt((string) $stored);
    }

    /**
     * Cifra um valor antes de gravar.
     *
     * @param string $value
     * @return string
     * @throws moodle_exception Quando a instalacao nao tem chave de cifragem.
     */
    public static function encrypt(string $value): string {
        if (!\core\encryption::key_exists()) {
            throw new moodle_exception('errornoencryptionkey', 'local_marketplace');
        }

        return \core\encryption::encrypt($value);
    }

    /**
     * Decifra um valor gravado.
     *
     * Devolve vazio em vez de explodir quando a chave de cifragem sumiu: o
     * player responde "video indisponivel", que e recuperavel, em vez de
     * derrubar a pagina do aluno com um erro de infraestrutura.
     *
     * @param string $value
     * @return string
     */
    protected static function decrypt(string $value): string {
        if ($value === '') {
            return '';
        }

        try {
            return \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            debugging('local_marketplace: nao foi possivel decifrar a chave da library - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
