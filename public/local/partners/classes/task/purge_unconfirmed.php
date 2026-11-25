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

namespace local_partners\task;

use core\task\scheduled_task;
use local_partners\application;

/**
 * Apaga candidaturas cujo e-mail nunca foi confirmado.
 *
 * O formulario e publico e anonimo, entao qualquer um enche esta tabela de nome,
 * e-mail, telefone e IP sem provar nada. Sem prazo de validade, o que sobra e um
 * deposito de dado pessoal de gente que talvez nem exista, guardado para sempre
 * e sem finalidade - o oposto do que a LGPD pede.
 *
 * Apaga so o que esta em UNCONFIRMED: assim que a pessoa clica no link, a
 * candidatura vira PENDING e passa a ser um pedido de verdade, que so o
 * administrador encerra.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_unconfirmed extends scheduled_task {
    /** @var int Prazo padrao, em dias, antes de a nao confirmada ser apagada. */
    public const DEFAULT_DAYS = 7;

    /**
     * Nome exibido na tela de tarefas.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskpurgeunconfirmed', 'local_partners');
    }

    /**
     * Executa a limpeza.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $configured = get_config('local_partners', 'unconfirmedretentiondays');

        // Nao configurado cai no padrao; zero desliga a limpeza. Sao coisas
        // diferentes: guardar tudo tem que ser escolha de alguem, e nao efeito
        // colateral de uma configuracao que ninguem preencheu.
        $days = ($configured === false || $configured === '') ? self::DEFAULT_DAYS : (int) $configured;

        if ($days <= 0) {
            mtrace('local_partners: limpeza de nao confirmadas desligada.');
            return;
        }

        $cutoff = time() - ($days * DAYSECS);

        $count = $DB->count_records_select(
            application::TABLE,
            'status = :status AND timecreated < :cutoff',
            ['status' => application::STATUS_UNCONFIRMED, 'cutoff' => $cutoff]
        );

        if (!$count) {
            mtrace('local_partners: nenhuma candidatura nao confirmada para apagar.');
            return;
        }

        $DB->delete_records_select(
            application::TABLE,
            'status = :status AND timecreated < :cutoff',
            ['status' => application::STATUS_UNCONFIRMED, 'cutoff' => $cutoff]
        );

        mtrace("local_partners: {$count} candidatura(s) nao confirmada(s) apagada(s).");
    }
}
