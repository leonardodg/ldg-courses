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

use coding_exception;
use stdClass;

/**
 * As aplicacoes da plataforma no Mercado Pago, uma por tipo de integracao.
 *
 * No Mercado Pago "aplicacao" nao e um detalhe de cadastro: o MODELO declarado
 * no painel muda o comportamento da API em silencio. Declarar Checkout
 * Transparente faz o marketplace_fee da preferencia ser ignorado sem erro - e
 * silencio aqui significa venda sem comissao que nao acusa nada.
 *
 * Por isso a credencial deixou de ser unica. Cada tipo tem client_id e
 * client_secret proprios, e cada um exige o SEU fluxo de OAuth: o token que um
 * vendedor emite autorizando a aplicacao de Preferencias nao carrega a
 * aplicacao de Bricks.
 *
 * O CONJUNTO E FECHADO de proposito. Uma lista aberta, alimentada por
 * configuracao, fingiria uma generalidade que nao existe: o codigo ramifica por
 * tipo, porque cada produto do Mercado Pago tem endpoint e campo de comissao
 * diferentes. Tipo novo aqui e mudanca de codigo, e tem que ser.
 *
 * COMPATIBILIDADE, E E O PONTO MAIS DELICADO DESTE ARQUIVO: o tipo
 * 'preferences' le os nomes de configuracao ANTIGOS - clientid, clientsecret,
 * accesstoken. Renomea-los para um esquema uniforme seria mais bonito e faria o
 * site que ja esta no ar perder o vinculo de cada vendedor em silencio, com o
 * sintoma aparecendo no checkout, diante do aluno. Feio e correto ganha.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application {
    /** @var string Checkout Pro pela API de Preferencias. O marketplace_fee vai na preferencia. */
    const TYPE_PREFERENCES = 'preferences';

    /** @var string Assinaturas (preapproval). NAO leva comissao - ver type_for_recurring(). */
    const TYPE_SUBSCRIPTIONS = 'subscriptions';

    /** @var string Checkout Bricks sobre a Checkout API. O application_fee vai no pagamento. */
    const TYPE_BRICKS = 'bricks';

    /**
     * Os tipos, na ordem em que aparecem na tela.
     *
     * Preferencias primeiro porque e o que ja esta em producao.
     *
     * @var string[]
     */
    const TYPES = [
        self::TYPE_PREFERENCES,
        self::TYPE_SUBSCRIPTIONS,
        self::TYPE_BRICKS,
    ];

    /**
     * Campos que o fluxo de OAuth grava na conta de pagamento.
     *
     * A lista e gerada, e nao escrita a mao em cada lugar, porque campo ausente
     * do formulario e APAGADO ao salvar: a configuracao gravada e a que o
     * formulario devolve. Duas listas divergindo custariam o vinculo de um
     * vendedor sem nenhum erro na tela.
     *
     * @var string[]
     */
    const ACCOUNT_FIELDS = [
        'mpuserid',
        'accesstoken',
        'refreshtoken',
        'tokenexpires',
        'siteid',
        'currency',
    ];

    /**
     * Este e um tipo que o plugin conhece?
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return in_array($type, self::TYPES, true);
    }

    /**
     * Nome de uma setting de SITE para um tipo.
     *
     * @param string $type Um dos TYPES
     * @param string $field clientid, clientsecret
     * @return string
     */
    public static function config_key(string $type, string $field): string {
        return self::suffixed($type, $field);
    }

    /**
     * Nome de um campo guardado na CONTA de pagamento para um tipo.
     *
     * Existe separado do config_key() apesar de a regra ser a mesma: sao dois
     * lugares diferentes - get_config('paygw_mercadopago') de um lado, a
     * configuracao do account_gateway do outro - e o nome do metodo e o que faz
     * o ponto de chamada dizer de qual se trata.
     *
     * @param string $type Um dos TYPES
     * @param string $field accesstoken, refreshtoken, tokenexpires, ...
     * @return string
     */
    public static function token_field(string $type, string $field): string {
        return self::suffixed($type, $field);
    }

    /**
     * Todos os campos que a conta guarda para um tipo.
     *
     * @param string $type
     * @return string[]
     */
    public static function account_fields(string $type): array {
        return array_map(
            static fn(string $field): string => self::suffixed($type, $field),
            self::ACCOUNT_FIELDS
        );
    }

    /**
     * Credenciais da aplicacao, ou null quando ela nao esta configurada.
     *
     * Devolve null tambem com a credencial pela METADE. Meio par produziria um
     * OAuth que falha no Mercado Pago com mensagem generica, e a tela nao teria
     * como dizer o que falta - enquanto "nao configurada" ela sabe dizer.
     *
     * @param string $type
     * @return stdClass|null clientid, clientsecret e site
     */
    public static function credentials(string $type): ?stdClass {
        self::guard($type);

        $config = get_config('paygw_mercadopago');
        $clientid = trim((string) ($config->{self::config_key($type, 'clientid')} ?? ''));
        $clientsecret = trim((string) ($config->{self::config_key($type, 'clientsecret')} ?? ''));

        if ($clientid === '' || $clientsecret === '') {
            return null;
        }

        return (object) [
            'clientid' => $clientid,
            'clientsecret' => $clientsecret,
            // O site e do PLUGIN, e nao da aplicacao. Ele decide em que dominio
            // o vendedor autoriza e entre que contas o split pode acontecer -
            // um por aplicacao permitiria a mistura de paises que o Mercado
            // Pago recusa, e a comissao so cai em conta do mesmo pais.
            'site' => (string) ($config->platformsite ?? 'MLB'),
        ];
    }

    /**
     * A chave publica da aplicacao, usada para MONTAR os campos do cartao.
     *
     * Fica separada do par de OAuth de proposito. Ela nao autoriza vendedor
     * nenhum: serve ao navegador, para tokenizar o cartao. Exigi-la junto do
     * client_id faria uma aplicacao que so vende avulso - onde nao ha campo de
     * cartao - parecer mal configurada.
     *
     * E ela e PUBLICA no sentido literal: vai para o HTML e qualquer um a le.
     * Por isso e configtext, e nao configpasswordunmask - esconder na tela de
     * administracao um valor que aparece no fonte da pagina do aluno seria
     * teatro.
     *
     * @param string $type
     * @return string Vazio quando nao configurada
     */
    public static function public_key(string $type): string {
        self::guard($type);

        return trim((string) get_config('paygw_mercadopago', self::config_key($type, 'publickey')));
    }

    /**
     * Os tipos efetivamente configurados, na ordem de TYPES.
     *
     * A ordem sai da constante, e nao da ordem em que o administrador
     * preencheu: a tela precisa ser estavel entre um acesso e outro.
     *
     * @return string[]
     */
    public static function configured_types(): array {
        return array_values(array_filter(
            self::TYPES,
            static fn(string $type): bool => self::credentials($type) !== null
        ));
    }

    /**
     * Qual aplicacao cobra um ciclo de assinatura levando comissao.
     *
     * A resposta e BRICKS, e nao a de Assinaturas - o que e contraintuitivo o
     * bastante para estar escrito aqui.
     *
     * Medido em 15/09/2026, com a aplicacao do tipo Assinaturas e contas
     * distintas: POST /preapproval aceita marketplace_fee, application_fee e
     * marketplace, na raiz e dentro de auto_recurring. Devolve 201 nos CINCO
     * formatos e nao devolve NENHUM deles no GET seguinte. O recurso simplesmente
     * nao tem onde guardar comissao - o SDK oficial concorda, e o GET completo
     * confirma.
     *
     * Entao a assinatura que leva comissao nao e um preapproval: e uma cobranca
     * por ciclo em /v1/payments com application_fee, sobre um cartao guardado no
     * proprio Mercado Pago. Quem tokeniza esse cartao e o Card Payment Brick,
     * e por isso a aplicacao e a de Bricks.
     *
     * O preapproval continua util, e nao some do plugin por isso: a mensalidade
     * que a EMPRESA paga a plataforma nao tem terceiro, logo nao tem split.
     * Para ela, o preapproval e exatamente a ferramenta certa.
     *
     * Ver docs/data-validation/mercadopago-assinatura.md.
     *
     * @return string
     */
    public static function type_for_recurring(): string {
        return self::TYPE_BRICKS;
    }

    /**
     * Aplica o sufixo do tipo a um nome de campo.
     *
     * @param string $type
     * @param string $field
     * @return string
     */
    protected static function suffixed(string $type, string $field): string {
        self::guard($type);

        return $type === self::TYPE_PREFERENCES ? $field : $field . '_' . $type;
    }

    /**
     * Recusa tipo que o plugin nao conhece.
     *
     * Sem esta trava um apptype vindo da URL viraria nome de configuracao
     * arbitrario, e o vinculo do vendedor seria gravado num campo que ninguem
     * le - perda silenciosa, que e o modo de falha caro deste plugin.
     *
     * @param string $type
     * @return void
     */
    protected static function guard(string $type): void {
        if (!self::is_valid($type)) {
            throw new coding_exception('tipo de aplicacao desconhecido no paygw_mercadopago: ' . $type);
        }
    }
}
