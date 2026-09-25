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

namespace local_marketplace;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * O esquema declarado, o esquema migrado e as classes que os leem.
 *
 * ESTES TESTES EXISTEM POR UM DEFEITO QUE CUSTOU DINHEIRO. O passo de upgrade
 * 2026090110 acrescentou `commissionbase` a `local_marketplace_course_policy` -
 * tabela que nao existe, porque o nome saiu da CLASSE (`course_policy`) e nao da
 * constante `TABLE` dela, que e `local_marketplace_course`.
 *
 * Nada reclamou. A guarda `table_exists()` do proprio passo devolveu falso, o
 * campo nunca foi criado, e o upgrade terminou com sucesso. Depois, o
 * `insert_record` do Moodle - que descarta em silencio campo ausente da tabela -
 * gravava politica de curso SEM a base de calculo, e a leitura devolvia nulo,
 * que significa "herda a base do site". Comissao negociada sobre o liquido saia
 * cobrada sobre o bruto.
 *
 * Instalacao nova nunca viu o problema: o `install.xml` declara a coluna. Por
 * isso NENHUM teste de banco comum pegaria - o PHPUnit instala do zero, e ali o
 * esquema sempre bate. O que estes testes leem e o TEXTO das migracoes, que e
 * onde a divergencia mora.
 *
 * ELES NAO OLHAM SO O PLUGIN DE CASA. O defeito nao era do marketplace, era da
 * FORMA, e a forma cabe em qualquer `db/upgrade.php` que alguem escreva amanha -
 * por isso a varredura passa pelos dez plugins do projeto. Plugin ausente da
 * arvore e pulado: o CI instala um subconjunto por job, e reprovar por isso
 * seria reprovar por algo que nao e defeito.
 *
 * A regra que saiu daqui, e que vale para todo passo novo: UMA INSTRUCAO DE
 * MIGRACAO NAO PODE RODAR SEM FAZER NADA E AINDA ASSIM PASSAR. Quando a tabela e
 * do proprio plugin, ela existe - perguntar se existe antes de acrescentar campo
 * nao protege de nada, e transforma nome errado em silencio. Sem a guarda, o
 * mesmo engano estoura na hora, com o nome da tabela na mensagem.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_marketplace\course_policy::class)]
final class db_schema_test extends \advanced_testcase {
    /**
     * Tabelas que o plugin REMOVEU ao longo da historia.
     *
     * Elas aparecem no upgrade.php e nao no install.xml, e isso esta certo: um
     * passo de `drop_table` precisa nomear o que vai apagar. A lista e curta e
     * so cresce quando alguem remove uma tabela de proposito - que e
     * exatamente a hora de parar para pensar.
     *
     * @var string[]
     */
    private const REMOVED = [
        // Guardava o token do Mercado Pago, e criava uma segunda fonte de
        // verdade para credencial financeira. Removida em 2026082404; a
        // credencial vive no account_gateway.config do core_payment.
        'local_marketplace_mpaccount',
    ];

    /**
     * Nomes ERRADOS que ficam no historico, e o passo que consertou cada um.
     *
     * Esta lista e a unica saida honesta para um engano que ja rodou. Um passo
     * de upgrade roda uma vez por site: onde ele ja passou, mudar o texto nao
     * tem efeito nenhum, e onde ainda nao passou o passo corretivo resolve do
     * mesmo jeito. Consertar a execucao do passado e trabalho sem destino.
     *
     * Entrar aqui e uma decisao, e nao um atalho: o nome so entra depois que o
     * passo corretivo existe. Enquanto ele nao existir, o teste falha - que e o
     * comportamento desejado.
     *
     * @var array<string,string> nome errado => passo que corrigiu
     */
    private const WRONG_NAMES_FIXED = [
        // Saiu da classe (`course_policy`) e nao da constante TABLE dela, que e
        // `local_marketplace_course`. O table_exists() devolveu falso, a coluna
        // `commissionbase` nunca foi criada, e o upgrade terminou com sucesso.
        'local_marketplace_course_policy' => '2026091110',
    ];

