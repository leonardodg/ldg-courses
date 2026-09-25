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
 * Upgrade do local_marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Passos de upgrade.
 *
 * @param int $oldversion Versao instalada.
 * @return bool
 */
function xmldb_local_marketplace_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026082403) {
        // O install.php passou a garantir allowcategorythemes, mas ele so roda
        // em instalacao nova. Sem este passo, todo ambiente que ja tem o plugin
        // continuaria com o tema por empresa quebrado em silencio.
        require_once(__DIR__ . '/install.php');
        local_marketplace_require_category_themes();

        upgrade_plugin_savepoint(true, 2026082403, 'local', 'marketplace');
    }

    if ($oldversion < 2026082404) {
        // Remove a tabela local_marketplace_mpaccount.
        //
        // Ela guardava o token do Mercado Pago, o que criava DUAS fontes de
        // verdade para uma credencial financeira. A credencial passou a viver
        // onde o Moodle espera: account_gateway.config do core_payment.
        //
        // Tirar a tabela do install.xml nao basta - o Moodle nunca apaga
        // tabela sozinho num upgrade, e com razao. O sintoma era o
        // check_database_schema acusando "table is not expected".
        //
        // Nao ha dado a migrar: o vinculo com o Mercado Pago sempre foi feito
        // pelo fluxo OAuth do gateway, que grava direto na conta de pagamento.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_marketplace_mpaccount');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082404, 'local', 'marketplace');
    }

    if ($oldversion < 2026082520) {
        $dbman = $DB->get_manager();

        // Modelo de assinatura com tres parametros independentes.
        //
        // Ate aqui havia um so: accessdays. Ele significava "duracao do acesso"
        // em days e "intervalo de cobranca" em recurring - dois conceitos no
        // mesmo campo, o que impedia expressar carencia e impedia limitar
        // quantas vezes a assinatura cobra.
        $table = new xmldb_table('local_marketplace_offer');

        $field = new xmldb_field('billingdays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'accessdays');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxcycles', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'billingdays');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // As assinaturas que ja existem cobravam no mesmo intervalo do acesso,
        // porque era um campo so. Copiar preserva o comportamento delas.
        $DB->execute("UPDATE {local_marketplace_offer}
                         SET billingdays = accessdays
                       WHERE accessmode = ? AND billingdays = 0", ['recurring']);

        // Contador de ciclos no direito.
        $table = new xmldb_table('local_marketplace_entitlement');
        $field = new xmldb_field('cycles', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quem ja comprou pagou ao menos uma vez. Deixar em zero faria um
        // limite de ciclos contar do zero para quem ja esta pagando ha meses.
        $DB->execute("UPDATE {local_marketplace_entitlement} SET cycles = 1 WHERE cycles = 0");

        upgrade_plugin_savepoint(true, 2026082520, 'local', 'marketplace');
    }

    if ($oldversion < 2026082521) {
        $dbman = $DB->get_manager();

        // Cancelar assinatura NAO e revogar acesso.
        //
        // O aluno que pagou 30 dias e cancela no dia 10 nao pode perder os 20
        // que restam: ele pagou por eles. Cancelar significa "nao quero
        // renovar" - o acesso corre ate timeend e os avisos param.
        //
        // Revogacao imediata continua existindo como status=cancelled, para
        // estorno e fraude, onde o dinheiro voltou e o acesso tem que sair na
        // hora.
        $table = new xmldb_table('local_marketplace_entitlement');
        $field = new xmldb_field('norenew', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'cycles');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082521, 'local', 'marketplace');
    }

    if ($oldversion < 2026082523) {
        $dbman = $DB->get_manager();

        // Comissao negociada por empresa.
        //
        // NULO de proposito: e a diferenca entre "nao negociamos nada, use o
        // padrao do site" e "negociamos zero por cento". Com NOT NULL e default
        // 25, as duas situacoes seriam indistinguiveis, e um parceiro isento
        // voltaria a pagar 25% na primeira vez que alguem mudasse o padrao.
        $table = new xmldb_table('local_marketplace_company');
        $field = new xmldb_field('commissionpct', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082523, 'local', 'marketplace');
    }

    if ($oldversion < 2026082540) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_marketplace_company');

        // Personalizacao da vitrine por empresa. Campos, e nao HTML livre: o
        // vendedor A escrevendo script que roda no navegador do aluno da
        // empresa B seria XSS entre inquilinos. Quem quiser pagina propria de
        // verdade usa a API e hospeda onde quiser.
        foreach (
            [
            ['pagetitle', XMLDB_TYPE_CHAR, '255', 'commissionpct'],
            ['pageintro', XMLDB_TYPE_TEXT, null, 'pagetitle'],
            ['pageaccent', XMLDB_TYPE_CHAR, '7', 'pageintro'],
            ] as [$name, $type, $precision, $after]
        ) {
            $field = new xmldb_field($name, $type, $precision, null, null, null, null, $after);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026082540, 'local', 'marketplace');
    }

    if ($oldversion < 2026082546) {
        $dbman = $DB->get_manager();

        // A oferta passa a dizer em que PAIS vende, em ISO-3166 alpha-2.
        //
        // O pais vive na oferta e nao na empresa porque core_payment resolve
        // valor, moeda e conta a partir do itemid, sem receber o usuario: uma
        // oferta so nao consegue ser BRL para um aluno e ARS para outro.
        $table = new xmldb_table('local_marketplace_offer');
        $field = new xmldb_field('country', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, 'BR', 'price');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('companyid-country', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'country']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Conta de pagamento por pais.
        $table = new xmldb_table('local_marketplace_account');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('country', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accountid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'local_marketplace_company', ['id']);
        $table->add_key('accountid', XMLDB_KEY_FOREIGN_UNIQUE, ['accountid'], 'payment_accounts', ['id']);
        $table->add_index('companyid-country', XMLDB_INDEX_UNIQUE, ['companyid', 'country']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adota as contas que ja existem.
        //
        // Sem isto elas ficariam orfas: a busca passa a ser por pais, e uma
        // conta sem linha aqui e invisivel - a empresa apareceria como "sem
        // meio de pagamento" mesmo com o vinculo concluido e funcionando.
        //
        // O pais vem do siteid que o vinculo do Mercado Pago ja detectava
        // (config.siteid), e do pais da plataforma quando o vinculo e anterior
        // a isso. Nos dois casos esta certo: ate agora so havia como vincular
        // conta do mesmo pais da aplicacao configurada.
        upgrade_local_marketplace_adopt_accounts();

        upgrade_plugin_savepoint(true, 2026082546, 'local', 'marketplace');
    }

    if ($oldversion < 2026082547) {
        $dbman = $DB->get_manager();

        // Tabela de vendas neutra. O relatorio lia paygw_mercadopago direto, e
        // com um segundo gateway ele passaria a mentir por omissao: a venda
        // existiria, o aluno estaria matriculado, e o total simplesmente nao
        // contaria aquele dinheiro.
        $table = new xmldb_table('local_marketplace_sale');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('paymentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('offerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('feeamount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('externalid', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('paymentid', XMLDB_KEY_FOREIGN_UNIQUE, ['paymentid'], 'payments', ['id']);
        $table->add_key('offerid', XMLDB_KEY_FOREIGN, ['offerid'], 'local_marketplace_offer', ['id']);
        $table->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'local_marketplace_company', ['id']);
        $table->add_index('companyid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_local_marketplace_backfill_sales();

        // A comissao padrao do site sai do namespace de um gateway. Copiar o
        // valor atual e obrigatorio: perder isto faria toda empresa sem
        // comissao negociada voltar ao padrao de fabrica de 25% - uma mudanca
        // de preco silenciosa em cima de contrato ja fechado.
        $inherited = get_config('paygw_mercadopago', 'defaultfeepercent');
        if ($inherited !== false && $inherited !== '' && get_config('local_marketplace', 'defaultfeepercent') === false) {
            set_config('defaultfeepercent', $inherited, 'local_marketplace');
        }

        upgrade_plugin_savepoint(true, 2026082547, 'local', 'marketplace');
    }

    if ($oldversion < 2026083110) {
        $dbman = $DB->get_manager();

        // As tabelas ANTES do campo que as referencia: a chave estrangeira de
        // company.planid aponta para local_marketplace_plan.
        foreach (['local_marketplace_plan', 'local_marketplace_plan_tier'] as $tablename) {
            $table = new xmldb_table($tablename);
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $tablename);
            }
        }

        // NULLABLE de proposito. Toda empresa que ja existe sai deste upgrade
        // com planid nulo, o degrau do plano na cadeia de comissao nao dispara,
        // e NENHUMA venda de producao muda de valor hoje. Era o requisito: o
        // split so foi provado uma vez, e ligar planos nao pode mexer nisso.
        $table = new xmldb_table('local_marketplace_company');
        $field = new xmldb_field('planid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'commissionpct');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $dbman->add_key($table, new xmldb_key(
                'planid',
                XMLDB_KEY_FOREIGN,
                ['planid'],
                'local_marketplace_plan',
                ['id']
            ));
        }

        // Semeia os planos publicos. O seed e idempotente por shortname e so
        // insere - um site que ja tenha planos passa por aqui sem mudanca.
        require_once(__DIR__ . '/install.php');
        local_marketplace_seed_plans();

        upgrade_plugin_savepoint(true, 2026083110, 'local', 'marketplace');
    }

    if ($oldversion < 2026090110) {
        $dbman = $DB->get_manager();

        // Base de calculo da comissao, configuravel em cada degrau da cadeia.
        //
        // Todas nascem NULAS, e nao com 'gross': nulo significa "este contrato
        // nao define base, herda a do site". Gravar 'gross' em toda linha
        // existente inventaria uma clausula que ninguem negociou, e depois nao
        // haveria como distinguir quem escolheu bruto de quem so herdou.
        //
        // A TERCEIRA TABELA NAO EXISTE, E O NOME FICA COMO ESTA.
        //
        // `local_marketplace_course_policy` saiu da classe (`course_policy`) e
        // nao da constante TABLE dela, que e `local_marketplace_course`. O
        // `table_exists()` devolveu falso, o campo nunca foi criado, e o passo
        // terminou com sucesso - o defeito e justamente esse silencio.
        //
        // NAO CORRIJA AQUI. Um passo de upgrade roda uma vez por site: onde ele
        // ja rodou, mudar o texto nao tem efeito nenhum, e onde ainda nao rodou
        // o passo 2026091110 resolve do mesmo jeito. Consertar uma execucao que
        // ja passou e trabalho sem destino; o que vale e o passo novo.
        foreach (['local_marketplace_company', 'local_marketplace_plan', 'local_marketplace_course_policy'] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('commissionbase', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'commissionpct');

            if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // A FOTO dos termos na venda. As vendas que ja existem recebem os
        // defaults da coluna - 'gross' e 'site' - porque e a melhor
        // aproximacao disponivel, e nao porque seja verdade sobre elas.
        $table = new xmldb_table('local_marketplace_sale');

        $field = new xmldb_field('feepercent', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0', 'feeamount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('feebase', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'gross', 'feepercent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('feesource', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'site', 'feebase');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090110, 'local', 'marketplace');
    }

    if ($oldversion < 2026090410) {
        // Dois papeis onde havia um, e a lista de proibicao fechada.
        //
        // ESTE PASSO EXISTE PORQUE O db/install.php NAO RODA AQUI. Ele so e
        // executado em instalacao nova, entao ate 04/09/2026 toda mudanca na
        // lista de capabilities do papel de vendedor valia apenas para quem
        // instalasse a plataforma do zero - a producao seguia com a lista do
        // dia em que foi instalada.
        //
        // ensure() e migrate_owners() sao idempotentes, e isso nao e luxo: um
        // upgrade que morre no meio e rodado de novo.
        \local_marketplace\roles::ensure();
        \local_marketplace\roles::migrate_owners();

        upgrade_plugin_savepoint(true, 2026090410, 'local', 'marketplace');
    }

    if ($oldversion < 2026091110) {
        // A base da comissao POR CURSO, que nunca chegou ao banco.
        //
        // O passo 2026090110 acrescentou `commissionbase` a tres tabelas, e uma
        // delas nao existe: ele pediu `local_marketplace_course_policy`, e a
        // tabela chama-se `local_marketplace_course`. O nome saiu da classe
        // (`course_policy`), e nao da constante TABLE dela.
        //
        // O erro passou em silencio por causa da guarda do proprio passo: o
        // `table_exists()` devolveu falso, o `add_field()` nem foi chamado, e o
        // upgrade terminou com sucesso.
        //
        // O SINTOMA NAO E ERRO, E PERDA SILENCIOSA. O `insert_record` do Moodle
        // descarta campo que nao existe na tabela, entao gravar uma politica com
        // base "liquido" gravava a linha inteira MENOS a base, e a leitura
        // seguinte devolvia nulo - que significa "herda a base do site". O
        // administrador negociava comissao sobre o liquido, a tela salvava sem
        // reclamar, e a plataforma cobrava sobre o BRUTO. Em R$ 100 a 25%, a
        // diferenca e R$ 25,00 contra R$ 24,38, sempre contra o vendedor.
        //
        // Instalacao nova nunca teve o problema: o install.xml declara a coluna
        // desde sempre. Quem foi atingido foi quem ATUALIZOU - a producao, entre
        // 01/09/2026 e 11/09/2026.
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_marketplace_course');
        $field = new xmldb_field('commissionbase', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'commissionpct');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Nasce NULA, como nas outras duas tabelas: nulo e "este contrato nao
        // define base, herda a do site". Nao ha o que restaurar - o valor que os
        // administradores tentaram gravar nunca chegou ao banco, e inventar
        // 'gross' aqui fingiria uma clausula que ninguem negociou.

        upgrade_plugin_savepoint(true, 2026091110, 'local', 'marketplace');
    }

    if ($oldversion < 2026091701) {
        // Vencimento da mensalidade do PLANO - a assinatura SaaS que a
        // empresa paga a plataforma, e nao a venda de curso.
        //
        // Sem status separado de proposito: inadimplente e so "planexpiry no
        // passado", do mesmo jeito que entitlement::timeend ja decide
        // vencimento hoje. Um enum a mais so poderia divergir do timestamp.
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_marketplace_company');
        $field = new xmldb_field('planexpiry', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'planid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091701, 'local', 'marketplace');
    }

    if ($oldversion < 2026091702) {
        // A funcao archive_legacy_plans() cuida da ordem entre
        // renomear e semear - fazer isto aqui fora ja causou o 'pro' novo
        // nunca ser criado, medido ao vivo nesta mesma rodada.
        require_once(__DIR__ . '/install.php');
        local_marketplace_archive_legacy_plans();

        upgrade_plugin_savepoint(true, 2026091702, 'local', 'marketplace');
    }

    if ($oldversion < 2026091706) {
        // Guarda de idempotencia de refund_sale(): sem coluna propria, um
        // duplo clique ou dois admins agindo sobre o mesmo pagamento
        // estornavam duas vezes no gateway, porque record_refund() so roda
        // DEPOIS da chamada externa ja ter tido sucesso.
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_marketplace_sale');
        $field = new xmldb_field('refundedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'externalid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091706, 'local', 'marketplace');
    }

    if ($oldversion < 2026092500) {
        // Library da Bunny por empresa (Frente B, ADR-0014). Tabela nova, sem
        // dado a migrar - guarda so em field_exists/index_exists, nunca em
        // table_exists() para tabela do proprio plugin.
        //
        // securitykey e cdnhostname nascem NULOS de proposito: verificado ao
        // vivo em 25/09/2026 contra a API real da Bunny que a criacao de
        // library so devolve Id e ApiKey. O token de autenticacao do player e
        // o hostname da pull zone exigem um passo a mais (habilitar
        // PlayerTokenAuthenticationEnabled e resolver o PullZoneId), que fica
        // para o proximo sub-passo.
        //
        // webhooksecret e bunnylibraryid unico: e como o mod_bunnystream
        // identifica de qual empresa veio um POST no webhook, sem depender de
        // dominio ou IP - cada library tem o proprio segredo, gerado na
        // criacao (nunca um singleton como no plugin de referencia).
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_marketplace_library');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_marketplace_library');
        }

        upgrade_plugin_savepoint(true, 2026092500, 'local', 'marketplace');
    }

    return true;
}

/**
 * Traz o historico de vendas do paygw_mercadopago para a tabela neutra.
 *
 * So as linhas aprovadas e com pagamento do core registrado: uma preferencia
 * pendente ou recusada nunca foi uma venda, e trazer aquilo inflaria o
 * relatorio com dinheiro que nao entrou.
 *
 * @return int Quantas vendas foram trazidas.
 */
function upgrade_local_marketplace_backfill_sales(): int {
    global $DB;

    if (!$DB->get_manager()->table_exists('paygw_mercadopago')) {
        return 0;
    }

    $sql = "SELECT mp.id, mp.paymentid, mp.itemid, mp.feeamount, mp.mppaymentid, o.companyid
              FROM {paygw_mercadopago} mp
              JOIN {local_marketplace_offer} o ON o.id = mp.itemid
             WHERE mp.status = :status
               AND mp.paymentid IS NOT NULL
               AND mp.component = :component";
    $rows = $DB->get_records_sql($sql, ['status' => 'approved', 'component' => 'local_marketplace']);

    $now = time();
    $moved = 0;

    foreach ($rows as $row) {
        if ($DB->record_exists('local_marketplace_sale', ['paymentid' => $row->paymentid])) {
            continue;
        }

        $DB->insert_record('local_marketplace_sale', (object) [
            'paymentid' => (int) $row->paymentid,
            'offerid' => (int) $row->itemid,
            'companyid' => (int) $row->companyid,
            'feeamount' => (float) $row->feeamount,
            'externalid' => $row->mppaymentid ?: null,
            'usermodified' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $moved++;
    }

    return $moved;
}

/**
 * Cria uma linha em local_marketplace_account para cada conta ja existente.
 *
 * Fica fora da funcao de upgrade porque e o unico passo com logica de verdade,
 * e um passo de schema que faz duas coisas e dificil de reler quando falha.
 *
 * @return int Quantas contas foram adotadas.
 */
function upgrade_local_marketplace_adopt_accounts(): int {
    global $DB;

    // MLB e o codigo do Mercado Pago; BR e o nosso. A traducao mora aqui, e nao
    // no nucleo, porque e um dado historico: depois deste passo ninguem mais
    // precisa saber o que MLB significa.
    $sitetocountry = [
        'MLA' => 'AR',
        'MLB' => 'BR',
        'MLC' => 'CL',
        'MCO' => 'CO',
        'MLM' => 'MX',
        'MPE' => 'PE',
        'MLU' => 'UY',
    ];

    $platformsite = strtoupper((string) (get_config('paygw_mercadopago', 'platformsite') ?: 'MLB'));
    $fallback = $sitetocountry[$platformsite] ?? \local_marketplace\country::DEFAULT_COUNTRY;

    $now = time();
    $adopted = 0;

    $companies = $DB->get_records('local_marketplace_company', null, '', 'id, categoryid');
    foreach ($companies as $companyrow) {
        if (empty($companyrow->categoryid)) {
            continue;
        }
        $context = context_coursecat::instance((int) $companyrow->categoryid, IGNORE_MISSING);
        if (!$context) {
            continue;
        }

        $accounts = $DB->get_records('payment_accounts', ['contextid' => $context->id, 'archived' => 0]);
        foreach ($accounts as $account) {
            $country = $fallback;

            $config = $DB->get_field('payment_gateways', 'config', [
                'accountid' => $account->id,
                'gateway' => 'mercadopago',
            ]);
            if ($config) {
                $decoded = json_decode($config, true);
                if (!empty($decoded['siteid'])) {
                    $siteid = strtoupper((string) $decoded['siteid']);
                    $country = $sitetocountry[$siteid] ?? $fallback;
                }
            }

            // Uma empresa com duas contas no mesmo pais e um cadastro que nunca
            // deveria ter existido, e o indice unico agora impede. Fica a
            // primeira; a segunda segue no core, sem vinculo, e aparece na tela
            // de contas da empresa para alguem decidir o que fazer com ela.
            $taken = $DB->record_exists('local_marketplace_account', [
                'companyid' => $companyrow->id,
                'country' => $country,
            ]);
            $already = $DB->record_exists('local_marketplace_account', ['accountid' => $account->id]);
            if ($taken || $already) {
                continue;
            }

            $DB->insert_record('local_marketplace_account', (object) [
                'companyid' => $companyrow->id,
                'country' => $country,
                'accountid' => $account->id,
                'usermodified' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $adopted++;
        }
    }

    return $adopted;
}
