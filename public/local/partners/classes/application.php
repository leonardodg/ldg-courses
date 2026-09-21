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

namespace local_partners;

use core\persistent;
use lang_string;
use local_marketplace\cnpj;
use local_marketplace\plan;

/**
 * Candidatura de empresa parceira.
 *
 * NAO e uma empresa: e um pedido. A distincao existe porque criar empresa cria
 * uma categoria de curso, que e objeto global do site - por isso a plataforma
 * nao tem auto-atendimento, e esta tabela e a fila que o administrador aprova.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application extends persistent {
    /** @var string Tabela. */
    const TABLE = 'local_partners_application';

    /** @var string E-mail ainda nao confirmado; NAO entra na fila. */
    const STATUS_UNCONFIRMED = 'unconfirmed';

    /** @var string Na fila, esperando decisao. */
    const STATUS_PENDING = 'pending';

    /** @var string Aprovada; a empresa foi provisionada. */
    const STATUS_APPROVED = 'approved';

    /** @var string Recusada. */
    const STATUS_REJECTED = 'rejected';

    /** @var string Faixa declarada: ate 100 alunos ativos por mes. */
    const BAND_UPTO_100 = 'upto100';

    /** @var string Faixa declarada: de 100 a 1.000. */
    const BAND_100_TO_1000 = '100to1000';

    /** @var string Faixa declarada: de 1.000 a 5.000. */
    const BAND_1000_TO_5000 = '1000to5000';

    /** @var string Faixa declarada: mais de 5.000. */
    const BAND_OVER_5000 = 'over5000';

    /**
     * As faixas, na ordem em que aparecem no formulario.
     *
     * Sao texto, e nao numero de ordem: um inteiro remapearia em silencio se a
     * lista mudasse, e a "faixa 2" de hoje viraria outra coisa amanha sem
     * ninguem perceber.
     *
     * @var string[]
     */
    const LEARNER_BANDS = [
        self::BAND_UPTO_100,
        self::BAND_100_TO_1000,
        self::BAND_1000_TO_5000,
        self::BAND_OVER_5000,
    ];

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'companyname' => [
                'type' => PARAM_TEXT,
            ],
            'cnpj' => [
                'type' => PARAM_ALPHANUM,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'contactname' => [
                'type' => PARAM_TEXT,
            ],
            'contactemail' => [
                'type' => PARAM_EMAIL,
            ],
            'contactphone' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'website' => [
                'type' => PARAM_URL,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'planid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'country' => [
                'type' => PARAM_ALPHA,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'learnersband' => [
                'type' => PARAM_ALPHANUM,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'termsaccepted' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'message' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'status' => [
                'type' => PARAM_ALPHA,
                'default' => self::STATUS_PENDING,
                'choices' => [
                    self::STATUS_UNCONFIRMED,
                    self::STATUS_PENDING,
                    self::STATUS_APPROVED,
                    self::STATUS_REJECTED,
                ],
            ],
            'reviewnote' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'reviewerid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'timereviewed' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'companyid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'userid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'confirmtoken' => [
                'type' => PARAM_ALPHANUM,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'timeconfirmed' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'submitterip' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }

    /**
     * O CNPJ, quando informado, precisa ser valido.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_cnpj($value) {
        if ($value === null || $value === '') {
            return true;
        }

        if (!cnpj::is_valid((string) $value)) {
            return new lang_string('errorcnpjinvalid', 'local_partners');
        }

        return true;
    }

    /**
     * O plano escolhido, quando informado, precisa existir.
     *
     * Nao ha chave estrangeira no XMLDB: ela apontaria para tabela de outro
     * plugin, e o check_database_schema reclamaria num ambiente em que a ordem
     * de desinstalacao divergisse. A integridade e garantida aqui.
     *
     * @param int|null $value
     * @return true|lang_string
     */
    protected function validate_planid($value) {
        if (empty($value)) {
            return true;
        }

        if (!plan::record_exists((int) $value)) {
            return new lang_string('errorplannotfound', 'local_partners');
        }

        return true;
    }

    /**
     * O rotulo de uma faixa, traduzido.
     *
     * A chave de idioma NAO e derivada do valor da faixa. Derivar produziria
     * 'learnersband100to1000' e 'learnersband1000to5000', que em ordem
     * alfabetica de verdade ficam ao contrario do que qualquer pessoa espera -
     * e o phpcs cobra a ordem do arquivo de idioma. Nomes por tamanho resolvem,
     * e o numero fica no texto, que e onde o leitor procura.
     *
     * @param string $band Uma das LEARNER_BANDS.
     * @return string
     */
    public static function band_label(string $band): string {
        $keys = [
            self::BAND_UPTO_100 => 'learnersbandsmall',
            self::BAND_100_TO_1000 => 'learnersbandmedium',
            self::BAND_1000_TO_5000 => 'learnersbandlarge',
            self::BAND_OVER_5000 => 'learnersbandxlarge',
        ];

        if (!isset($keys[$band])) {
            return '';
        }

        return get_string($keys[$band], 'local_partners');
    }

    /**
     * O pais, quando informado, precisa ser um que o site ofereca.
     *
     * NAO usa 'choices' na definicao da propriedade: o persistent confere a
     * lista antes de qualquer coisa e reprova o proprio nulo, porque
     * in_array(null, [...]) e falso. Campo anulavel com lista fechada valida
     * aqui, do mesmo jeito que o CNPJ e o plano.
     *
     * A lista e a do Moodle, e sem o parametro de "todos": se o administrador
     * restringiu os paises do site, o formulario oferece menos e a validacao
     * precisa concordar com o formulario.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_country($value) {
        if ($value === null || $value === '') {
            return true;
        }

        if (!array_key_exists((string) $value, get_string_manager()->get_list_of_countries())) {
            return new lang_string('errorcountryinvalid', 'local_partners');
        }

        return true;
    }

    /**
     * A faixa de alunos, quando informada, precisa ser uma das quatro.
     *
     * Mesma razao do validate_country para nao usar 'choices'.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_learnersband($value) {
        if ($value === null || $value === '') {
            return true;
        }

        if (!in_array((string) $value, self::LEARNER_BANDS, true)) {
            return new lang_string('errorlearnersbandinvalid', 'local_partners');
        }

        return true;
    }

    /**
     * Candidaturas na fila, das mais antigas para as mais novas.
     *
     * Ordem de chegada, e nao a mais recente primeiro: a fila e para ser
     * esvaziada, e quem esta esperando ha mais tempo vem antes.
     *
     * @return application[]
     */
    public static function get_pending(): array {
        return self::get_records(['status' => self::STATUS_PENDING], 'timecreated');
    }

    /**
     * Ja existe candidatura em aberto para este contato ou CNPJ?
     *
     * Evita que a mesma empresa entre tres vezes na fila porque quem enviou nao
     * viu a tela de confirmacao.
     *
     * @param string $email
     * @param string|null $cnpj So digitos, ou nulo.
     * @return bool
     */
    public static function has_pending_for(string $email, ?string $cnpj = null): bool {
        global $DB;

        // So a candidatura CONFIRMADA bloqueia. A nao confirmada nao pode
        // bloquear: quem nao recebeu o e-mail precisa poder tentar de novo, e
        // um envio que nunca foi confirmado trancaria a pessoa para sempre.
        // O reenvio substitui a anterior - ver api::submit().
        $params = ['status' => self::STATUS_PENDING, 'contactemail' => $email];

        if ($DB->record_exists(self::TABLE, $params)) {
            return true;
        }

        if (!empty($cnpj)) {
            return $DB->record_exists(self::TABLE, [
                'status' => self::STATUS_PENDING,
                'cnpj' => $cnpj,
            ]);
        }

        return false;
    }

    /**
     * Busca uma candidatura pelo token de confirmacao.
     *
     * @param string $token
     * @return application|false
     */
    public static function get_by_token(string $token) {
        if ($token === '') {
            return false;
        }

        return self::get_record([
            'confirmtoken' => $token,
            'status' => self::STATUS_UNCONFIRMED,
        ]);
    }

    /**
     * Apaga candidaturas nao confirmadas do mesmo e-mail.
     *
     * Chamado antes de gravar um envio novo: quem reenvia porque nao recebeu o
     * link nao pode acumular linhas mortas nem levar um erro de duplicidade.
     *
     * @param string $email
     * @return void
     */
    public static function purge_unconfirmed_for(string $email): void {
        global $DB;

        $DB->delete_records(self::TABLE, [
            'status' => self::STATUS_UNCONFIRMED,
            'contactemail' => $email,
        ]);
    }

    /**
     * Quantas candidaturas vieram deste IP na ultima hora.
     *
     * @param string $ip
     * @return int
     */
    public static function count_recent_from_ip(string $ip): int {
        global $DB;

        return $DB->count_records_select(
            self::TABLE,
            'submitterip = :ip AND timecreated > :since',
            ['ip' => $ip, 'since' => time() - HOURSECS]
        );
    }

    /**
     * Quantas candidaturas este e-mail recebeu na ultima hora.
     *
     * O limite por IP sozinho nao protege quem nunca se candidatou: rotacionar
     * IPv6 e trivial em rede movel/VPS, mas o alvo do abuso continua sendo o
     * MESMO endereco - cada submissao nao confirmada manda um e-mail real de
     * confirmacao para quem nunca pediu nada (email-bombing de terceiro).
     *
     * @param string $email
     * @return int
     */
    public static function count_recent_from_email(string $email): int {
        global $DB;

        return $DB->count_records_select(
            self::TABLE,
            'contactemail = :email AND timecreated > :since',
            ['email' => $email, 'since' => time() - HOURSECS]
        );
    }
}