    /**
     * Guardas de `table_exists() &&` que ficam no historico, e por que.
     *
     * A chave e o passo onde a guarda mora. Mesma regra da lista de cima: so
     * entra depois que existe um passo corretivo, e o texto do passo antigo NAO
     * e mexido - onde ele ja rodou, mudar nao tem efeito.
     *
     * @var array<string,string> passo com a guarda => passo que corrigiu
     */
    private const GUARDS_FIXED = [
        // Guardava tres add_field, e um deles nomeava tabela inexistente. A
        // guarda engoliu o engano; sem ela o upgrade teria estourado no dia.
        '2026090110' => '2026091110',
    ];

    /**
     * Os plugins deste projeto que tem migracao propria.
     *
     * O defeito nao e do marketplace, e da FORMA. Varrer so o plugin de casa
     * deixaria os outros nove livres para repetir exatamente o mesmo engano -
     * que foi a razao de este teste existir.
     *
     * Plugin ausente da arvore e pulado, e nao reprovado: o CI instala um
     * subconjunto por job, e um teste que exige a arvore inteira falharia por
     * motivo que nao e defeito nenhum.
     *
     * @var array<string,string> caminho sob dirroot => prefixo das tabelas
     */
    private const PROJECT_PLUGINS = [
        'local/marketplace' => 'local_marketplace',
        'local/partners' => 'local_partners',
        'theme/ldg' => 'theme_ldg',
        'enrol/marketplace' => 'enrol_marketplace',
        'availability/condition/marketplace' => 'availability_marketplace',
        'blocks/marketplace' => 'block_marketplace',
        'mod/ldgvideo' => 'ldgvideo',
        'payment/gateway/asaas' => 'paygw_asaas',
        'payment/gateway/mercadopago' => 'paygw_mercadopago',
        'course/format/ldg' => 'format_ldg',
    ];

    /**
     * O caminho dos arquivos de banco do plugin.
     *
     * @param string $file
     * @return string
     */
    private function path(string $file): string {
        global $CFG;

        return $CFG->dirroot . '/local/marketplace/db/' . $file;
    }

    /**
     * As tabelas declaradas no install.xml.
     *
     * @return string[]
     */
    private function declared_tables(): array {
        $xml = file_get_contents($this->path('install.xml'));
        preg_match_all('/<TABLE NAME="([a-z_]+)"/', $xml, $matches);

        return $matches[1];
    }

    /**
     * Toda tabela citada no upgrade.php existe, ou foi removida de proposito.
     *
     * E O TESTE QUE TERIA PEGO O DEFEITO. Um nome de tabela errado num passo de
     * migracao nao produz erro nenhum em tempo de execucao - a guarda
     * `table_exists()`, que existe para o passo ser idempotente, transforma o
     * engano em silencio.
     *
     * @return void
     */
    public function test_o_upgrade_so_cita_tabela_que_existe(): void {
        $php = file_get_contents($this->path('upgrade.php'));
        preg_match_all('/[\'"](local_marketplace_[a-z_]+)[\'"]/', $php, $matches);

        $cited = array_unique($matches[1]);
        $known = array_merge(
            $this->declared_tables(),
            self::REMOVED,
            array_keys(self::WRONG_NAMES_FIXED)
        );

        $orphans = array_values(array_diff($cited, $known));

        $this->assertSame([], $orphans, implode("\n", [
            'O upgrade.php cita tabela que nao existe no install.xml: ' . implode(', ', $orphans) . '.',
            'Confira a constante TABLE da classe - o nome da classe nao e o nome da tabela.',
            'Se a tabela foi removida de proposito, acrescente-a a db_schema_test::REMOVED.',
            'Se o passo ja rodou errado e outro passo ja corrigiu, use WRONG_NAMES_FIXED.',
        ]));
    }

