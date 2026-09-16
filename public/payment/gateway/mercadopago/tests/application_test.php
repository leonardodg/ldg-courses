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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * As aplicacoes do Mercado Pago, uma por tipo de integracao.
 *
 * O que estes testes protegem, e que nao aparece em desenvolvimento: a
 * aplicacao de Preferencias continua lendo os nomes de configuracao ANTIGOS.
 * Renomea-los faria o site que ja esta no ar perder o vinculo de cada vendedor
 * em silencio - o checkout so acusaria na hora da compra, diante do aluno.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_mercadopago\application::class)]
final class application_test extends \advanced_testcase {
    /**
     * A aplicacao de Preferencias le os nomes que ja estao em producao.
     *
     * @return void
     */
    public function test_preferencias_le_os_nomes_antigos_de_configuracao(): void {
        $this->assertSame('clientid', application::config_key(application::TYPE_PREFERENCES, 'clientid'));
        $this->assertSame('clientsecret', application::config_key(application::TYPE_PREFERENCES, 'clientsecret'));
        $this->assertSame('accesstoken', application::token_field(application::TYPE_PREFERENCES, 'accesstoken'));
    }

    /**
     * As aplicacoes novas ganham sufixo, para conviverem com a antiga.
     *
     * @return void
     */
    public function test_aplicacao_nova_ganha_sufixo_no_nome(): void {
        $this->assertSame(
            'clientid_subscriptions',
            application::config_key(application::TYPE_SUBSCRIPTIONS, 'clientid')
        );
        $this->assertSame(
            'accesstoken_bricks',
            application::token_field(application::TYPE_BRICKS, 'accesstoken')
        );
    }

    /**
     * Tipo desconhecido nao vira nome de configuracao.
     *
     * Sem esta trava, um apptype vindo da URL viraria chave de config
     * arbitraria - e o vinculo iria para um campo que ninguem le.
     *
     * @return void
     */
    public function test_tipo_desconhecido_e_recusado(): void {
        $this->expectException(\coding_exception::class);
        application::config_key('checkouttransparente', 'clientid');
    }

    /**
     * A lista de tipos e fechada, e a ordem importa para a tela.
     *
     * @return void
     */
    public function test_a_lista_de_tipos_e_fechada(): void {
        $this->assertSame(
            ['preferences', 'subscriptions', 'bricks'],
            application::TYPES
        );
        $this->assertTrue(application::is_valid('subscriptions'));
        $this->assertFalse(application::is_valid('assinaturas'));
    }

    /**
     * Sem client_id e client_secret, a aplicacao nao existe para o plugin.
     *
     * Devolver credencial pela metade produziria um OAuth que falha no
     * Mercado Pago, com mensagem que nao diz o que falta.
     *
     * @return void
     */
    public function test_aplicacao_sem_credencial_completa_nao_existe(): void {
        $this->resetAfterTest();

        $this->assertNull(application::credentials(application::TYPE_SUBSCRIPTIONS));

        set_config('clientid_subscriptions', '6990306155285574', 'paygw_mercadopago');
        $this->assertNull(
            application::credentials(application::TYPE_SUBSCRIPTIONS),
            'com o secret faltando a aplicacao nao pode ser considerada configurada'
        );

        set_config('clientsecret_subscriptions', 'segredo', 'paygw_mercadopago');
        $credenciais = application::credentials(application::TYPE_SUBSCRIPTIONS);
        $this->assertNotNull($credenciais);
        $this->assertSame('6990306155285574', $credenciais->clientid);
        $this->assertSame('segredo', $credenciais->clientsecret);
    }

    /**
     * O site da aplicacao e unico, e nao por tipo.
     *
     * O pais decide em que dominio o vendedor autoriza e entre que contas o
     * split pode acontecer. Ter um por aplicacao permitiria justamente a
     * mistura que o Mercado Pago recusa.
     *
     * @return void
     */
    public function test_o_site_e_do_plugin_e_nao_da_aplicacao(): void {
        $this->resetAfterTest();

        set_config('clientid_bricks', '2598194068751669', 'paygw_mercadopago');
        set_config('clientsecret_bricks', 'segredo', 'paygw_mercadopago');
        set_config('platformsite', 'MLA', 'paygw_mercadopago');

        $this->assertSame('MLA', application::credentials(application::TYPE_BRICKS)->site);
    }

    /**
     * So aparece na tela a aplicacao que foi configurada.
     *
     * @return void
     */
    public function test_lista_so_as_aplicacoes_configuradas(): void {
        $this->resetAfterTest();

        $this->assertSame([], application::configured_types());

        set_config('clientid', '2401225442871147', 'paygw_mercadopago');
        set_config('clientsecret', 'segredo', 'paygw_mercadopago');
        $this->assertSame([application::TYPE_PREFERENCES], application::configured_types());

        set_config('clientid_subscriptions', '6990306155285574', 'paygw_mercadopago');
        set_config('clientsecret_subscriptions', 'segredo', 'paygw_mercadopago');
        $this->assertSame(
            [application::TYPE_PREFERENCES, application::TYPE_SUBSCRIPTIONS],
            application::configured_types(),
            'a ordem sai de TYPES, e nao da ordem em que foram configuradas'
        );
    }

