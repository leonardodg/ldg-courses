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

namespace local_marketplace\task;

use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;

/**
 * Avisa o aluno que o acesso esta para vencer.
 *
 * Existe porque o Mercado Pago nao cobra sozinho com split: nao ha debito
 * automatico a disparar, entao o aviso E o mecanismo de renovacao. Sem ele o
 * aluno so descobre que venceu quando tenta entrar no curso - e a essa altura
 * ja perdeu aula, e a chance de ele culpar a plataforma e alta.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_expiring extends \core\task\scheduled_task {
    /**
     * Janela em que a renovacao e oferecida, em dias.
     *
     * Nao e so desta tarefa: offers.php, mysubscriptions.php e o
     * block_marketplace leem daqui para decidir quando mostrar o botao de
     * renovar. Precisa continuar sendo um inteiro simples por isso.
     *
     * @var int
     */
    const NOTICE_DAYS = 5;

    /**
     * Marcos de aviso, em dias antes do vencimento.
     *
     * Dois, e nao um: o primeiro lembra, o ultimo avisa que o acesso vai
     * parar. Um aviso so, cinco dias antes, chega e se perde na caixa de
     * entrada de quem ia renovar mesmo.
     *
     * O maior tem que casar com NOTICE_DAYS - avisar fora da janela em que o
     * botao de renovar existe mandaria o aluno para uma vitrine sem botao.
     * Ha teste fixando isso, porque sao duas constantes e elas podem divergir.
     *
     * @var int[]
     */
    const NOTICE_MILESTONES = [5, 1];

    /** @var string Preferencia que guarda o ultimo aviso enviado. */
    const PREF_PREFIX = 'local_marketplace_notified_';

    /**
     * Nome exibido na lista de tarefas.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifyexpiring', 'local_marketplace');
    }

    /**
     * Executa.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();
        $limit = $now + (self::NOTICE_DAYS * DAYSECS);

        // Só direitos com data marcada: vitalicio nao vence, e quem ja venceu
        // nao adianta avisar - viraria "seu acesso vai vencer" depois do fato.
        // norenew de fora: quem cancelou pediu para nao ser mais cobrado.
        // Insistir seria transformar o aviso em spam de quem ja disse nao.
        $records = $DB->get_records_select(
            'local_marketplace_entitlement',
            'status = :status AND norenew = 0 AND timeend > :now AND timeend <= :limit',
            ['status' => entitlement::STATUS_ACTIVE, 'now' => $now, 'limit' => $limit]
        );

        $sent = 0;
        foreach ($records as $record) {
            if (self::send_notice($record)) {
                $sent++;
            }
        }

        mtrace("local_marketplace: {$sent} aviso(s) de vencimento enviado(s).");
    }

    /**
     * Manda um aviso, se ainda nao foi mandado para este vencimento.
     *
     * PUBLICA E ESTATICA porque o gerente da empresa reenvia a mesma mensagem
     * pela tela de assinantes. Duplicar a montagem daria dois textos que
     * divergem na primeira edicao feita so num deles - e o do gerente e
     * justamente o que ele manda quando o aluno diz "nao recebi".
     *
     * @param \stdClass $record
     * @param bool $force Ignora a deduplicacao. So para reenvio manual: no cron
     *                    ela e o que impede o mesmo e-mail sair de hora em hora.
     * @return bool
     */
    public static function send_notice(\stdClass $record, bool $force = false): bool {
        $user = \core_user::get_user((int) $record->userid, '*', IGNORE_MISSING);
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }

        $milestone = self::milestone_for((int) $record->timeend, time());

        // No reenvio manual o marco pode nem existir - o gerente reenvia
        // quando o aluno pede, e nao quando o calendario manda. Cai no texto
        // brando, que e o certo para quem ainda nao esta na ultima semana.
        if (!$milestone && !$force) {
            return false;
        }

        // O valor guardado inclui o vencimento E o marco.
        //
        // O vencimento sozinho nao basta desde que passaram a existir dois
        // avisos: gravando so ele, o de cinco dias marcaria a linha e o de um
        // dia NUNCA sairia - sem erro, sem log, sem nada. Foi por isso que
        // esta linha mudou junto com os marcos, e nao depois.
        //
        // O vencimento continua na chave porque a renovacao o muda, e e o que
        // libera os dois avisos de novo no ciclo seguinte.
        $key = self::PREF_PREFIX . (int) $record->id;
        $marca = (int) $record->timeend . ':' . $milestone;
        if (!$force && get_user_preferences($key, '', $user) === $marca) {
            return false;
        }

        $offer = offer::get_record(['id' => (int) $record->offerid]);
        $company = company::get_record(['id' => (int) $record->companyid]);
        if (!$offer || !$company) {
            return false;
        }

        // Oferta fora de venda nao tem como ser renovada. Avisar levaria o
        // aluno a uma vitrine onde o botao nao existe.
        if ($offer->get('status') !== offer::STATUS_PUBLISHED) {
            return false;
        }

        // Assinatura que atingiu o limite de ciclos tambem nao renova.
        if (!$offer->accepts_cycle((int) $record->cycles)) {
            return false;
        }

        $renewurl = new \moodle_url('/local/marketplace/offers.php', [
            'company' => $company->get('shortname'),
            'highlight' => $offer->get('id'),
        ]);

        // A FATURA, quando ela ja existe, vale mais que a vitrine.
        //
        // Numa assinatura o gateway ja gerou a cobranca do ciclo seguinte - com
        // boleto ela nasce ate com linha digitavel. Mandar o aluno para a
        // vitrine e pedir que ele COMPRE de novo algo que ja esta cobrado.
        //
        // Ausencia nao e erro: sem assinatura, ou com o gateway fora do ar, o
        // aviso continua saindo com o caminho antigo.
        $invoice = \local_marketplace\api::pending_invoice_for(
            'local_marketplace',
            (int) $record->offerid,
            (int) $record->userid
        );

        $a = (object) [
            'offer' => format_string($offer->get('name')),
            'company' => format_string($company->get('name')),
            'date' => userdate((int) $record->timeend, get_string('strftimedaydate')),
            'days' => max(1, (int) ceil(((int) $record->timeend - time()) / DAYSECS)),
            'url' => $invoice ? $invoice['url'] : $renewurl->out(false),
            // Linha digitavel so existe em boleto. Vazia, o texto nao a menciona.
            'line' => $invoice ? (string) $invoice['line'] : '',
        ];

        // O ultimo marco fala em bloqueio, e nao em vencimento. Repetir o mesmo
        // texto duas vezes ensinaria o aluno a ignorar os dois.
        $last = $milestone === min(self::NOTICE_MILESTONES);
        $prefix = $last ? 'expiringlast' : 'expiring';

        $message = new \core\message\message();
        $message->component = 'local_marketplace';
        $message->name = 'expiring';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string($prefix . 'subject', 'local_marketplace', $a);
        $body = get_string($prefix . 'body', 'local_marketplace', $a);
        $bodyhtml = get_string($prefix . 'bodyhtml', 'local_marketplace', $a);
        if ($a->line !== '') {
            $body .= "\n\n" . get_string('expiringline', 'local_marketplace', $a->line);
            $bodyhtml .= \html_writer::tag(
                'p',
                get_string('expiringline', 'local_marketplace', \html_writer::tag('code', $a->line))
            );
        }

        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = get_string($prefix . 'subject', 'local_marketplace', $a);
        $message->notification = 1;
        $message->contexturl = $a->url;
        $message->contexturlname = get_string('renewnow', 'local_marketplace');

        if (message_send($message)) {
            // O reenvio manual NAO marca a preferencia: se marcasse, o gerente
            // reenviando hoje calaria o aviso automatico de amanha.
            if (!$force) {
                set_user_preference($key, $marca, $user);
            }
            return true;
        }

        return false;
    }

    /**
     * Qual marco de aviso se aplica a este vencimento.
     *
     * Devolve o marco MAIS APERTADO que ainda cobre o tempo restante: faltando
     * tres dias, o marco e o de cinco; faltando algumas horas, e o de um. Sem
     * isso, o aviso final nunca chegaria - o de cinco dias continuaria valendo
     * ate o fim e marcaria a linha como ja avisada.
     *
     * Independe da ordem em que os marcos foram declarados.
     *
     * @param int $timeend Vencimento do direito
     * @param int $now Momento de referencia
     * @return int Marco em dias, ou 0 quando esta fora de todas as janelas
     */
    public static function milestone_for(int $timeend, int $now): int {
        $restante = $timeend - $now;
        if ($restante <= 0) {
            // Ja venceu: "seu acesso vai vencer" depois do fato so confunde.
            return 0;
        }

        $cabem = array_filter(
            self::NOTICE_MILESTONES,
            static fn(int $dias): bool => $restante <= $dias * DAYSECS
        );

        return $cabem ? min($cabem) : 0;
    }
}
