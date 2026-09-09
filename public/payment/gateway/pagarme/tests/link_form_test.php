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

namespace paygw_pagarme;

use paygw_pagarme\form\link_form;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A recusa de chave do ambiente errado.
 *
 * Isto NAO da para provar por behat: a tela de vinculo exige chave de cifragem
 * no site, e o site do behat nasce sem uma. O que o behat prova la e a guarda
 * da cifragem; a validacao do formulario se prova aqui.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(link_form::class)]
final class link_form_test extends \advanced_testcase {
    /**
     * Um formulario montado para o ambiente pedido.
     *
     * @param string $environment
     * @return link_form
     */
    protected function formulario(string $environment): link_form {
        global $CFG;

        require_once($CFG->libdir . '/formslib.php');

        return new link_form(new \moodle_url('/x'), null, 'post', '', null, true, [
            'accountid' => 1,
            'environment' => $environment,
        ]);
    }

    public function test_chave_de_producao_e_recusada_em_homologacao(): void {
        $this->resetAfterTest();

        // O prefixo e a UNICA coisa que separa os ambientes no Pagar.me: nao
        // ha host diferente para tropecar antes. Sem esta recusa, uma chave de
        // producao vincularia como se fosse de teste e a primeira compra seria
        // dinheiro real.
        $erros = $this->formulario(pagarme_client::ENV_SANDBOX)->validation([
            'environment' => pagarme_client::ENV_SANDBOX,
            'apikey' => 'sk_de_producao',
            'platformrecipient' => 'rp_1',
        ], []);

        $this->assertArrayHasKey('apikey', $erros);
    }

    public function test_chave_de_homologacao_e_recusada_em_producao(): void {
        $this->resetAfterTest();

        $erros = $this->formulario(pagarme_client::ENV_PRODUCTION)->validation([
            'environment' => pagarme_client::ENV_PRODUCTION,
            'apikey' => 'sk_test_de_homologacao',
            'platformrecipient' => 'rp_1',
        ], []);

        $this->assertArrayHasKey('apikey', $erros);
    }

    public function test_chave_do_ambiente_certo_passa(): void {
        $this->resetAfterTest();

        $erros = $this->formulario(pagarme_client::ENV_SANDBOX)->validation([
            'environment' => pagarme_client::ENV_SANDBOX,
            'apikey' => 'sk_test_certa',
            'platformrecipient' => 'rp_1',
        ], []);

        $this->assertArrayNotHasKey('apikey', $erros);
    }

    public function test_chave_vazia_nao_reclama_de_ambiente(): void {
        $this->resetAfterTest();

        // Campo vazio ja e pego pela regra de obrigatorio. Acusar ambiente
        // errado aqui daria duas mensagens para o mesmo problema.
        $erros = $this->formulario(pagarme_client::ENV_SANDBOX)->validation([
            'environment' => pagarme_client::ENV_SANDBOX,
            'apikey' => '',
            'platformrecipient' => 'rp_1',
        ], []);

        $this->assertArrayNotHasKey('apikey', $erros);
    }
}