    /**
     * Cada tipo guarda o proprio conjunto de campos na conta de pagamento.
     *
     * Campo ausente do formulario e APAGADO ao salvar - por isso a lista
     * precisa ser gerada, e nao escrita a mao em dois lugares.
     *
     * @return void
     */
    public function test_os_campos_da_conta_sao_um_conjunto_por_tipo(): void {
        $this->assertSame(
            ['mpuserid', 'accesstoken', 'refreshtoken', 'tokenexpires', 'siteid', 'currency'],
            application::account_fields(application::TYPE_PREFERENCES)
        );
        $this->assertSame(
            [
                'mpuserid_subscriptions',
                'accesstoken_subscriptions',
                'refreshtoken_subscriptions',
                'tokenexpires_subscriptions',
                'siteid_subscriptions',
                'currency_subscriptions',
            ],
            application::account_fields(application::TYPE_SUBSCRIPTIONS)
        );
    }

    /**
     * Assinatura nao sai pela aplicacao de Preferencias.
     *
     * Medido em 15/09/2026: o POST /preapproval aceita marketplace_fee,
     * application_fee e marketplace, devolve 201 em todos, e nao devolve
     * NENHUM deles no GET. Ver docs/data-validation/mercadopago-assinatura.md.
     *
     * @return void
     */
    public function test_so_bricks_cobra_ciclo_de_assinatura_com_comissao(): void {
        $this->assertSame(application::TYPE_BRICKS, application::type_for_recurring());
        $this->assertNotSame(application::TYPE_SUBSCRIPTIONS, application::type_for_recurring());
    }

    /**
     * A chave publica e separada do par de OAuth, e a separacao tem razao.
     *
     * Ela e necessaria para MONTAR os campos do cartao, e nao para autorizar
     * vendedor nenhum. Exigi-la junto do client_id faria uma aplicacao que so
     * vende avulso - onde nao ha campo de cartao - parecer mal configurada.
     *
     * @return void
     */
    public function test_a_chave_publica_e_opcional_e_separada(): void {
        $this->resetAfterTest();

        set_config('clientid_bricks', '4205369394622168', 'paygw_mercadopago');
        set_config('clientsecret_bricks', 'segredo', 'paygw_mercadopago');

        $this->assertNotNull(
            application::credentials(application::TYPE_BRICKS),
            'sem chave publica a aplicacao continua configurada para OAuth'
        );
        $this->assertSame('', application::public_key(application::TYPE_BRICKS));

        set_config('publickey_bricks', 'APP_USR-4b7e1753', 'paygw_mercadopago');
        $this->assertSame('APP_USR-4b7e1753', application::public_key(application::TYPE_BRICKS));
    }

    /**
     * A chave publica segue o modo de teste do SITE.
     *
     * Sao guardadas as DUAS - producao e teste -, e quem escolhe e o testmode,
     * o mesmo interruptor que ja decide se o OAuth emite token de teste. Ter de
     * trocar a chave a mao ao ligar o modo de teste seria a configuracao em
     * dois lugares que este plugin ja pagou caro para evitar: comprador,
     * vendedor e aplicacao precisam estar todos do mesmo lado, e uma chave de
     * producao com token de teste devolve "Invalid users involved" sem dizer
     * qual das partes esta fora.
     *
     * @return void
     */
    public function test_a_chave_publica_segue_o_modo_de_teste(): void {
        $this->resetAfterTest();

        set_config('publickey_bricks', 'APP_USR-producao', 'paygw_mercadopago');
        set_config('publickeytest_bricks', 'TEST-teste', 'paygw_mercadopago');

        set_config('testmode', 0, 'paygw_mercadopago');
        $this->assertSame('APP_USR-producao', application::public_key(application::TYPE_BRICKS));

        set_config('testmode', 1, 'paygw_mercadopago');
        $this->assertSame('TEST-teste', application::public_key(application::TYPE_BRICKS));
    }

    /**
     * Em modo de teste SEM chave de teste, nao se cai na de producao.
     *
     * Cair seria pior que faltar: a cobranca nasceria misturando ambientes e a
     * recusa chegaria como problema com o cartao. Vazio faz o subscribe.php
     * dizer que falta configurar, que e a verdade.
     *
     * @return void
     */
    public function test_sem_chave_de_teste_nao_cai_na_de_producao(): void {
        $this->resetAfterTest();

        set_config('publickey_bricks', 'APP_USR-producao', 'paygw_mercadopago');
        set_config('testmode', 1, 'paygw_mercadopago');

        $this->assertSame('', application::public_key(application::TYPE_BRICKS));
    }

    /**
     * A chave publica de Preferencias tambem usa o nome legado.
     *
     * @return void
     */
    public function test_a_chave_publica_de_preferencias_usa_o_nome_legado(): void {
        $this->assertSame('publickey', application::config_key(application::TYPE_PREFERENCES, 'publickey'));
    }
}
