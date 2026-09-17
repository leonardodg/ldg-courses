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

/**
 * Tarefas agendadas.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Diaria basta: o token vale ~6 meses e renovamos com 15 dias de
        // antecedencia. Rodar de hora em hora so gastaria chamada a API.
        'classname' => 'paygw_mercadopago\task\refresh_tokens',
        'blocking' => 0,
        'minute' => '23',
        'hour' => '4',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        // De hora em hora, e nao diaria: aqui o que esta em jogo e o aluno que
        // pagou e nao recebeu o curso porque o webhook se perdeu. Esperar ate
        // a madrugada seria esperar demais.
        'classname' => 'paygw_mercadopago\task\reconcile',
        'blocking' => 0,
        'minute' => '41',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        // Uma vez por dia, de madrugada, e a escolha merece razao.
        //
        // O intervalo de cobranca e contado em DIAS, entao rodar de hora em
        // hora nao antecipa ciclo nenhum - so multiplicaria por 24 a chance de
        // duas execucoes se cruzarem na mesma assinatura. E a madrugada porque
        // cobranca recusada gera e-mail, e e-mail de cobranca as tres da tarde
        // no meio do expediente do vendedor nao ajuda ninguem.
        //
        // CRON PARADO E ASSINATURA QUE NAO COBRA: aqui quem dispara somos nos,
        // ao contrario do Asaas. Vale monitorar esta tarefa como se monitora
        // dinheiro, e nao como se monitora limpeza de cache.
        'classname' => 'paygw_mercadopago\task\charge_due_cycles',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '5',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        // Antes de charge_due_cycles, na mesma madrugada: quem vai precisar
        // agir (Pix, boleto) fica sabendo antes de quem so vai ver a
        // cobranca automatica no cartao acontecer sozinha.
        'classname' => 'paygw_mercadopago\task\remind_upcoming_cycles',
        'blocking' => 0,
        'minute' => '5',
        'hour' => '5',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
