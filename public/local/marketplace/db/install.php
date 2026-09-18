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
 * Cria o papel de vendedor na instalacao do plugin.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Prepara a instalacao nova: tema por categoria, planos e os papeis de empresa.
 *
 * As capabilities dos papeis - inclusive a lista de proibicao que sustenta a
 * margem do plano Free - moram em \local_marketplace\roles, e nao aqui. O
 * db/upgrade.php chama o MESMO metodo: duas listas que precisam ficar iguais
 * viram, com o tempo, duas listas que divergem.
 *
 * @return void
 */
function xmldb_local_marketplace_install() {
    local_marketplace_require_category_themes();
    local_marketplace_seed_plans();

    \local_marketplace\roles::ensure();
}

/**
 * Liga o tema por categoria, do qual o tema por empresa depende.
 *
 * Nao e preferencia de administrador: e requisito do produto. O vinculo
 * empresa->tema e feito gravando o tema na categoria dela, e com
 * allowcategorythemes desligado o Moodle simplesmente ignora esse campo.
 *
 * O modo de falhar e silencioso, que e o pior: o vendedor escolhe o tema, a
 * tela confirma, e nada muda. Nenhum erro, nenhum log. Por isso a dependencia
 * e garantida em codigo, e nao deixada para um clique que alguem precisa
 * lembrar de dar em cada ambiente novo.
 *
 * @return void
 */
function local_marketplace_require_category_themes() {
    if (empty(get_config('moodle', 'allowcategorythemes'))) {
        set_config('allowcategorythemes', 1);
        theme_reset_static_caches();
    }
}

/**
 * Semeia os planos comerciais de uma instalacao nova.
 *
 * IDEMPOTENTE por shortname, e so INSERE: nunca faz update. A razao e que preco
 * muda por decisao comercial, e nao por deploy. Depois da instalacao, tudo se
 * ajusta em /local/marketplace/admin/plan_edit.php - se este seed atualizasse
 * as linhas, o proximo upgrade desfaria a tabela de precos do usuario sem aviso.
 *
 * Os valores sao os que a plataforma EXIBE em publico. Margem, custo de banda e
 * comparacao com concorrente nao entram aqui nem em nenhum arquivo versionado.
 * Sao iniciais e provisorios de proposito: a tela existe para corrigi-los sem
 * tocar em codigo.
 *
 * DOIS planos comerciais, nao tres - desenhado em 17/09/2026
 * (docs/ai-plans/2026-09-17-assinatura-saas-planos-start-e-pro.md), substitui
 * o Starter/Pro/Scale anterior. Start tem TRES linhas aqui (uma por tier de
 * mensalidade) porque `monthlyfee` e campo do PLANO, e nao do tier - nao ha
 * como uma so linha cobrar R$0, R$50 ou R$100 dependendo de quem assinou.
 *
 * @return int Quantos planos foram criados.
 */