    /**
     * Todo nome errado do historico tem o passo corretivo no arquivo.
     *
     * Sem isto, `WRONG_NAMES_FIXED` viraria um lugar para calar o teste. A
     * entrada so se sustenta enquanto o passo que consertou existir de verdade -
     * apagar o passo derruba este teste, e nao o outro.
     *
     * @return void
     */
    public function test_todo_engano_do_historico_tem_passo_corretivo(): void {
        $php = file_get_contents($this->path('upgrade.php'));

        foreach (self::WRONG_NAMES_FIXED as $wrong => $step) {
            $this->assertStringContainsString(
                "oldversion < {$step}",
                $php,
                "'{$wrong}' esta listada como corrigida pelo passo {$step}, e esse passo nao existe"
            );
        }
    }

    /**
     * Nenhum passo NOVO esconde uma operacao atras de `table_exists() &&`.
     *
     * E A LICAO DO BUG, VIRADA EM REGRA. Uma instrucao de migracao nao pode
     * "rodar" sem fazer nada e ainda assim passar. Quando a tabela e do proprio
     * plugin - declarada no install.xml -, ela EXISTE; perguntar se existe antes
     * de acrescentar campo nao protege de nada e transforma nome errado em
     * silencio. Sem a guarda, o mesmo engano estoura na hora, com o nome da
     * tabela na mensagem.
     *
     * As guardas que CONTINUAM certas nao casam com este padrao, e por isso o
     * teste procura a forma exata `table_exists(...) &&`:
     *
     *   drop_table          `if (table_exists(...))` sozinho - so se apaga o que
     *                       existe, e a tabela pode ja ter sido apagada
     *   criacao idempotente `if (!table_exists(...))` - negada, outra forma
     *   plugin de terceiro  a tabela pode nao estar instalada, e ai a guarda e a
     *                       propria regra de negocio
     *
     * @return void
     */
    public function test_nenhum_passo_novo_esconde_operacao_atras_de_guarda(): void {
        global $CFG;

        foreach (self::PROJECT_PLUGINS as $path => $prefix) {
            $file = $CFG->dirroot . '/' . $path . '/db/upgrade.php';

            if (!file_exists($file)) {
                continue;
            }

            $php = file_get_contents($file);

            // A forma exata: table_exists(...) seguido de && na mesma condicao.
            preg_match_all('/table_exists\([^)]*\)\s*&&/', $php, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$snippet, $position]) {
                $step = $this->step_at($php, $position);

                $this->assertArrayHasKey(
                    $step,
                    self::GUARDS_FIXED,
                    implode("
", [
                        "{$path}/db/upgrade.php, passo {$step}: '{$snippet}' esconde a operacao.",
                        'Tabela do proprio plugin sempre existe - a guarda so faz nome errado passar calado.',
                        'Tire o table_exists() e deixe o erro estourar, com o nome da tabela na mensagem.',
                        'Se a tabela e de plugin de TERCEIRO, que pode nao estar instalado, a guarda esta certa',
                        'e o passo entra em db_schema_test::GUARDS_FIXED com a justificativa.',
                    ])
                );
            }
        }
    }

    /**
     * Em qual passo de upgrade cai uma posicao do arquivo.
     *
     * @param string $php
     * @param int $position
     * @return string
     */
    private function step_at(string $php, int $position): string {
        preg_match_all('/oldversion\s*<\s*(\d+)/', substr($php, 0, $position), $matches);

        return empty($matches[1]) ? 'fora de passo' : end($matches[1]);
    }

    /**
     * Nenhum plugin do projeto cita tabela que nao existe.
     *
     * O mesmo conferidor do teste de cima, aplicado aos OUTROS nove plugins. O
     * defeito nao era do marketplace, era da forma - e a forma cabe em qualquer
     * `db/upgrade.php` que alguem escreva amanha.
     *
     * @return void
     */
    public function test_nenhum_plugin_do_projeto_cita_tabela_ausente(): void {
        global $CFG;

        $scanned = 0;

        foreach (self::PROJECT_PLUGINS as $path => $prefix) {
            $base = $CFG->dirroot . '/' . $path . '/db/';

            if (!file_exists($base . 'upgrade.php')) {
                continue;
            }

            $scanned++;

            $declared = [];
            if (file_exists($base . 'install.xml')) {
                preg_match_all('/<TABLE NAME="([a-z_0-9]+)"/', file_get_contents($base . 'install.xml'), $matches);
                $declared = $matches[1];
            }

            preg_match_all(
                '/[\'"](' . preg_quote($prefix, '/') . '_[a-z_0-9]+)[\'"]/',
                file_get_contents($base . 'upgrade.php'),
                $matches
            );

            $known = array_merge($declared, self::REMOVED, array_keys(self::WRONG_NAMES_FIXED));
            $orphans = array_values(array_diff(array_unique($matches[1]), $known));

            $this->assertSame(
                [],
                $orphans,
                "{$path}/db/upgrade.php cita tabela que nao existe: " . implode(', ', $orphans)
            );
        }

        // UM TESTE QUE NAO VARRE NADA PASSA, e passar sem fazer nada foi
        // justamente o defeito que trouxe este arquivo a existencia.
        //
        // O CI leva os outros plugins em --extra-plugins a cada job, entao a
        // varredura e real la. Se um dia deixar de levar, esta linha avisa - em
        // vez de o teste encolher para um plugin so, continuar verde, e ninguem
        // notar que a promessa de "os dez plugins" virou um.
        $this->assertGreaterThanOrEqual(2, $scanned, implode("\n", [
            "Só {$scanned} plugin(s) do projeto estavam na arvore, e este teste existe para varrer varios.",
            'Verde aqui nao significa mais nada: confira se o CI ainda instala os outros em --extra-plugins.',
        ]));
    }

    /**
     * Cada propriedade de persistent tem coluna na tabela dela.
     *
     * O outro lado da mesma moeda: acrescentar propriedade a um persistent e
     * esquecer o install.xml da o mesmo silencio, so que na direcao contraria -
     * o campo existe no PHP e some na gravacao.
     *
     * @return void
     */
    public function test_cada_propriedade_de_persistent_tem_coluna(): void {
        global $DB;

        $this->resetAfterTest();

        $classes = [
            \local_marketplace\company::class,
            \local_marketplace\course_policy::class,
            \local_marketplace\offer::class,
            \local_marketplace\plan::class,
            \local_marketplace\entitlement::class,
            \local_marketplace\sale::class,
            \local_marketplace\library_account::class,
        ];

        foreach ($classes as $class) {
            $table = $class::TABLE;
            $columns = array_keys($DB->get_columns($table));

            $this->assertNotEmpty($columns, "a tabela {$table} da classe {$class} nao existe");

            foreach (array_keys($class::properties_definition()) as $property) {
                $this->assertContains(
                    $property,
                    $columns,
                    "{$class} declara '{$property}', e {$table} nao tem essa coluna"
                );
            }
        }
    }

    /**
     * A base da comissao por curso sobrevive a uma ida e volta ao banco.
     *
     * O defeito nao derrubava nada: o valor era descartado na gravacao, e a
     * leitura devolvia nulo. Um teste que so grava, ou que so le, passaria dos
     * dois lados - por isso este faz o caminho inteiro e compara.
     *
     * @return void
     */
    public function test_a_base_por_curso_sobrevive_a_gravacao(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $policy = new course_policy(0, (object) [
            'courseid' => (int) $course->id,
            'companyid' => 1,
            'hostingtype' => course_policy::HOSTING_EXTERNAL,
            'commissionpct' => 7.5,
            'commissionbase' => commission::BASE_NET,
        ]);
        $policy->create();

        $read = new course_policy($policy->get('id'));

        $this->assertSame(commission::BASE_NET, $read->get('commissionbase'));
    }

    /**
     * Base nao declarada continua nula, e nao vira 'gross'.
     *
     * Nulo e "este contrato nao define base, herda a do site", e isso e
     * diferente de escolher bruto. Preencher o nulo apagaria a distincao, e
     * depois nao haveria como saber quem escolheu e quem herdou.
     *
     * @return void
     */
    public function test_base_ausente_continua_nula(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $policy = new course_policy(0, (object) [
            'courseid' => (int) $course->id,
            'companyid' => 1,
            'hostingtype' => course_policy::HOSTING_EXTERNAL,
            'commissionpct' => 7.5,
        ]);
        $policy->create();

        $read = new course_policy($policy->get('id'));

        $this->assertNull($read->get('commissionbase'));
    }
}
