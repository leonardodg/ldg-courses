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

use core\persistent;
use lang_string;

/**
 * Empresa vendedora.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class company extends persistent {
    /** @var string Tabela. */
    public const TABLE = 'local_marketplace_company';

    /** @var string Empresa em operacao. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Empresa bloqueada pelo dono da plataforma. */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'shortname' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'cnpj' => [
                'type' => PARAM_ALPHANUM,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'commissionpct' => [
                'type' => PARAM_FLOAT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'commissionbase' => [
                'type' => PARAM_ALPHA,
                'null' => NULL_ALLOWED,
                'default' => null,
                // O nulo entra na lista de proposito: o validador de choices do
                // persistent roda mesmo com NULL_ALLOWED, e sem ele "herda a
                // base do site" seria recusado como valor invalido.
                'choices' => [null, commission::BASE_GROSS, commission::BASE_NET],
            ],
            'planid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'planexpiry' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
                // Sem status separado, de proposito: inadimplente e so
                // "planexpiry no passado", do mesmo jeito que
                // entitlement::timeend ja decide vencimento hoje. Um enum a
                // mais so poderia divergir do timestamp.
            ],
            'pagetitle' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'pageintro' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'pageaccent' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'categoryid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'themename' => [
                'type' => PARAM_COMPONENT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'hostname' => [
                'type' => PARAM_HOST,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'status' => [
                'type' => PARAM_ALPHA,
                'default' => self::STATUS_ACTIVE,
                'choices' => [self::STATUS_ACTIVE, self::STATUS_SUSPENDED],
            ],
        ];
    }

    /**
     * O CNPJ, quando informado, precisa ser um numero valido.
     *
     * Continua OPCIONAL: pessoa fisica vende sem CNPJ, e essa decisao nao muda.
     * O que muda e que catorze digitos quaisquer deixam de passar - antes disto
     * o campo era so PARAM_ALPHANUM, e um erro de digitacao so aparecia quando
     * o gateway recusasse a cobranca.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_cnpj($value) {
        if ($value === null || $value === '') {
            return true;
        }

        if (!cnpj::is_valid((string) $value)) {
            return new lang_string('errorcnpjinvalid', 'local_marketplace');
        }

        return true;
    }

    /**
     * O nome curto vai para a URL, entao precisa ser unico.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_shortname($value) {
        $existing = self::get_record(['shortname' => $value]);
        if ($existing && $existing->get('id') != $this->get('id')) {
            return new lang_string('errorshortnametaken', 'local_marketplace');
        }
        return true;
    }

    /**
     * Dois dominios iguais quebrariam a resolucao Host->empresa da Fase 3.
     *
     * @param string|null $value
     * @return true|lang_string
     */
    protected function validate_hostname($value) {
        if ($value === null || $value === '') {
            return true;
        }
        $existing = self::get_record(['hostname' => $value]);
        if ($existing && $existing->get('id') != $this->get('id')) {
            return new lang_string('errorhostnametaken', 'local_marketplace');
        }
        return true;
    }

    /**
     * A empresa pode vender curso pago?
     *
     * Este e o portao de venda, e e ESTADO, nao permissao. Uma capability
     * responderia "tem direito de vender?"; a pergunta real e "esta habilitada
     * a receber?". Sem meio de recebimento configurado nao ha para onde mandar
     * o dinheiro do vendedor, entao a empresa so publica curso gratuito.
     *
     * A pergunta e feita ao core_payment, NAO ao gateway. account::is_available()
     * ja responde "habilitada e com ao menos um gateway configurado", e mantem
     * isto agnostico: se um dia entrar outro meio de pagamento, o portao
     * continua correto sem tocar aqui.
     *
     * Sem pais, responde "vende em ALGUM lugar" - e o que a vitrine e a lista de
     * empresas querem saber. Com pais, responde sobre aquele mercado: uma
     * empresa pode receber no Brasil e nao na Argentina, e a oferta argentina
     * dela nao pode ser publicada por causa disso.
     *
     * @param string|null $country ISO-3166 alpha-2, ou nulo para qualquer pais.
     * @return bool
     */
    public function can_sell(?string $country = null): bool {
        if ($this->get('status') !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($country !== null) {
            $account = $this->get_payment_account($country);
            return $account && $account->is_available();
        }

        foreach ($this->get_payment_accounts() as $account) {
            if ($account->is_available()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Payment account com que a empresa recebe naquele pais.
     *
     * @param string $country ISO-3166 alpha-2.
     * @return \core_payment\account|null
     */
    public function get_payment_account(string $country): ?\core_payment\account {
        $link = company_account::get_for((int) $this->get('id'), $country);

        return $link ? $link->get_payment_account() : null;
    }

    /**
     * Todas as contas da empresa, indexadas por pais.
     *
     * @return array<string, \core_payment\account>
     */
    public function get_payment_accounts(): array {
        $out = [];
        foreach (company_account::list_for_company((int) $this->get('id')) as $countrycode => $link) {
            $account = $link->get_payment_account();
            if ($account) {
                $out[$countrycode] = $account;
            }
        }

        return $out;
    }

    /**
     * Paises em que a empresa tem conta.
     *
     * @return string[] Codigos ISO-3166 alpha-2, em ordem.
     */
    public function get_countries(): array {
        return array_keys($this->get_payment_accounts());
    }

    /**
     * URL do CSS proprio da vitrine, se houver.
     *
     * @return \moodle_url|null
     */
    public function get_page_css_url(): ?\moodle_url {
        if (!$this->get('categoryid')) {
            return null;
        }

        $context = $this->get_context();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_marketplace', 'pagecss', 0, 'itemid', false);

        if (!$files) {
            return null;
        }

        $file = reset($files);

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_marketplace',
            'pagecss',
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * URL do logo da marca, se houver.
     *
     * Nao tem campo no banco: o arquivo em si e o dado. Uma coluna com o nome
     * do arquivo abriria a chance de as duas coisas discordarem.
     *
     * @return \moodle_url|null
     */
    public function get_page_logo_url(): ?\moodle_url {
        if (!$this->get('categoryid')) {
            return null;
        }

        $fs = get_file_storage();
        $context = $this->get_context();
        $files = $fs->get_area_files($context->id, 'local_marketplace', 'pagelogo', 0, 'itemid', false);

        if (!$files) {
            return null;
        }

        $file = reset($files);

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_marketplace',
            'pagelogo',
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Titulo da vitrine, com o nome da empresa como padrao.
     *
     * @return string
     */
    public function get_page_title(): string {
        $custom = (string) $this->get('pagetitle');

        return $custom !== '' ? $custom : (string) $this->get('name');
    }

    /**
     * Valida a cor de destaque.
     *
     * Este e o unico campo do cadastro que vai parar DENTRO de uma regra CSS.
     * Aceitar texto livre ali deixaria o vendedor fechar a declaracao e abrir
     * outra - e num navegador antigo, chegar a expression() ou url(javascript:).
     *
     * Por isso o formato e imposto aqui e nao so na tela: hexadecimal de tres
     * ou seis digitos, mais nada.
     *
     * @param mixed $value
     * @return true|\lang_string
     */
    protected function validate_pageaccent($value) {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value)) {
            return new \lang_string('errorpageaccent', 'local_marketplace');
        }

        return true;
    }

    /**
     * Comissao negociada com esta empresa.
     *
     * Nulo significa "nao negociamos nada, use o padrao do site" - diferente de
     * zero, que significa "negociamos isencao". Sem essa distincao, um parceiro
     * isento voltaria a pagar comissao na primeira vez que alguem mudasse o
     * padrao do site.
     *
     * @return float|null
     */
    public function get_commission_percent(): ?float {
        $value = $this->get('commissionpct');

        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Valida o percentual.
     *
     * @param mixed $value
     * @return true|\lang_string
     */
    protected function validate_commissionpct($value) {
        if ($value === null || $value === '') {
            return true;
        }
        if ((float) $value < 0 || (float) $value > 100) {
            return new \lang_string('errorcommissionrange', 'local_marketplace');
        }

        return true;
    }

    /**
     * Moedas em que esta empresa recebe, uma por pais em que tem conta.
     *
     * Nao e escolha livre e nao e uma so. Uma conta e presa a um pais e so
     * recebe na moeda dele, entao a empresa "escolhe" a moeda escolhendo em
     * quais paises abre conta.
     *
     * Ate a versao anterior isto devolvia UMA moeda, lida do primeiro gateway
     * habilitado da unica conta. Com mais de um gateway na mesma conta a
     * resposta passava a depender da ordem dos ids, o que e o mesmo que dizer
     * que nao havia resposta.
     *
     * @return array<string, string> pais => moeda
     */
    public function get_currencies(): array {
        $out = [];
        foreach ($this->get_countries() as $countrycode) {
            $out[$countrycode] = country::currency_for($countrycode);
        }

        return $out;
    }

    /**
     * Contexto da categoria da empresa, onde vivem as capabilities e o papel.
     *
     * @return \context
     */
    /**
     * O plano precisa existir e estar ativo.
     *
     * A chave estrangeira do XMLDB e documental no Moodle - a integridade e
     * garantida aqui. Plano arquivado e recusado de proposito: arquivar existe
     * para tirar um plano de venda, e aceitar novo vinculo o traria de volta
     * pela porta dos fundos.
     *
     * @param int|null $value
     * @return true|lang_string
     */
    protected function validate_planid($value) {
        if ($value === null) {
            return true;
        }

        $plan = plan::get_record(['id' => (int) $value]);

        if (!$plan) {
            return new lang_string('errorplannotfound', 'local_marketplace');
        }

        if ($plan->get('status') !== plan::STATUS_ACTIVE) {
            return new lang_string('errorplanarchived', 'local_marketplace');
        }

        return true;
    }

    /**
     * Plano contratado por esta empresa, se houver.
     *
     * @return plan|null
     */
    public function get_plan(): ?plan {
        $planid = $this->get('planid');

        if (empty($planid)) {
            return null;
        }

        $plan = plan::get_record(['id' => (int) $planid]);

        return $plan ?: null;
    }

    /**
     * A mensalidade do plano esta em dia?
     *
     * Sem status separado de proposito - inadimplente e so "planexpiry no
     * passado". Empresa sem planexpiry (nunca assinou, ou plano gratis) nao
     * esta inadimplente: nao ha o que vencer.
     *
     * @return bool
     */
    public function is_plan_overdue(): bool {
        $expiry = $this->get('planexpiry');

        return $expiry !== null && (int) $expiry < time();
    }

    /**
     * Soma um ciclo pago ao vencimento da mensalidade.
     *
     * Mesma regra de entitlement::extend(): soma ao vencimento ATUAL quando
     * ele ainda esta no futuro, e a partir de AGORA quando ja passou - quem
     * ficou dias sem pagar nao ganha esses dias de volta.
     *
     * @param int $seconds
     * @return void
     */
    public function extend_plan(int $seconds): void {
        $current = (int) $this->get('planexpiry');
        $base = max($current, time());

        $this->set('planexpiry', $base + $seconds);
        $this->update();
    }

    /**
     * Contexto da categoria da empresa, onde vivem as capabilities e o papel.
     *
     * @return \context
     */
    public function get_context(): \context {
        $categoryid = $this->get('categoryid');
        if (empty($categoryid)) {
            // NAO cai para context_system: quem chama get_context() usa o
            // resultado para checar capability ESCOPADA aquela empresa
            // (managecompany, managesales, refundsale...). Devolver o
            // contexto do site faria essas checagens virarem "tem a
            // capability em nivel de sistema?" para uma empresa sem categoria
            // - uma falha de provisionamento (linha nunca deveria existir
            // assim) escalando silenciosamente o escopo da checagem em vez
            // de falhar.
            throw new \coding_exception(
                'Empresa sem categoria: provisionamento incompleto, id=' . $this->get('id')
            );
        }
        return \context_coursecat::instance($categoryid);
    }

    /**
     * Confere se um curso esta na categoria desta empresa, ou numa subcategoria dela.
     *
     * Empresa = categoria e a fronteira multi-tenant do projeto (CLAUDE.md),
     * mas empresas organizam os proprios cursos em subcategorias sob a
     * categoria raiz - igualdade exata excluiria todo curso fora do topo.
     *
     * @param int $courseid
     * @return bool
     */
    public function owns_course(int $courseid): bool {
        global $DB;

        $companycategoryid = (int) $this->get('categoryid');
        if (empty($companycategoryid)) {
            return false;
        }

        $coursecategoryid = (int) $DB->get_field('course', 'category', ['id' => $courseid], MUST_EXIST);
        if ($coursecategoryid === $companycategoryid) {
            return true;
        }

        $coursecategory = \core_course_category::get($coursecategoryid, IGNORE_MISSING, true);
        if (!$coursecategory) {
            return false;
        }
        $ancestors = explode('/', trim($coursecategory->path, '/'));
        return in_array((string) $companycategoryid, $ancestors, true);
    }

    /**
     * Empresa dona da categoria de um curso, subida ate achar uma.
     *
     * Sobe a arvore de categorias em vez de exigir igualdade exata: uma
     * empresa organiza os proprios cursos em subcategorias sob a categoria
     * raiz, e igualdade exata excluiria todo curso fora do topo.
     *
     * @param int $courseid
     * @return company|null
     */
    public static function for_course(int $courseid): ?company {
        global $DB;

        $categoryid = (int) $DB->get_field('course', 'category', ['id' => $courseid], IGNORE_MISSING);
        if (!$categoryid) {
            return null;
        }

        // A cadeia vai do curso ate a raiz: uma empresa costuma organizar os
        // proprios cursos em subcategorias, e a mais especifica que bater
        // ganha - nao precisa ser exatamente a categoria do curso.
        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        $chain = $category
            ? array_reverse(array_filter(explode('/', trim($category->path, '/'))))
            : [$categoryid];

        foreach ($chain as $id) {
            $company = self::get_record(['categoryid' => (int) $id]);
            if ($company) {
                return $company;
            }
        }

        return null;
    }

    /**
     * Empresas em que o usuario e membro.
     *
     * @param int $userid
     * @return company[]
     */
    public static function get_by_member(int $userid): array {
        global $DB;

        $sql = "SELECT c.*
                  FROM {" . self::TABLE . "} c
                  JOIN {" . member::TABLE . "} m ON m.companyid = c.id
                 WHERE m.userid = :userid
              ORDER BY c.name";
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);

        return array_map(fn($record) => new self(0, $record), $records);
    }
}
