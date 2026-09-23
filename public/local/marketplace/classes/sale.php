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
 * Uma venda concluida, do ponto de vista do marketplace.
 *
 * Guarda o que o core_payment nao guarda: a comissao que de fato foi retida e
 * o id da transacao no gateway. Valor, moeda, gateway, comprador e data ficam
 * na tabela {payments} do core, que continua sendo a fonte da verdade - por
 * isso as consultas aqui fazem join em vez de ler colunas duplicadas.
 *
 * Cada gateway grava a sua linha depois de salvar o pagamento no core. E a
 * inversao que faz o relatorio parar de conhecer o nome dos gateways: antes ele
 * dava SELECT em paygw_mercadopago, e uma venda pelo Asaas simplesmente nao
 * apareceria - sem erro, sem aviso, so um total menor.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sale extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_sale';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'paymentid' => ['type' => PARAM_INT],
            'offerid' => ['type' => PARAM_INT],
            'companyid' => ['type' => PARAM_INT],
            'feeamount' => ['type' => PARAM_FLOAT, 'default' => 0],
            'feepercent' => ['type' => PARAM_FLOAT, 'default' => 0],
            'feebase' => [
                'type' => PARAM_ALPHA,
                'default' => commission::BASE_GROSS,
                'choices' => [commission::BASE_GROSS, commission::BASE_NET],
            ],
            'feesource' => [
                'type' => PARAM_ALPHA,
                'default' => commission::SOURCE_SITE,
                'choices' => [
                    commission::SOURCE_POLICY,
                    commission::SOURCE_COMPANY,
                    commission::SOURCE_PLAN,
                    commission::SOURCE_SITE,
                ],
            ],
            'externalid' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'refundedat' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * Vendas de uma empresa, com os dados do pagamento do core.
     *
     * @param int $companyid
     * @param int $since Timestamp minimo, 0 para tudo.
     * @return \stdClass[] Linhas com saleid, feeamount, feepercent, feebase,
     *                     feesource, externalid, offerid, amount, currency,
     *                     gateway, userid e timecreated.
     */
    public static function get_for_company(int $companyid, int $since = 0): array {
        global $DB;

        $where = 's.companyid = :companyid';
        $params = ['companyid' => $companyid];
        if ($since > 0) {
            $where .= ' AND p.timecreated >= :since';
            $params['since'] = $since;
        }

        $sql = "SELECT s.id AS saleid, s.feeamount, s.feepercent, s.feebase, s.feesource,
                       s.externalid, s.offerid, s.companyid,
                       p.id AS paymentid, p.amount, p.currency, p.gateway, p.userid, p.timecreated
                  FROM {" . self::TABLE . "} s
                  JOIN {payments} p ON p.id = s.paymentid
                 WHERE $where
              ORDER BY p.timecreated DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Compras de um aluno.
     *
     * @param int $userid
     * @return \stdClass[] Mesmas colunas de get_for_company().
     */
    public static function get_for_user(int $userid): array {
        global $DB;

        $sql = "SELECT s.id AS saleid, s.feeamount, s.feepercent, s.feebase, s.feesource,
                       s.externalid, s.offerid, s.companyid,
                       p.id AS paymentid, p.amount, p.currency, p.gateway, p.userid, p.timecreated
                  FROM {" . self::TABLE . "} s
                  JOIN {payments} p ON p.id = s.paymentid
                 WHERE p.userid = :userid
              ORDER BY p.timecreated DESC";

        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Quantas vendas concluidas existem para uma oferta.
     *
     * @param int $offerid
     * @return int
     */
    public static function count_for_offer(int $offerid): int {
        global $DB;

        return $DB->count_records(self::TABLE, ['offerid' => $offerid]);
    }
}
