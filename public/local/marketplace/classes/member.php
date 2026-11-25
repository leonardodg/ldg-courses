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

/**
 * Vendedor vinculado a uma empresa.
 *
 * Uma empresa tem N membros, e um usuario pode participar de varias empresas.
 * A credencial de pagamento, porem, e da EMPRESA - fica na payment account do
 * core_payment, no contexto da categoria dela. Foi uma escolha
 * deliberada: se a credencial fosse por pessoa, o split de um curso escrito a
 * quatro maos nao teria destinatario definido.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class member extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_member';

    /** @var string Administra a empresa e a conta de pagamento. */
    public const ROLE_OWNER = 'owner';

    /** @var string Publica cursos pela empresa. */
    public const ROLE_SELLER = 'seller';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'companyid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'memberrole' => [
                'type' => PARAM_ALPHA,
                'default' => self::ROLE_SELLER,
                'choices' => [self::ROLE_OWNER, self::ROLE_SELLER],
            ],
        ];
    }

    /**
     * O usuario e dono da empresa?
     *
     * @return bool
     */
    public function is_owner(): bool {
        return $this->get('memberrole') === self::ROLE_OWNER;
    }

    /**
     * Membros de uma empresa.
     *
     * @param int $companyid
     * @return member[]
     */
    public static function get_by_company(int $companyid): array {
        return self::get_records(['companyid' => $companyid], 'memberrole');
    }

    /**
     * O usuario participa desta empresa?
     *
     * @param int $companyid
     * @param int $userid
     * @return member|false
     */
    public static function get_membership(int $companyid, int $userid) {
        return self::get_record(['companyid' => $companyid, 'userid' => $userid]);
    }
}
