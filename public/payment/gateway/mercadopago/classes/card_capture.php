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
 * Onde o aluno digita o cartao - e o que cada escolha custa.
 *
 * A pagina de pagamento e NOSSA nos tres modos, com o nosso layout e a nossa
 * marca. O que muda e o caminho que o numero do cartao percorre, e isso decide
 * o enquadramento do projeto no PCI DSS:
 *
 *   BRICK  - os campos sao iframes do Mercado Pago dentro da nossa pagina. O
 *            numero nao entra no nosso DOM nem no nosso servidor.  SAQ A.
 *
 *   DIRECT - o formulario e nosso, HTML e CSS nossos, e o JavaScript manda o
 *            cartao DIRETO para o Mercado Pago. O numero passa pelo nosso
 *            DOM, nunca pelo nosso backend.  SAQ A-EP.
 *
 *   NATIVE - o formulario e nosso e o numero vai ao Mercado Pago PELO NOSSO
 *            BACKEND. Tecnicamente funciona: medido em 16/09/2026,
 *            POST /v1/card_tokens?public_key=... tokeniza a partir do
 *            servidor.  SAQ D.
 *
 * A ORDEM DE MODES E A DA EXPOSICAO CRESCENTE, e a tela apresenta nessa ordem:
 * quem desce a lista esta escolhendo mais risco a cada linha.
 *
 * O QUE O CODIGO GARANTE, E O QUE NAO GARANTE. Metade do custo nao cabe num
 * plugin: varredura ASV trimestral, teste de intrusao anual, politicas formais
 * e a atestacao entregue ao adquirente sao processo da empresa, e nenhuma linha
 * daqui os substitui. O detalhamento esta em
 * docs/legal/pci-dss-captura-de-cartao.md.
 *
 * A PROPRIEDADE QUE TORNA A ESCOLHA ACEITAVEL e que os modos expostos nao
 * dependem de o administrador acertar a configuracao. Sem HTTPS eles nao valem,
 * e o codigo cai no BRICK sozinho - porque "seguro desde que a configuracao
 * esteja certa" e o desenho que este projeto recusa.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_capture {
    /** @var string Campos do Mercado Pago na nossa pagina. O PAN nao nos toca. */
    const MODE_BRICK = 'brick';

    /** @var string Formulario nosso; o JS manda direto ao MP, sem passar pelo backend. */
    const MODE_DIRECT = 'direct';

    /** @var string Formulario nosso; o PAN transita pelo nosso backend. */
    const MODE_NATIVE = 'native';

    /** @var string[] Os modos, em ordem de exposicao crescente. */
    const MODES = [self::MODE_BRICK, self::MODE_DIRECT, self::MODE_NATIVE];

    /**
     * Em que enquadramento do PCI DSS cada modo coloca o projeto.
     *
     * Vive no codigo, e nao so na documentacao, porque e o que a tela mostra ao
     * lado de cada opcao. Documentacao que o administrador nao le na hora de
     * escolher nao protege ninguem.
     *
     * @var array<string,string>
     */
    const SCOPES = [
        self::MODE_BRICK => 'A',
        self::MODE_DIRECT => 'A-EP',
        self::MODE_NATIVE => 'D',
    ];

    /**
     * O modo que vale AGORA, nesta requisicao.
     *
     * Nao devolve o que esta configurado: devolve o que e seguro executar. A
     * diferenca aparece sem HTTPS, e e deliberada - uma escolha feita por
     * engano mandaria numero de cartao por uma pagina que qualquer um no
     * caminho reescreve, e o sintoma seria invisivel ate o vazamento.
     *
     * CADA EMPRESA PODE ESCOLHER O PROPRIO MODO, e a config do site continua
     * valendo como padrao para quem nao escolheu - contas que existiam antes
     * desta opcao nao mudam de comportamento sozinhas.
     *
     * @param int $accountid Conta de pagamento da empresa, 0 para so o site
     * @return string Um dos MODES
     */
    public static function current(int $accountid = 0): string {
        $configurado = self::configured_mode($accountid);

        // Inclui o valor vazio e o desconhecido: qualquer duvida cai no modo
        // que nao toca no cartao.
        if (!in_array($configurado, self::MODES, true)) {
            return self::MODE_BRICK;
        }

        return self::mode_is_allowed($configurado) ? $configurado : self::MODE_BRICK;
    }

    /**
     * O modo configurado, na conta ou no site - antes de qualquer guarda de
     * HTTPS.
     *
     * @param int $accountid
     * @return string
     */
    protected static function configured_mode(int $accountid): string {
        if ($accountid > 0) {
            $daconta = (string) (self::account_config($accountid)['cardcapture'] ?? '');
            if ($daconta !== '') {
                return $daconta;
            }
        }

        return (string) get_config('paygw_mercadopago', 'cardcapture');
    }

    /**
     * A configuracao desta CONTA de pagamento (empresa), ou vazia quando a
     * conta nao existe ou nao esta vinculada ainda.
     *
     * Vazio e nao excecao de proposito: card_capture precisa de uma resposta
     * mesmo antes de a conta estar configurada - e nesse caso o padrao do
     * site e a resposta certa, nao um erro na tela.
     *
     * @param int $accountid
     * @return array
     */
    protected static function account_config(int $accountid): array {
        return gateway::account_configuration($accountid);
    }

    /**
     * As opcoes do modo de captura, para um <select> - usada no site
     * (settings.php) e por conta (gateway.php), para a lista nao poder
     * divergir entre as duas telas.
     *
     * @param bool $comopcaopadrao Inclui uma opcao vazia = "usar o padrao do site"
     * @return array<string,string>
     */
    public static function form_options(bool $comopcaopadrao = false): array {
        $opcoes = $comopcaopadrao ? ['' => get_string('cardcaptureusesite', 'paygw_mercadopago')] : [];

        foreach (self::MODES as $modo) {
            $opcoes[$modo] = get_string('cardcapture' . $modo, 'paygw_mercadopago', (object) [
                'scope' => self::scope_of($modo),
            ]);
        }

        return $opcoes;
    }

    /**
     * O enquadramento PCI de um modo.
     *
     * @param string $mode
     * @return string A, A-EP ou D
     */
    public static function scope_of(string $mode): string {
        return self::SCOPES[$mode] ?? self::SCOPES[self::MODE_BRICK];
    }

    /**
     * Este modo exige que o site seja servido por HTTPS?
     *
     * O brick dispensa porque e o unico que nao expoe nada: os campos sao
     * iframes servidos pelo proprio Mercado Pago, por TLS dele, e o numero
     * nunca esta na nossa pagina. Os outros dois colocam o cartao no nosso DOM,
     * e sem TLS qualquer um no caminho reescreve aquele JavaScript e passa a
     * receber o cartao antes do Mercado Pago. No modo direto isso e importante
     * de entender: o PAN nao passar pelo nosso backend NAO protege de nada se a
     * pagina que o coleta pode ser adulterada em transito.
     *
     * @param string $mode
     * @return bool
     */
    public static function requires_https(string $mode): bool {
        return $mode !== self::MODE_BRICK;
    }

    /**
     * Este modo pode ser executado neste site?
     *
     * Existe separada de current() para que a TELA consiga dizer por que a
     * escolha do administrador nao esta valendo. Recusar em silencio seria
     * trocar um risco por uma confusao.
     *
     * @param string $mode
     * @return bool
     */
    public static function mode_is_allowed(string $mode): bool {
        global $CFG;

        if (!self::requires_https($mode)) {
            return true;
        }

        require_once($CFG->libdir . '/weblib.php');

        return is_https();
    }

    /**
     * O modo escolhido esta sendo ignorado?
     *
     * @param int $accountid Conta de pagamento da empresa, 0 para so o site
     * @return bool
     */
    public static function is_blocked(int $accountid = 0): bool {
        $configurado = self::configured_mode($accountid);

        return in_array($configurado, self::MODES, true) && !self::mode_is_allowed($configurado);
    }
}
