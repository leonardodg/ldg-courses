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

namespace paygw_mercadopago;

/**
 * Confere a assinatura das notificacoes do Mercado Pago.
 *
 * O QUE ELA ACRESCENTA, porque o endpoint ja nao era ingenuo: o status NUNCA
 * veio do corpo da notificacao - ele e consultado de volta na API com o token
 * do vendedor. Isso ja impedia que alguem POSTasse "aprovado" e ganhasse acesso
 * de graca, e continua sendo a defesa principal.
 *
 * O que faltava era a outra metade: impedir que alguem faca o NOSSO servidor
 * consultar ids arbitrarios na API do Mercado Pago, um por requisicao.
 *
 * O manifesto tem ordem e pontuacao fixas, e nao ha liberdade nenhuma aqui:
 *
 *     id:<data.id>;request-id:<x-request-id>;ts:<ts>;
 *
 * Qualquer variacao - um espaco, um ponto e virgula a menos, a ordem trocada -
 * muda o HMAC e faz a notificacao LEGITIMA ser recusada. O sintoma disso e
 * venda que nao entrega, entao o formato esta escrito aqui e tem teste.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_signature {
    /**
     * A notificacao veio mesmo do Mercado Pago?
     *
     * @param string $header Conteudo do cabecalho x-signature
     * @param string $requestid Conteudo do cabecalho x-request-id
     * @param string $dataid O data.id da notificacao
     * @param string $secret Assinatura secreta DAQUELA aplicacao
     * @return bool
     */
    public static function is_valid(
        string $header,
        string $requestid,
        string $dataid,
        string $secret
    ): bool {
        // SEM SEGREDO CONFIGURADO A VALIDACAO E PULADA, e a escolha e
        // deliberada. Recusar tudo faria a atualizacao do plugin derrubar o
        // webhook de quem ja estava no ar - e o sintoma seria aluno pagando e
        // nao recebendo acesso, o pior desfecho possivel aqui.
        //
        // O que resta nesse caso nao e pouco: o status continua vindo de uma
        // consulta a API, e nao do corpo.
        if ($secret === '') {
            return true;
        }

        [$ts, $v1] = self::parse($header);

        if ($ts === '' || $v1 === '') {
            return false;
        }

        $manifest = 'id:' . $dataid . ';request-id:' . $requestid . ';ts:' . $ts . ';';

        // Comparacao com hash_equals, e nao com ===: igualdade de string sai no
        // primeiro byte diferente, e o tempo de resposta entrega quantos bytes
        // o atacante acertou.
        return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
    }

    /**
     * Separa ts e v1 do cabecalho.
     *
     * O formato e "ts=<numero>,v1=<hex>", e a ordem das partes nao e garantida
     * - por isso se le por nome, e nao por posicao.
     *
     * @param string $header
     * @return array [ts, v1], vazios quando o cabecalho nao serve
     */
    protected static function parse(string $header): array {
        $ts = $v1 = '';

        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', trim($part), 2);
            if (count($pieces) !== 2) {
                continue;
            }

            [$key, $value] = $pieces;
            $value = trim($value);

            if (trim($key) === 'ts') {
                $ts = $value;
            } else if (trim($key) === 'v1') {
                $v1 = $value;
            }
        }

        return [$ts, $v1];
    }
}
