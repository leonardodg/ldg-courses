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

namespace paygw_mercadopago\task;

use core\task\scheduled_task;
use paygw_mercadopago\application;
use paygw_mercadopago\mp_client;

/**
 * Renova os tokens dos vendedores antes de vencerem.
 *
 * O token do Mercado Pago vale cerca de seis meses. Sem renovacao o repasse
 * daquele vendedor simplesmente para - e o sintoma aparece no checkout, diante
 * do aluno, meses depois de alguem ter configurado tudo corretamente.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_tokens extends scheduled_task {
    /** @var int Antecedencia da renovacao, em dias. */
    const RENEW_BEFORE_DAYS = 15;

    /**
     * Nome exibido no admin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskrefreshtokens', 'paygw_mercadopago');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $types = application::configured_types();
        if (!$types) {
            mtrace('Nenhuma aplicacao da plataforma configurada; nada a renovar.');
            return;
        }

        $limit = time() + (self::RENEW_BEFORE_DAYS * DAYSECS);
        $renewed = $failures = 0;

        foreach ($DB->get_records('payment_gateways', ['gateway' => 'mercadopago']) as $gw) {
            $originalconfig = $gw->config;
            $gwconfig = @json_decode($gw->config, true);
            if (!is_array($gwconfig)) {
                continue;
            }

            // Cada aplicacao tem o proprio token e o proprio vencimento, e eles
            // nao andam juntos: um vendedor pode ter autorizado Preferencias em
            // marco e Bricks em setembro. Renovar so o primeiro que vencer
            // deixaria o outro morrer calado.
            $changed = false;

            foreach ($types as $type) {
                $refresh = (string) ($gwconfig[application::token_field($type, 'refreshtoken')] ?? '');
                if ($refresh === '') {
                    continue;
                }

                $expires = (int) ($gwconfig[application::token_field($type, 'tokenexpires')] ?? 0);
                if ($expires === 0 || $expires > $limit) {
                    continue;
                }

                $credentials = application::credentials($type);
                if (!$credentials) {
                    continue;
                }

                try {
                    $token = mp_client::refresh_token(
                        $credentials->clientid,
                        $credentials->clientsecret,
                        $refresh
                    );

                    $newexpires = time() + (int) ($token['expires_in'] ?? 0);
                    $gwconfig[application::token_field($type, 'accesstoken')] =
                        (string) ($token['access_token'] ?? '');
                    // O Mercado Pago pode devolver um refresh_token novo. Manter
                    // o antigo nesse caso quebraria a renovacao seguinte.
                    if (!empty($token['refresh_token'])) {
                        $gwconfig[application::token_field($type, 'refreshtoken')] =
                            (string) $token['refresh_token'];
                    }
                    $gwconfig[application::token_field($type, 'tokenexpires')] = $newexpires;

                    $changed = true;
                    $renewed++;
                    mtrace("Conta {$gw->accountid} [$type]: token renovado ate " . userdate($newexpires));
                } catch (\Throwable $e) {
                    // Uma falha nao pode interromper as outras contas nem as
                    // outras aplicacoes: se o vendedor revogou a autorizacao no
                    // painel do Mercado Pago, aquele vinculo nunca mais renova,
                    // e os demais seguem validos.
                    $failures++;
                    mtrace("Conta {$gw->accountid} [$type]: FALHA ao renovar - " . $e->getMessage());
                }
            }

            // Uma gravacao por conta, e nao uma por aplicacao: sao ate tres
            // renovacoes na mesma linha, e gravar a cada uma reescreveria o
            // mesmo registro tres vezes.
            //
            // Lock otimista: a escrita so vale se o config ainda for o MESMO
            // que foi lido no topo do loop. Sem isto, um vendedor usando
            // oauth_unlink.php enquanto esta tarefa esta no meio do loop tinha
            // a revogacao ressuscitada pela sobrescrita incondicional desta
            // linha - ou o inverso, um relink recem-feito apagado.
            if ($changed) {
                $DB->set_field_select(
                    'payment_gateways',
                    'config',
                    json_encode($gwconfig),
                    'id = :id AND config = :original',
                    ['id' => $gw->id, 'original' => $originalconfig]
                );
            }
        }

        mtrace("Renovados: $renewed. Falhas: $failures.");

        if ($failures > 0) {
            // Falha aqui e silenciosa por natureza: so apareceria numa venda.
            // Deixar registrado no log da task e o minimo; a Fase 4 acrescenta
            // aviso ao vendedor.
            mtrace('ATENCAO: contas com falha nao conseguirao receber pagamento.');
        }
    }
}