function local_marketplace_seed_plans(): int {
    $planos = [
        [
            'shortname' => 'start_free',
            'name' => get_string('planstartfreename', 'local_marketplace'),
            'description' => get_string('planstartfreedesc', 'local_marketplace'),
            'monthlyfee' => 0,
            'commissionpct' => 10,
            'hostingmodel' => \local_marketplace\plan::HOSTING_NATIVE,
            'sortorder' => 10,
            // Faixa unica (maxprice nulo): a resolucao e do TIER contratado
            // pela empresa, nao mais do preco do curso - plan::max_resolution_for()
            // continua funcionando sem mudar de assinatura, so muda o que a
            // linha representa.
            'tiers' => [
                ['maxprice' => null, 'maxresolution' => '720p'],
            ],
        ],
        [
            'shortname' => 'start_50',
            'name' => get_string('planstart50name', 'local_marketplace'),
            'description' => get_string('planstart50desc', 'local_marketplace'),
            'monthlyfee' => 50,
            'commissionpct' => 10,
            'hostingmodel' => \local_marketplace\plan::HOSTING_NATIVE,
            'sortorder' => 20,
            'tiers' => [
                ['maxprice' => null, 'maxresolution' => '1080p'],
            ],
        ],
        [
            'shortname' => 'start_100',
            'name' => get_string('planstart100name', 'local_marketplace'),
            'description' => get_string('planstart100desc', 'local_marketplace'),
            'monthlyfee' => 100,
            'commissionpct' => 10,
            'hostingmodel' => \local_marketplace\plan::HOSTING_NATIVE,
            'sortorder' => 30,
            'tiers' => [
                ['maxprice' => null, 'maxresolution' => '4k'],
            ],
        ],
        [
            'shortname' => 'pro',
            'name' => get_string('planproname', 'local_marketplace'),
            'description' => get_string('planprodesc', 'local_marketplace'),
            'monthlyfee' => 97,
            'commissionpct' => 5,
            'hostingmodel' => \local_marketplace\plan::HOSTING_BYOS,
            'sortorder' => 40,
            // Sem faixas: no BYOS quem paga a banda e o produtor, entao nao ha
            // margem nossa para proteger.
            'tiers' => [],
        ],
    ];

    $created = 0;

    foreach ($planos as $dados) {
        if (\local_marketplace\plan::get_record_by_shortname($dados['shortname'])) {
            continue;
        }

        $tiers = $dados['tiers'];
        unset($dados['tiers']);

        $plan = new \local_marketplace\plan(0, (object) $dados);
        $plan->create();
        $created++;

        $ordem = 10;
        foreach ($tiers as $tier) {
            $tier['planid'] = (int) $plan->get('id');
            $tier['sortorder'] = $ordem;
            (new \local_marketplace\plan_tier(0, (object) $tier))->create();
            $ordem += 10;
        }
    }

    return $created;
}

/**
 * Arquiva os tres planos comerciais anteriores (Starter, Pro, Scale) e
 * semeia os quatro novos (Start/PRO), migrando qualquer empresa que ainda
 * aponte para um dos antigos.
 *
 * So existe para o UPGRADE de uma instalacao que ja tinha os planos antigos -
 * uma instalacao nova nunca os semeia (local_marketplace_seed_plans() ja
 * nasce com Start/PRO direto, sem passar por aqui). Nenhum dos tres antigos
 * mapeia 1:1 pro desenho novo (nenhuma comissao bate, e Scale de 0% nao tem
 * equivalente), entao ARQUIVA em vez de reaproveitar - plan::STATUS_ARCHIVED
 * preserva o historico, e a empresa que ainda apontava pra um deles vira o
 * mais proximo por modelo de hospedagem.
 *
 * A ORDEM AQUI DENTRO NAO E NEGOCIAVEL, e foi medida ao vivo: renomear
 * ANTES de semear. O plano arquivado nao e removido, entao o shortname
 * 'pro' continua ocupado pelo antigo (3,9%) ate ele ser renomeado - semear
 * primeiro faz local_marketplace_seed_plans() achar 'pro' "ja existente" e
 * pular a criacao do novo (5%) em silencio. So depois de o shortname estar
 * livre e que da pra migrar as empresas para o id do plano novo.
 *
 * @return void
 */
function local_marketplace_archive_legacy_plans(): void {
    global $DB;

    $mapa = [
        'starter' => 'start_free',
        'scale' => 'start_free',
        'pro' => 'pro',
    ];

    $idsantigos = [];

    foreach (array_keys($mapa) as $antigo) {
        $planoantigo = \local_marketplace\plan::get_record_by_shortname($antigo);
        if (!$planoantigo || $planoantigo->get('status') === \local_marketplace\plan::STATUS_ARCHIVED) {
            continue;
        }

        $idsantigos[$antigo] = (int) $planoantigo->get('id');

        $planoantigo->set('shortname', $antigo . '_legado');
        $planoantigo->set('status', \local_marketplace\plan::STATUS_ARCHIVED);
        $planoantigo->update();
    }

    local_marketplace_seed_plans();

    foreach ($mapa as $antigo => $novo) {
        if (!isset($idsantigos[$antigo])) {
            continue;
        }

        $planonovo = \local_marketplace\plan::get_record_by_shortname($novo);
        if ($planonovo) {
            $DB->set_field('local_marketplace_company', 'planid', (int) $planonovo->get('id'), [
                'planid' => $idsantigos[$antigo],
            ]);
        }
    }
}
