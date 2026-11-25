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

namespace enrol_marketplace\task;

use core\task\scheduled_task;
use local_marketplace\entitlement;

/**
 * Marca direitos vencidos e ressincroniza as matriculas afetadas.
 *
 * @package    enrol_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_entitlements extends scheduled_task {
    /**
     * Nome exibido no admin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncentitlements', 'enrol_marketplace');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $plugin = enrol_get_plugin('marketplace');
        if (!$plugin) {
            mtrace('enrol_marketplace desabilitado; nada a fazer.');
            return;
        }

        // 1. Direitos que venceram desde a ultima execucao.
        //
        // is_active() ja trata o vencimento na leitura, entao o acesso nao
        // depende desta task. Marcar o status aqui e para o relatorio e para
        // a suspensao da matricula, que precisa de um evento para acontecer.
        $expired = $DB->get_records_select(
            'local_marketplace_entitlement',
            'status = :status AND timeend > 0 AND timeend <= :now',
            ['status' => entitlement::STATUS_ACTIVE, 'now' => time()]
        );

        $affected = [];
        foreach ($expired as $record) {
            // Carrega FRESCO pelo id, e nao pelo snapshot puxado no SELECT
            // acima: um webhook pode ter renovado este MESMO direito
            // enquanto a tarefa estava no meio do loop, e o snapshot antigo
            // sobrescreveria a renovacao recem-paga com o timeend/status de
            // antes. Reconfere a condicao de vencimento contra o dado atual
            // antes de expirar - se ja nao vence mais, alguem ja cuidou disto.
            $ent = new entitlement((int) $record->id);
            if (
                $ent->get('status') !== entitlement::STATUS_ACTIVE
                || (int) $ent->get('timeend') <= 0
                || (int) $ent->get('timeend') > time()
            ) {
                continue;
            }
            $ent->set('status', entitlement::STATUS_EXPIRED);
            $ent->update();
            $affected[(int) $ent->get('userid')] = true;
        }
        mtrace('Direitos vencidos: ' . count($expired));

        // 2. Ressincroniza quem foi afetado.
        //
        // So os afetados, e nao a base inteira: varrer todos os usuarios a
        // cada hora seria caro e nao mudaria nada para quem nada mudou.
        $totals = [0, 0, 0];
        foreach (array_keys($affected) as $userid) {
            $result = $plugin->sync_user((int) $userid);
            foreach ($result as $i => $count) {
                $totals[$i] += $count;
            }
        }

        mtrace(sprintf(
            'Sincronizados %d usuarios: %d matriculas, %d suspensas, %d reativadas.',
            count($affected),
            $totals[0],
            $totals[1],
            $totals[2]
        ));
    }
}
