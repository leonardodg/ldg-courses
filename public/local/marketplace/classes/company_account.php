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

/**
 * Conta de pagamento de uma empresa em um pais.
 *
 * Esta tabela existe porque o core_payment nao sabe responder "qual das contas
 * desta empresa recebe em reais?". Ele guarda a conta por CONTEXTO, e todas as
 * contas de uma empresa vivem no mesmo contexto - o da categoria dela. Sem uma
 * chave por pais, account::get_record(['contextid' => ...]) devolve a PRIMEIRA
 * que aparecer, e uma empresa que vende no Brasil e na Argentina passaria a
 * receber em ordem de id.
 *
 * A conta em si continua sendo do core: aqui so mora o vinculo empresa x pais
 * x conta. Duplicar nome, situacao ou gateway daria duas fontes da verdade
 * para um dado financeiro.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class company_account extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_account';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'companyid' => ['type' => PARAM_INT],
            'country' => [
                'type' => PARAM_ALPHA,
                'default' => country::DEFAULT_COUNTRY,
            ],
            'accountid' => ['type' => PARAM_INT],
        ];
    }

    /**
     * O pais tem que ser um em que o marketplace opera.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_country($value) {
        if (!country::is_supported((string) $value)) {
            return new lang_string('errorcountryunsupported', 'local_marketplace', $value);
        }

        return true;
    }

    /**
     * Nao pode haver duas contas da mesma empresa no mesmo pais.
     *
     * O indice unico ja garante isso no banco, mas ali o erro sai como exception
     * de integridade no meio de um salvamento. Aqui sai como mensagem de campo,
     * que e o que a pessoa consegue corrigir.
     *
     * @param int $value
     * @return true|lang_string
     */
    protected function validate_accountid($value) {
        $existing = self::get_record(['accountid' => (int) $value]);
        if ($existing && $existing->get('id') != $this->get('id')) {
            return new lang_string('erroraccounttaken', 'local_marketplace');
        }

        return true;
    }

    /**
     * Conta de uma empresa em um pais.
     *
     * @param int $companyid
     * @param string $country
     * @return company_account|null
     */
    public static function get_for(int $companyid, string $country): ?company_account {
        $record = self::get_record([
            'companyid' => $companyid,
            'country' => country::normalize($country),
        ]);

        return $record ?: null;
    }

    /**
     * Todas as contas de uma empresa, indexadas por pais.
     *
     * @param int $companyid
     * @return array<string, company_account>
     */
    public static function list_for_company(int $companyid): array {
        $out = [];
        foreach (self::get_records(['companyid' => $companyid], 'country') as $record) {
            $out[$record->get('country')] = $record;
        }

        return $out;
    }

    /**
     * A conta do core que este vinculo aponta.
     *
     * Devolve nulo quando a conta foi apagada ou arquivada por fora - o vinculo
     * sobreviveria a exclusao, e entregar uma conta arquivada faria o checkout
     * abrir para uma conta que nao recebe mais.
     *
     * @return \core_payment\account|null
     */
    public function get_payment_account(): ?\core_payment\account {
        $account = \core_payment\account::get_record([
            'id' => (int) $this->get('accountid'),
            'archived' => 0,
        ]);

        return $account ?: null;
    }

    /**
     * Moeda em que esta conta recebe.
     *
     * @return string
     */
    public function get_currency(): string {
        return country::currency_for($this->get('country'));
    }
}
