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

use core_course_category;
use moodle_exception;

/**
 * Operacoes do marketplace que envolvem mais de uma entidade.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Cria a empresa e provisiona tudo que ela precisa para operar.
     *
     * Uma empresa sem categoria seria uma linha inerte: e a categoria que da
     * a ela um contexto onde atribuir o papel, um lugar para os cursos e um
     * tema proprio. Por isso o provisionamento e parte da criacao, e nao um
     * passo separado que alguem poderia esquecer.
     *
     * Tudo numa transacao: uma empresa com categoria mas sem dono, ou com dono
     * mas sem papel atribuido, seria pior que nenhuma empresa - apareceria na
     * lista e nao funcionaria.
     *
     * @param object $data name, shortname, cnpj, themename, hostname
     * @param int $ownerid Usuario que passa a ser dono.
     * @return company
     */
    public static function create_company(object $data, int $ownerid): company {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $company = new company();
            $company->set('name', $data->name);
            $company->set('shortname', $data->shortname);
            $company->set('cnpj', !empty($data->cnpj) ? $data->cnpj : null);
            $company->set('commissionpct', self::commission_input($data->commissionpct ?? null));
            $company->set('commissionbase', self::commission_base_input($data->commissionbase ?? null));
            $company->set('planid', !empty($data->planid) ? (int) $data->planid : null);
            $company->set('pagetitle', !empty($data->pagetitle) ? $data->pagetitle : null);
            $company->set('pageintro', $data->pageintro ?? null);
            $company->set('pageaccent', !empty($data->pageaccent) ? $data->pageaccent : null);
            $company->set('themename', !empty($data->themename) ? $data->themename : null);
            $company->set('hostname', !empty($data->hostname) ? $data->hostname : null);
            $company->create();

            $category = self::create_category($company);
            $company->set('categoryid', $category->id);
            $company->update();

            $owner = new member();
            $owner->set('companyid', $company->get('id'));
            $owner->set('userid', $ownerid);
            $owner->set('memberrole', member::ROLE_OWNER);
            $owner->create();

            self::assign_member_role($company, $ownerid, member::ROLE_OWNER);
            self::apply_theme($company);
            self::create_payment_account($company, $data->country ?? self::default_country());

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // Fora da transacao de proposito: escrever arquivo nao tem rollback.
        // Gerar antes do commit deixaria o mapa anunciando um dominio de
        // empresa que nao chegou a existir.
        if ($company->get('hostname')) {
            self::regenerate_domain_map();
        }

        return $company;
    }

    /**
     * Percentual de comissao que vale para uma oferta.
     *
     * Hierarquia, do mais especifico para o mais geral:
     *
     *   1. politica do CURSO, quando a oferta libera um curso so
     *   2. comissao negociada com a EMPRESA
     *   3. comissao do PLANO contratado pela empresa
     *   4. padrao do SITE
     *
     * A politica de curso so entra em oferta de curso unico, e a razao e que
     * nao existe resposta correta para um combo: tres cursos com percentuais
     * diferentes nao produzem um percentual do pacote. Pegar o maior seria
     * predatorio, o menor seria arbitrario, e a media seria um numero que
     * ninguem negociou. Entao no combo vale a empresa, que e o que o vendedor
     * de fato acordou.
     *
     * A linha de politica so existe quando alguem a criou de proposito - nada
     * cria por padrao. Por isso a existencia dela conta como intencao, e nao
     * ha ambiguidade entre "definido como 25" e "nunca definido".
     *
     * @param offer $offer
     * @return float Percentual entre 0 e 100.
     */
    public static function resolve_commission_percent(offer $offer): float {
        return self::resolve_commission($offer)->percent;
    }

    /**
     * Termos completos da comissao: percentual, base de calculo e origem.
     *
     * Mesma cadeia do percentual, com uma regra a mais: **a base sai do MESMO
     * degrau que deu o percentual**. Resolver as duas coisas em cadeias
     * independentes produziria combinacoes que ninguem contratou - taxa do
     * plano com base do site, por exemplo - e o parceiro veria um numero que
     * nao corresponde a nenhum acordo existente.
     *
     * Degrau que nao declara base (coluna nula) herda a base do site. E a
     * diferenca entre "este contrato define a base" e "este contrato so define
     * a taxa", que sem o nulo seriam indistinguiveis.
     *
     * @param offer $offer
     * @return commission
     */
    public static function resolve_commission(offer $offer): commission {
        if ($offer->get('offertype') === offer::TYPE_SINGLE) {
            $courseids = $offer->get_course_ids();
            if (count($courseids) === 1) {
                $policy = course_policy::get_by_course((int) reset($courseids));
                if ($policy) {
                    return new commission(
                        (float) $policy->get('commissionpct'),
                        $policy->get('commissionbase'),
                        commission::SOURCE_POLICY
                    );
                }
            }
        }

        $company = company::get_record(['id' => (int) $offer->get('companyid')]);
        if ($company) {
            // A empresa continua vencendo o plano. "Comissao negociada com ESTA
            // empresa" e mais especifico que "padrao da classe de empresas", e e
            // o que garante que ligar planos hoje nao mexa em nenhuma venda que
            // ja acontece. Quem quiser que o plano governe apaga o valor
            // negociado - e a tela de empresas mostra de onde o numero veio.
            $negotiated = $company->get_commission_percent();
            if ($negotiated !== null) {
                return new commission(
                    $negotiated,
                    $company->get('commissionbase'),
                    commission::SOURCE_COMPANY
                );
            }

            $plan = $company->get_plan();
            if ($plan && $plan->get('status') === plan::STATUS_ACTIVE) {
                return new commission(
                    (float) $plan->get('commissionpct'),
                    $plan->get('commissionbase'),
                    commission::SOURCE_PLAN
                );
            }
        }

        return new commission(
            self::default_commission_percent(),
            commission::default_base(),
            commission::SOURCE_SITE
        );
    }

    /**
     * Percentual de comissao para um item, com o guarda de componente embutido.
     *
     * E este o ponto que os gateways chamam. Cada um repetia o mesmo bloco -
     * checar o componente, checar se a classe existe, buscar a oferta, cair num
     * padrao quando nao acha - e tres copias do mesmo cuidado sao tres lugares
     * onde ele pode divergir.
     *
     * Devolve o padrao do site quando o item nao e do marketplace: um gateway
     * pode ser usado para pagar taxa de matricula do enrol_fee, e ali nao ha
     * oferta nem empresa.
     *
     * @param string $component Componente do core_payment, ex.: 'local_marketplace'.
     * @param int $itemid
     * @return float Percentual entre 0 e 100.
     */
    public static function commission_for(string $component, int $itemid): float {
        return self::commission_terms_for($component, $itemid)->percent;
    }

    /**
     * Termos completos da comissao para um item.
     *
     * E este o ponto que os gateways passam a chamar: eles precisam da base
     * para decidir COMO mandar o split, e nao so de quanto.
     *
     * @param string $component Componente do core_payment.
     * @param int $itemid
     * @return commission
     */
    public static function commission_terms_for(string $component, int $itemid): commission {
        $fallback = new commission(
            self::default_commission_percent(),
            commission::default_base(),
            commission::SOURCE_SITE
        );

        if ($component !== 'local_marketplace') {
            return $fallback;
        }

        $offer = offer::get_record(['id' => $itemid]);
        if (!$offer) {
            return $fallback;
        }

        return self::resolve_commission($offer);
    }

    /**
     * O item e uma assinatura, e com que periodicidade cobra?
     *
     * Existe pelo mesmo motivo do commission_terms_for(): o gateway precisa
     * decidir se cria uma cobranca avulsa ou uma assinatura, e nao pode saber
     * o que e uma "oferta". Ele pergunta, e o marketplace responde.
     *
     * O core_payment nao tem conceito de assinatura - ele conhece pagamento
     * unico. Por isso a informacao vem daqui, e nao do payable.
     *
     * Devolve null para qualquer coisa que nao seja assinatura, inclusive
     * componente de fora: o gateway trata null como "cobranca avulsa", que e o
     * comportamento que ele ja tinha antes desta funcao existir.
     *
     * @param string $component Componente do core_payment.
     * @param int $itemid
     * @return \stdClass|null days (intervalo de cobranca) e maxcycles, ou null
     */
    public static function recurrence_for(string $component, int $itemid): ?\stdClass {
        if ($component !== 'local_marketplace') {
            return null;
        }

        $offer = offer::get_record(['id' => $itemid]);
        if (!$offer || $offer->get('accessmode') !== offer::ACCESS_RECURRING) {
            return null;
        }

        // O intervalo de COBRANCA, e nao o de acesso. Sao campos separados de
        // proposito - ver offer::calculate_expiry() -, e confundi-los aqui
        // tiraria a carencia entre o vencimento da fatura e o corte do acesso.
        $days = (int) $offer->get('billingdays');
        if ($days <= 0) {
            $days = (int) $offer->get('accessdays');
        }
        if ($days <= 0) {
            // Assinatura sem periodicidade nao e assinatura. Melhor cair na
            // cobranca avulsa do que criar no gateway algo que cobra sozinho
            // num intervalo que ninguem definiu.
            return null;
        }

        return (object) [
            'days' => $days,
            'maxcycles' => (int) $offer->get('maxcycles'),
        ];
    }

    /**
     * Comissao padrao do site.
     *
     * Vive nas settings do local_marketplace, e nao nas de um gateway. Ate a
     * versao anterior o nucleo lia get_config('paygw_mercadopago', ...) - o que
     * fazia a comissao do marketplace inteiro depender de um plugin de
     * fornecedor estar instalado, e nao ter resposta quando houvesse tres.
     *
     * A comparacao com false, e nao o ?:, e o que preserva uma isencao: com
     * ?: um 0% configurado de proposito voltaria a 25 em silencio.
     *
     * @return float
     */
    public static function default_commission_percent(): float {
        $configured = get_config('local_marketplace', 'defaultfeepercent');
        if ($configured === false || $configured === '' || !is_numeric($configured)) {
            return 25.0;
        }

        return self::clamp((float) $configured);
    }

    /**
     * Registra uma venda concluida.
     *
     * Chamado pelo gateway depois de helper::save_payment(), porque so ele sabe
     * quanto de comissao foi de fato enviado - e nao da para recalcular a
     * partir do percentual: cada gateway deduz as proprias taxas numa ordem
     * diferente, e 25% do bruto nao e o que caiu na conta da plataforma.
     *
     * Idempotente: o webhook do gateway reenvia notificacao, e registrar a
     * mesma venda duas vezes dobraria o faturamento no relatorio.
     *
     * O componente entra como parametro pelo mesmo motivo de commission_for():
     * sem ele, um pagamento de enrol_fee cujo itemid coincidisse com um offerid
     * viraria uma venda que nunca houve. O guarda fica aqui, e nao repetido em
     * cada gateway, para nao existir em tres copias que podem divergir.
     *
     * @param string $component Componente do core_payment.
     * @param int $paymentid payments.id do core.
     * @param int $offerid
     * @param float $feeamount Comissao em moeda, ja calculada pelo gateway.
     * @param string $externalid Id da transacao no gateway.
     * @param commission|null $terms Termos APLICADOS. Nulo resolve na hora, e e
     *                               so para chamador antigo: o certo e o gateway
     *                               passar o que ele de fato usou.
     * @return sale|null Nulo quando o pagamento nao e de uma oferta.
     */
    public static function record_sale(
        string $component,
        int $paymentid,
        int $offerid,
        float $feeamount,
        string $externalid = '',
        ?commission $terms = null
    ): ?sale {
        if ($component !== 'local_marketplace') {
            return null;
        }

        $existing = sale::get_record(['paymentid' => $paymentid]);
        if ($existing) {
            return $existing;
        }

        $offer = offer::get_record(['id' => $offerid]);
        if (!$offer) {
            return null;
        }

        // A FOTO dos termos. Sem ela, uma venda de seis meses atras so pode ser
        // explicada relendo a configuracao de hoje - e o percentual da empresa,
        // o plano dela e o padrao do site mudam. O relatorio passaria a contar
        // uma historia diferente da que o extrato do gateway conta, e a que
        // muda seria a nossa.
        $terms = $terms ?? self::resolve_commission($offer);

        $record = new sale();
        $record->set('paymentid', $paymentid);
        $record->set('offerid', $offerid);
        $record->set('companyid', (int) $offer->get('companyid'));
        $record->set('feeamount', $feeamount);
        $record->set('feepercent', $terms->percent);
        $record->set('feebase', $terms->base);
        $record->set('feesource', $terms->source);
        $record->set('externalid', $externalid !== '' ? $externalid : null);
        $record->create();

        return $record;
    }

    /**
     * Mantem o percentual dentro de 0 a 100.
     *
     * Um valor fora da faixa viraria marketplace_fee maior que o proprio preco,
     * e o Mercado Pago recusaria a preferencia - com o aluno ja no checkout.
     *
     * @param float $value
     * @return float
     */
    protected static function clamp(float $value): float {
        return max(0.0, min(100.0, $value));
    }

    /**
     * Interpreta o campo de comissao vindo do formulario.
     *
     * Vazio vira NULO - herda o site. "0" vira zero - isencao negociada. Usar
     * empty() aqui trataria os dois como a mesma coisa, e um parceiro isento
     * passaria a pagar a comissao padrao sem ninguem ter mudado nada.
     *
     * @param mixed $value
     * @return float|null
     */
    protected static function commission_input($value): ?float {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return self::clamp((float) $value);
    }

    /**
     * Traduz a base vinda de formulario.
     *
     * O vazio vira NULO, e nao a base padrao: nulo significa "herda o site" e e
     * a informacao que permite o contrato acompanhar uma mudanca de politica da
     * plataforma. Gravar 'gross' congelaria a base naquele valor para sempre,
     * sem ninguem ter pedido isso.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function commission_base_input($value): ?string {
        if (!is_string($value) || !in_array($value, commission::bases(), true)) {
            return null;
        }

        return $value;
    }

    /**
     * Altera os dados cadastrais de uma empresa.
     *
     * O atalho NAO muda. Ele vira o idnumber da categoria, entra nas URLs da
     * vitrine e do painel, e pode ja estar em link divulgado pelo vendedor.
     * Trocar exigiria renomear o idnumber e quebraria os links antigos sem
     * aviso - custo que nao se justifica para um campo cosmetico.
     *
     * O dono tambem nao muda aqui: quem responde pela empresa se resolve na
     * tela de vendedores, onde da para promover outro antes de rebaixar o atual.
     *
     * @param company $company
     * @param object $data name, cnpj, themename, hostname
     * @return company
     */
    public static function update_company(company $company, object $data): company {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $hostbefore = $company->get('hostname');

        try {
            $renamed = ($company->get('name') !== $data->name);

            $company->set('name', $data->name);
            $company->set('cnpj', !empty($data->cnpj) ? $data->cnpj : null);
            $company->set('commissionpct', self::commission_input($data->commissionpct ?? null));
            $company->set('commissionbase', self::commission_base_input($data->commissionbase ?? null));
            $company->set('planid', !empty($data->planid) ? (int) $data->planid : null);
            $company->set('pagetitle', !empty($data->pagetitle) ? $data->pagetitle : null);
            $company->set('pageintro', $data->pageintro ?? null);
            $company->set('pageaccent', !empty($data->pageaccent) ? $data->pageaccent : null);
            $company->set('themename', !empty($data->themename) ? $data->themename : null);
            $company->set('hostname', !empty($data->hostname) ? $data->hostname : null);
            $company->update();

            // A categoria carrega o nome da empresa. Deixar de renomear faria a
            // arvore de cursos divergir do cadastro, e e a arvore que o aluno ve.
            if ($renamed && $company->get('categoryid')) {
                $category = \core_course_category::get((int) $company->get('categoryid'), IGNORE_MISSING, true);
                if ($category) {
                    $category->update(['name' => $data->name]);
                }
            }

            self::apply_theme($company);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // Regenera quando o dominio ENTROU, SAIU ou mudou. Comparar com o
        // valor anterior evita reescrever o arquivo a cada troca de nome ou
        // de tema, que nao afetam o mapa. Suspender tambem nao afeta: o mapa
        // lista dominio existente, e a situacao e conferida no banco.
        if ($hostbefore !== $company->get('hostname')) {
            self::regenerate_domain_map();
        }

        return $company;
    }

    /**
     * Regenera o mapa Host -> empresa lido pelo config.php.
     *
     * O config.php roda ANTES do lib/setup.php, entao $DB ainda nao existe e
     * consultar o banco ali e impossivel. Por isso o mapa e um arquivo PHP
     * gerado: escrito quando um dominio muda, lido a cada requisicao.
     *
     * Escrito num temporario e movido por rename(), que e atomico no mesmo
     * sistema de arquivos. Escrever direto no destino deixaria uma janela em
     * que o config.php poderia incluir um arquivo pela metade - e um
     * $CFG->wwwroot truncado derruba o site inteiro, nao so a empresa.
     *
     * @return int Quantos dominios entraram no mapa.
     */
    public static function regenerate_domain_map(): int {
        global $CFG, $DB;

        // TODA empresa com dominio entra, inclusive a suspensa. O arquivo
        // responde "este dominio existe?", que e fronteira de SEGURANCA: sem
        // ele, o Host da requisicao definiria o wwwroot, e um atacante mandando
        // "Host: evil.com" faria o Moodle gerar todo link apontando para la -
        // inclusive o de redefinicao de senha, que sai por e-mail.
        //
        // "Esta suspensa?" e outra pergunta, respondida no banco pelo
        // after_config, onde da para mostrar uma pagina explicando. Deixar a
        // suspensao aqui faria o dominio cair no site padrao em silencio, e
        // acoplaria suspender a regenerar arquivo: se a regeneracao falhasse, a
        // suspensao nao surtiria efeito.
        $rows = $DB->get_records_select(
            company::TABLE,
            "hostname IS NOT NULL AND hostname <> ''",
            [],
            'hostname',
            'id, shortname, hostname'
        );

        $map = [];
        foreach ($rows as $row) {
            // O host e a chave e vem do cadastro; normalizar aqui evita que
            // "Meu.Site.com" no formulario nunca case com o Host do navegador,
            // que chega sempre em minusculas.
            $map[strtolower($row->hostname)] = [
                'wwwroot' => 'https://' . strtolower($row->hostname),
                'company' => $row->shortname,
            ];
        }

        $file = $CFG->dataroot . '/marketplace_domains.php';
        $tmp = $file . '.' . getmypid() . '.tmp';

        $php = "<?php\n"
            . "// GERADO por local_marketplace. Nao edite: sera sobrescrito.\n"
            . "// Ultima geracao: " . date('c') . "\n"
            . "return " . var_export($map, true) . ";\n";

        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new moodle_exception('errordomainmap', 'local_marketplace');
        }
        @chmod($tmp, $CFG->filepermissions ?? 0666);
        rename($tmp, $file);

        return count($map);
    }

    /**
     * Cria a categoria de cursos da empresa.
     *
     * @param company $company
     * @return \stdClass
     */
    protected static function create_category(company $company): \stdClass {
        $category = core_course_category::create([
            'name' => $company->get('name'),
            'idnumber' => 'marketplace_' . $company->get('shortname'),
            'description' => '',
            'parent' => 0,
        ]);
        return $category->get_db_record();
    }

    /**
     * Cria a payment account da empresa para um pais, no contexto da categoria.
     *
     * E o contexto que faz o vendedor conseguir administrar a propria conta
     * sem enxergar as das outras empresas: moodle/payment:manageaccounts e
     * CONTEXT_COURSE, e helper::get_payment_accounts_menu() resolve pelos
     * contextos-pai.
     *
     * A conta nasce SEM gateway. E isso que mantem o portao de venda fechado
     * ate o vendedor concluir o vinculo: account::is_available() exige ao
     * menos um gateway habilitado.
     *
     * O pais entra no idnumber porque uma empresa que vende no Brasil e na
     * Argentina tem duas contas no mesmo contexto, e sem o sufixo elas
     * colidiriam num identificador que deveria distingui-las.
     *
     * Devolve a conta existente quando ja ha uma para aquele pais - criar a
     * segunda deixaria a empresa com duas contas concorrendo pelo mesmo
     * mercado, e o indice unico recusaria o vinculo no meio do caminho.
     *
     * @param company $company
     * @param string $country ISO-3166 alpha-2.
     * @return \core_payment\account
     */
    public static function create_payment_account(company $company, string $country): \core_payment\account {
        $country = country::normalize($country);

        $existing = $company->get_payment_account($country);
        if ($existing) {
            return $existing;
        }

        $context = $company->get_context();

        $account = new \core_payment\account();
        $account->set('name', $company->get('name') . ' (' . $country . ')');
        $account->set('idnumber', 'marketplace_' . $company->get('shortname') . '_' . strtolower($country));
        $account->set('contextid', $context->id);
        $account->set('enabled', true);
        $account->create();

        $link = new company_account();
        $link->set('companyid', (int) $company->get('id'));
        $link->set('country', $country);
        $link->set('accountid', (int) $account->get('id'));
        $link->create();

        return $account;
    }

    /**
     * A payment account da PROPRIA PLATAFORMA para um pais, criando se
     * precisar.
     *
     * Diferente de create_payment_account(): sem empresa, sem contexto de
     * categoria, sem linha em company_account. E a conta que recebe a
     * assinatura SaaS (Start/PRO) que a empresa parceira paga a plataforma -
     * o oposto de create_payment_account(), que cria a conta que recebe a
     * venda de curso.
     *
     * Vive no contexto do SITE, e nao de empresa nenhuma, porque nao e
     * propriedade de empresa nenhuma. O idnumber com o prefixo 'platform_' e
     * o que is_platform_account() usa para reconhecer esta conta depois -
     * inclusive nos gateways que hoje recusam vincular a propria carteira da
     * plataforma como "vendedor" (guarda pensada pra venda de curso, que nao
     * se aplica aqui).
     *
     * IDEMPOTENTE: busca pelo idnumber antes de criar.
     *
     * @param string $country ISO-3166 alpha-2.
     * @return \core_payment\account
     */
    public static function get_or_create_platform_account(string $country): \core_payment\account {
        $country = country::normalize($country);
        $idnumber = 'platform_' . strtolower($country);

        $existing = \core_payment\account::get_record(['idnumber' => $idnumber]);
        if ($existing) {
            return $existing;
        }

        $account = new \core_payment\account();
        $account->set('name', get_string('platformaccountname', 'local_marketplace', $country));
        $account->set('idnumber', $idnumber);
        $account->set('contextid', \context_system::instance()->id);
        $account->set('enabled', true);
        $account->create();

        return $account;
    }

    /**
     * Esta conta de pagamento e a da propria plataforma?
     *
     * So alcancavel por quem ja tem moodle/payment:manageaccounts (as telas
     * de administracao de conta) - nao e superficie que uma empresa consiga
     * atacar forjando um idnumber, porque nenhuma tela deixa a empresa
     * escolher o proprio idnumber.
     *
     * @param int $accountid
     * @return bool
     */
    public static function is_platform_account(int $accountid): bool {
        if ($accountid <= 0) {
            return false;
        }

        // Get_record() devolve false, e nao null, quando nao acha - por isso
        // a checagem explicita antes de chamar get().
        $account = \core_payment\account::get_record(['id' => $accountid]);
        if (!$account) {
            return false;
        }

        return str_starts_with((string) $account->get('idnumber'), 'platform_');
    }

    /**
     * Gateways instalados e habilitados que conseguem receber neste pais.
     *
     * A pergunta e feita a cada gateway, nao a uma lista nossa. Um gateway novo
     * passa a aparecer aqui so por declarar a moeda que atende - nada no nucleo
     * precisa saber o nome dele. Foi o oposto disso que amarrou a tela da
     * empresa ao Mercado Pago por um literal.
     *
     * @param string $country ISO-3166 alpha-2.
     * @return string[] Nomes de gateway, ex.: ['asaas', 'mercadopago'].
     */
    public static function gateways_for_country(string $country): array {
        $currency = country::currency_for($country);
        if ($currency === '') {
            return [];
        }

        $out = [];
        foreach (array_keys(\core\plugininfo\paygw::get_enabled_plugins()) as $name) {
            $classname = '\paygw_' . $name . '\gateway';
            $currencies = \component_class_callback($classname, 'get_supported_currencies', [], []);
            if (in_array($currency, $currencies, true)) {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * A fatura em aberto do proximo ciclo, perguntada aos gateways.
     *
     * O aviso de vencimento mandava o aluno para a VITRINE, e o que ele precisa
     * e da FATURA. Com boleto a diferenca e grande: a cobranca do ciclo ja nasce
     * pronta e com linha digitavel, e o aluno estava sendo mandado para uma tela
     * onde teria que comprar de novo.
     *
     * Devolve null quando nao ha assinatura, quando o gateway nao sabe
     * responder, ou quando a rede falha. Quem chama trata a ausencia como
     * "mostre o caminho antigo" - nem o e-mail nem a tela podem quebrar porque
     * o gateway piscou.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return array|null url, duedate, value e line
     */
    public static function pending_invoice_for(string $component, int $itemid, int $userid): ?array {
        foreach (self::billing_capable_gateways() as $name) {
            $classname = '\paygw_' . $name . '\gateway';
            try {
                $fatura = \component_class_callback(
                    $classname,
                    'pending_invoice',
                    [$component, $itemid, $userid],
                    null
                );
            } catch (\Throwable $e) {
                continue;
            }
            if (is_array($fatura) && !empty($fatura['url'])) {
                return $fatura;
            }
        }

        return null;
    }

    /**
     * Endereco para trocar a forma de pagamento de uma assinatura por cartao
     * guardado, quando o gateway que a cobra oferece essa troca.
     *
     * SO FAZ SENTIDO PARA QUEM COBRA POR FATURA (Pix, boleto): quem ja paga
     * com cartao nao tem para onde trocar, e o gateway que nao implementa o
     * metodo simplesmente nao aparece aqui - o mesmo desenho de
     * pending_invoice_for(), e pela mesma razao: o nucleo continua sem saber
     * o nome de gateway nenhum.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return string|null
     */
    public static function switch_to_card_url_for(string $component, int $itemid, int $userid): ?string {
        foreach (self::billing_capable_gateways() as $name) {
            $classname = '\paygw_' . $name . '\gateway';
            try {
                $url = \component_class_callback(
                    $classname,
                    'switch_to_card_url',
                    [$component, $itemid, $userid],
                    null
                );
            } catch (\Throwable $e) {
                continue;
            }
            if (!empty($url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Por qual meio uma assinatura esta sendo cobrada (cartao, Pix, boleto),
     * ou null quando nao ha assinatura ou o gateway nao sabe responder.
     *
     * Mesmo desenho de pending_invoice_for(): o nucleo pergunta a cada
     * gateway habilitado e fica com a primeira resposta, sem saber o nome de
     * nenhum. Serve tanto para o admin gerenciar (report.php) quanto para o
     * aluno so consultar (mysubscriptions.php) - nenhum dos dois ESCOLHE o
     * meio aqui, so veem qual esta valendo.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @return string|null
     */
    public static function payment_method_for(string $component, int $itemid, int $userid): ?string {
        foreach (self::billing_capable_gateways() as $name) {
            $classname = '\paygw_' . $name . '\gateway';
            try {
                $metodo = \component_class_callback(
                    $classname,
                    'payment_method',
                    [$component, $itemid, $userid],
                    null
                );
            } catch (\Throwable $e) {
                continue;
            }
            if ($metodo !== null && $metodo !== '') {
                return $metodo;
            }
        }

        return null;
    }

    /**
     * Motivo pelo qual esta venda nao pode ser estornada.
     *
     * Existe para a TELA: e com isto que o botao some, em vez de aparecer e
     * falhar no clique de quem esta resolvendo um problema. Quem decide e o
     * gateway, porque as regras sao dele - assinatura no meio do ciclo,
     * cobranca ainda nao liquidada, estorno ja feito.
     *
     * @param int $paymentid Registro em {payments}
     * @return string Chave de string do impedimento, ou vazio quando pode
     */
    public static function refund_blocker(int $paymentid): string {
        global $DB;

        $pagamento = $DB->get_record('payments', ['id' => $paymentid]);
        if (!$pagamento) {
            return 'errorrefundunknown';
        }

        $classname = '\paygw_' . $pagamento->gateway . '\gateway';

        // Gateway que nao implementa estorno responde o padrao, e o padrao e
        // "nao da" - melhor esconder o botao do que oferecer o que nao existe.
        return (string) \component_class_callback($classname, 'refund_blocker', [$paymentid], 'errorrefundunknown');
    }

    /**
     * Estorna uma venda no gateway que a cobrou, e revoga o acesso.
     *
     * O gateway que cobrou e descoberto pela coluna do {payments}, e nao
     * escolhido aqui: o nucleo continua sem saber o nome de nenhum. Quem nao
     * implementa cancel_recurring/refund simplesmente nao responde, e o
     * callback devolve o padrao.
     *
     * A REVOGACAO SO ACONTECE SE O DINHEIRO VOLTOU. Revogar antes deixaria o
     * aluno sem curso e sem reembolso caso a chamada externa falhasse - o pior
     * dos dois lados. Por isso a ordem e gateway primeiro, direito depois.
     *
     * @param int $paymentid Registro em {payments}
     * @return bool Verdadeiro quando o dinheiro voltou e o acesso caiu.
     */
    public static function refund_sale(int $paymentid): bool {
        global $DB;

        $pagamento = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);
        $classname = '\paygw_' . $pagamento->gateway . '\gateway';

        $estornou = \component_class_callback($classname, 'refund', [$paymentid], false);
        if (!$estornou) {
            return false;
        }

        self::record_refund($paymentid);

        return true;
    }

    /**
     * Registra que uma venda foi estornada, e revoga o acesso.
     *
     * ESTORNO REVOGA, e essa e a diferenca em relacao ao cancelamento. Cancelar
     * para de cobrar e deixa o aluno usar o que ja pagou; estornar devolve o
     * dinheiro daquele periodo, entao o periodo tambem volta.
     *
     * Ate 09/09/2026 este projeto tratava revogacao como ato exclusivamente
     * manual - "revogar acesso e decisao de negocio, nunca por automacao", como
     * ainda diz o webhook do Asaas. Continua valendo para o que chega de fora:
     * um PAYMENT_REFUNDED disparado no painel do gateway NAO revoga nada aqui,
     * porque ninguem da plataforma decidiu. O que revoga e o estorno feito POR
     * AQUI, por quem tem a capability - a decisao existe, e e humana.
     *
     * A venda fica no historico. Apagar a linha esconderia o dinheiro que
     * entrou e saiu, e o relatorio precisa dos dois lados.
     *
     * @param int $paymentid Registro em {payments}
     * @return bool Verdadeiro se algum direito foi revogado agora.
     */
    public static function record_refund(int $paymentid): bool {
        global $DB;

        $venda = $DB->get_record('local_marketplace_sale', ['paymentid' => $paymentid]);
        if (!$venda) {
            return false;
        }

        $pagamento = $DB->get_record('payments', ['id' => $paymentid]);
        if (!$pagamento) {
            return false;
        }

        $revogou = false;
        $direitos = entitlement::get_records([
            'userid' => (int) $pagamento->userid,
            'offerid' => (int) $venda->offerid,
        ]);

        foreach ($direitos as $direito) {
            if ($direito->get('status') === entitlement::STATUS_CANCELLED) {
                continue;
            }
            $direito->revoke();
            $revogou = true;
        }

        // Sem o direito, a matricula tem que cair junto - senao o aluno recebe
        // o dinheiro de volta e continua no curso ate o cron passar.
        if ($revogou) {
            $plugin = enrol_get_plugin('marketplace');
            if ($plugin) {
                $plugin->sync_user((int) $pagamento->userid);
            }
        }

        return $revogou;
    }

    /**
     * Gateways a quem perguntar sobre cobranca ja existente.
     *
     * INSTALADOS, e nao habilitados, e a diferenca vale dinheiro.
     *
     * Habilitar governa a venda NOVA: gateway desligado nao aparece no
     * checkout. Nao governa o que ja foi vendido - uma assinatura criada
     * enquanto ele estava ligado CONTINUA COBRANDO depois de desligado, porque
     * quem cobra e o gateway, e nao o Moodle.
     *
     * Perguntando so aos habilitados, desligar o Asaas deixaria toda assinatura
     * dele cobrando para sempre, e o cancelamento do aluno nao faria nada - sem
     * erro e sem log. Visto acontecer em 09/09/2026, na prova do ciclo curto.
     *
     * @return string[] Nomes curtos dos gateways instalados.
     */
    public static function billing_capable_gateways(): array {
        return array_keys(\core_plugin_manager::instance()->get_plugins_of_type('paygw'));
    }

    /**
     * Pede aos gateways que parem de cobrar esta assinatura.
     *
     * Cancelar no Moodle marcava so o norenew. Enquanto nao havia assinatura de
     * verdade em gateway nenhum isso bastava - nao havia o que parar. Desde que
     * o Asaas passou a criar assinatura que cobra sozinha, marcar a coluna e
     * deixar o gateway cobrando seria tirar dinheiro de quem pediu para sair.
     *
     * O nucleo continua sem saber o nome de gateway nenhum: pergunta a cada um
     * habilitado, do mesmo jeito que faz para descobrir moedas. Gateway que nao
     * tem assinatura simplesmente nao implementa o metodo, e o callback devolve
     * o padrao.
     *
     * Falha de um gateway nao impede os outros nem o cancelamento em si: o
     * aluno pediu para sair, e isso vale mesmo que a chamada externa caia. O
     * que sobra e uma cobranca a mais para estornar, visivel no relatorio - bem
     * melhor que uma tela que recusa o cancelamento.
     *
     * @param string $component Componente do core_payment.
     * @param int $itemid
     * @param int $userid
     * @return string[] Gateways que confirmaram ter cancelado algo.
     */
    public static function stop_recurring_billing(string $component, int $itemid, int $userid): array {
        $parados = [];

        foreach (self::billing_capable_gateways() as $name) {
            $classname = '\paygw_' . $name . '\gateway';
            try {
                $parou = \component_class_callback(
                    $classname,
                    'cancel_recurring',
                    [$component, $itemid, $userid],
                    false
                );
            } catch (\Throwable $e) {
                debugging(
                    'local_marketplace: gateway ' . $name . ' falhou ao cancelar assinatura - ' . $e->getMessage(),
                    DEBUG_NORMAL
                );
                continue;
            }
            if ($parou) {
                $parados[] = $name;
            }
        }

        return $parados;
    }

    /**
     * Pais em que a primeira conta de uma empresa nova e provisionada.
     *
     * @return string ISO-3166 alpha-2.
     */
    public static function default_country(): string {
        $configured = (string) get_config('local_marketplace', 'defaultcountry');

        return country::is_supported($configured) ? country::normalize($configured) : country::DEFAULT_COUNTRY;
    }

    /**
     * Da a um usuario o papel do Moodle que corresponde ao vinculo dele.
     *
     * Sao dois papeis - gerente e vendedor -, divididos por risco: quem so
     * monta curso nao alcanca a conta de pagamento. Qual deles vale sai do
     * memberrole, e a traducao mora em roles::shortname_for().
     *
     * ATRIBUI UM E TIRA O OUTRO, sempre. Trocar o vinculo de dono para vendedor
     * sem desfazer o papel anterior deixaria o antigo grudado, e a pessoa
     * seguiria alcancando a conta de pagamento depois de ter sido rebaixada.
     *
     * @param company $company
     * @param int $userid
     * @param string $memberrole member::ROLE_OWNER ou member::ROLE_SELLER.
     * @return void
     */
    public static function assign_member_role(company $company, int $userid, string $memberrole): void {
        $contextid = $company->get_context()->id;
        $escolhido = roles::shortname_for($memberrole);

        role_assign(roles::get_id($escolhido), $userid, $contextid);

        foreach ([roles::MANAGER, roles::SELLER] as $shortname) {
            if ($shortname !== $escolhido) {
                role_unassign(roles::get_id($shortname), $userid, $contextid);
            }
        }
    }

    /**
     * Aplica o tema da empresa na categoria dela.
     *
     * Depende de $CFG->allowcategorythemes. Sem ele o campo e gravado e
     * simplesmente ignorado pelo Moodle, o que renderia um bug silencioso:
     * o vendedor escolhe o tema, a tela confirma, e nada muda.
     *
     * @param company $company
     * @return bool Falso quando o tema por categoria esta desligado no site.
     */
    public static function apply_theme(company $company): bool {
        global $DB, $CFG;

        $theme = $company->get('themename');
        $categoryid = $company->get('categoryid');
        if (empty($categoryid)) {
            return true;
        }

        // Tema vazio LIMPA o da categoria, e nao "nao faz nada". Sair cedo aqui
        // tornaria impossivel voltar ao tema do site pela edicao: a empresa
        // ficaria presa ao primeiro tema escolhido.
        if (empty($theme)) {
            $DB->set_field('course_categories', 'theme', '', ['id' => $categoryid]);
            theme_reset_static_caches();
            return true;
        }

        if (empty($CFG->allowcategorythemes)) {
            debugging(
                'local_marketplace: tema por empresa exige $CFG->allowcategorythemes ligado.',
                DEBUG_DEVELOPER
            );
            return false;
        }

        $DB->set_field('course_categories', 'theme', $theme, ['id' => $categoryid]);
        theme_reset_static_caches();
        return true;
    }

    /**
     * Acrescenta um vendedor a empresa.
     *
     * @param company $company
     * @param int $userid
     * @return member
     */
    public static function add_member(company $company, int $userid): member {
        global $DB;

        $existing = member::get_membership($company->get('id'), $userid);
        if ($existing) {
            return $existing;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $member = new member();
            $member->set('companyid', $company->get('id'));
            $member->set('userid', $userid);
            $member->set('memberrole', member::ROLE_SELLER);
            $member->create();

            self::assign_member_role($company, $userid, member::ROLE_SELLER);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return $member;
    }

    /**
     * Remove um vendedor da empresa.
     *
     * Tira o papel no contexto da categoria junto com o vinculo. Apagar so a
     * linha da tabela deixaria a pessoa com as capabilities do vendedor -
     * criando curso na categoria de uma empresa da qual nao faz mais parte.
     *
     * NAO mexe em direitos de acesso nem em vendas: quem comprou continua com
     * o que comprou, e o historico financeiro da empresa nao se altera porque
     * um vendedor saiu.
     *
     * @param company $company
     * @param int $userid
     * @return bool Falso se a pessoa nao era membro.
     */
    public static function remove_member(company $company, int $userid): bool {
        global $DB;

        $member = member::get_membership($company->get('id'), $userid);
        if (!$member) {
            return false;
        }

        $contextid = $company->get_context()->id;

        $transaction = $DB->start_delegated_transaction();
        try {
            $member->delete();

            // OS DOIS papeis, e nao so o de vendedor. Tirar um deixaria o outro
            // grudado, e a pessoa continuaria enxergando a empresa depois de ter
            // sido removida dela.
            foreach ([roles::MANAGER, roles::SELLER] as $shortname) {
                role_unassign(roles::get_id($shortname), $userid, $contextid);
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }

    /**
     * Troca o papel de um membro entre dono e vendedor.
     *
     * AS CAPABILITIES DEIXARAM DE SER AS MESMAS em 04/09/2026. O dono responde
     * pela conta de pagamento e pelos membros; o vendedor so monta curso. Entao
     * trocar o vinculo tem que trocar o papel do Moodle junto - gravar so a
     * linha produziria um dono sem acesso a conta de pagamento da propria
     * empresa, ou um vendedor que continua alcancando ela.
     *
     * @param company $company
     * @param int $userid
     * @param string $memberrole
     * @return bool
     */
    public static function set_member_role(company $company, int $userid, string $memberrole): bool {
        global $DB;

        $member = member::get_membership($company->get('id'), $userid);
        if (!$member) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $member->set('memberrole', $memberrole);
            $member->update();

            self::assign_member_role($company, $userid, $memberrole);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }
}
