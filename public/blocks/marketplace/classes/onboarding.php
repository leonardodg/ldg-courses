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

namespace block_marketplace;

use local_marketplace\company;

/**
 * Estado do checklist de ativacao de uma empresa, TODO derivado dos campos
 * que ja existem em local_marketplace - nenhuma tabela nova.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onboarding {
    /** @var string */
    public const STEP_GATEWAY = 'gateway';
    /** @var string */
    public const STEP_PLAN = 'plan';
    /** @var string */
    public const STEP_DOCUMENT = 'document';

    /** @var string */
    public const STATE_DONE = 'done';
    /** @var string */
    public const STATE_PENDING = 'pending';
    /** @var string */
    public const STATE_OPTIONAL = 'optional';

    /**
     * Estado de cada etapa, derivado dos campos existentes.
     *
     * @param company $company
     * @return array<string, string> etapa => estado
     */
    public static function step_state(company $company): array {
        return [
            self::STEP_GATEWAY => self::gateway_state($company),
            self::STEP_PLAN => $company->get_plan() !== null ? self::STATE_DONE : self::STATE_PENDING,
            self::STEP_DOCUMENT => $company->get('cnpj') !== null ? self::STATE_DONE : self::STATE_OPTIONAL,
        ];
    }

    /**
     * Reusa company::can_sell(), e nao reimplementa o loop de contas.
     *
     * can_sell() ja junta as duas condicoes que importam aqui: a conta tem que
     * estar disponivel (account::is_available() - vinculada nao basta, precisa
     * do gateway habilitado, armadilha ja documentada no CLAUDE.md) E a
     * empresa tem que estar STATUS_ACTIVE. Uma copia manual do loop ja
     * esqueceu o segundo check uma vez: empresa suspensa com gateway
     * habilitado antes de suspender aparecia como etapa concluida, quando
     * can_sell() - a resposta canonica de "esta empresa pode vender" - diria
     * que nao.
     *
     * @param company $company
     * @return string
     */
    private static function gateway_state(company $company): string {
        return $company->can_sell() ? self::STATE_DONE : self::STATE_PENDING;
    }

    /**
     * Progresso agregado, contando so as etapas obrigatorias (documento e opcional).
     *
     * @param company $company
     * @return array{done: int, total: int, percent: int, complete: bool}
     */
    public static function progress(company $company): array {
        $states = self::step_state($company);
        $required = array_filter($states, fn ($state) => $state !== self::STATE_OPTIONAL);
        $done = count(array_filter($required, fn ($state) => $state === self::STATE_DONE));
        $total = count($required);

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 100,
            'complete' => $done === $total,
        ];
    }
}
