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

/**
 * Unidade de venda.
 *
 * O curso deixa de ser o que se vende e passa a ser o que se libera. A mesma
 * aula pode ser vendida como pacote basico, como parte de um combo e dentro
 * de uma assinatura, cada um com preco e prazo proprios.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class offer extends persistent {
    /** @var string Tabela. */
    const TABLE = 'local_marketplace_offer';

    /** @var string Um curso. */
    const TYPE_SINGLE = 'single';

    /** @var string Combo de cursos escolhidos. */
    const TYPE_BUNDLE = 'bundle';

    /** @var string Todo o catalogo da empresa, inclusive cursos futuros. */
    const TYPE_CATALOG = 'catalog';

    /** @var string Acesso sem prazo. */
    const ACCESS_LIFETIME = 'lifetime';

    /** @var string Acesso por accessdays dias. */
    const ACCESS_DAYS = 'days';

    /** @var string Assinatura: renova enquanto o aluno pagar. */
    const ACCESS_RECURRING = 'recurring';

    /** @var string Em edicao, invisivel para o aluno. */
    const STATUS_DRAFT = 'draft';

    /** @var string A venda. */
    const STATUS_PUBLISHED = 'published';

    /** @var string Fora de venda. NAO revoga quem ja comprou. */
    const STATUS_ARCHIVED = 'archived';

    /** @var string Ordem definida pelo vendedor. E o padrao. */
    const SORT_MANUAL = 'manual';

    /** @var string Mais recentes primeiro. */
    const SORT_NEWEST = 'newest';

    /** @var string Alfabetica. */
    const SORT_NAME = 'name';

    /** @var string Do mais barato ao mais caro. */
    const SORT_PRICE = 'price';

    /** @var string Do mais caro ao mais barato. */
    const SORT_PRICEDESC = 'pricedesc';

    /**
     * Define as propriedades.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'companyid' => ['type' => PARAM_INT],
            'name' => ['type' => PARAM_TEXT],
            'description' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'offertype' => [
                'type' => PARAM_ALPHA,
                'default' => self::TYPE_SINGLE,
                'choices' => [self::TYPE_SINGLE, self::TYPE_BUNDLE, self::TYPE_CATALOG],
            ],
            'price' => ['type' => PARAM_FLOAT, 'default' => 0],
            'country' => [
                'type' => PARAM_ALPHA,
                'default' => country::DEFAULT_COUNTRY,
            ],
            'currency' => ['type' => PARAM_ALPHA, 'default' => 'BRL'],
            'accessmode' => [
                'type' => PARAM_ALPHA,
                'default' => self::ACCESS_LIFETIME,
                'choices' => [self::ACCESS_LIFETIME, self::ACCESS_DAYS, self::ACCESS_RECURRING],
            ],
            'accessdays' => ['type' => PARAM_INT, 'default' => 0],
            'billingdays' => ['type' => PARAM_INT, 'default' => 0],
            'maxcycles' => ['type' => PARAM_INT, 'default' => 0],
            'status' => [
                'type' => PARAM_ALPHA,
                'default' => self::STATUS_DRAFT,
                'choices' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED],
            ],
            'sortorder' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * O pais tem que ser um em que o marketplace opera.
     *
     * Nao checa se a EMPRESA tem conta la: uma oferta pode ser cadastrada em
     * rascunho antes de a conta daquele pais existir. Quem barra a venda sem
     * conta e o portao - company::can_sell($country) na vitrine, e o
     * get_payable() no checkout, que recusa em vez de cobrar para lugar nenhum.
     *
     * @param string $value
     * @return true|\lang_string
     */
    protected function validate_country($value) {
        if (!country::is_supported((string) $value)) {
            return new \lang_string('errorcountryunsupported', 'local_marketplace', $value);
        }

        return true;
    }

    /**
     * A moeda e DERIVADA do pais, nunca escolhida.
     *
     * Uma conta do pais X so recebe na moeda de X, e nao ha cambio no caminho
     * do split - entao "oferta em BRL vendendo na Argentina" nao e uma
     * configuracao errada, e uma configuracao impossivel. Derivar aqui, e nao
     * validar num formulario, e o que garante que nenhum caminho de gravacao
     * (tela, CLI, teste, upgrade) consiga produzir a combinacao invalida.
     *
     * Antes disto a moeda esperada vinha do gateway ja vinculado, o que fazia
     * o cadastro de uma oferta depender de o vendedor ter concluido o vinculo -
     * e devolvia respostas diferentes conforme a ordem dos gateways na conta.
     *
     * @return void
     */
    protected function before_validate() {
        $currency = country::currency_for((string) $this->raw_get('country'));
        if ($currency !== '') {
            $this->raw_set('currency', $currency);
        }
    }

    /**
     * Moeda desta oferta, derivada do pais.
     *
     * @return string
     */
    public function get_expected_currency(): string {
        return country::currency_for((string) $this->get('country'));
    }

    /**
     * A oferta e gratuita?
     *
     * Importa porque o portao de venda so vale para oferta paga: uma empresa
     * sem meio de pagamento configurado pode publicar curso de graca.
     *
     * @return bool
     */
    public function is_free(): bool {
        return (float) $this->get('price') <= 0;
    }

    /**
     * Quando expira um direito comprado agora.
     *
     * @param int|null $from Momento da compra; agora se omitido.
     * @return int Timestamp, ou 0 para vitalicio.
     */
    public function calculate_expiry(?int $from = null): int {
        $from = $from ?? time();
        switch ($this->get('accessmode')) {
            case self::ACCESS_DAYS:
            case self::ACCESS_RECURRING:
                // Sempre accessdays, nos dois modos: e "quanto acesso este
                // pagamento libera". O intervalo de cobranca vive em
                // billingdays e nao entra aqui - se fossem o mesmo campo, nao
                // haveria como dar carencia entre o vencimento da fatura e o
                // corte do acesso.
                $days = (int) $this->get('accessdays');
                return $days > 0 ? $from + ($days * DAYSECS) : 0;
            case self::ACCESS_LIFETIME:
            default:
                return 0;
        }
    }

    /**
     * A assinatura ainda admite mais uma cobranca?
     *
     * @param int $cyclespaid Ciclos ja pagos pelo aluno.
     * @return bool
     */
    public function accepts_cycle(int $cyclespaid): bool {
        if ($this->get('accessmode') !== self::ACCESS_RECURRING) {
            return true;
        }
        $max = (int) $this->get('maxcycles');

        return $max <= 0 || $cyclespaid < $max;
    }

    /**
     * Descricao legivel do modelo de cobranca.
     *
     * @return string
     */
    public function describe_billing(): string {
        if ($this->get('accessmode') !== self::ACCESS_RECURRING) {
            return $this->get('accessmode') === self::ACCESS_LIFETIME
                ? get_string('accesslifetime', 'local_marketplace')
                : get_string('accessdays', 'local_marketplace', (int) $this->get('accessdays'));
        }

        $max = (int) $this->get('maxcycles');
        $a = (object) [
            'billing' => (int) $this->get('billingdays'),
            'access' => (int) $this->get('accessdays'),
            'cycles' => $max,
        ];

        return $max > 0
            ? get_string('accessrecurringlimited', 'local_marketplace', $a)
            : get_string('accessrecurringopen', 'local_marketplace', $a);
    }

    /**
     * Duracao do acesso em segundos, ou 0 para vitalicio.
     *
     * Separado de calculate_expiry() porque a renovacao precisa somar a
     * duracao ao vencimento ATUAL, nao calcular uma data a partir de agora -
     * senao renovar antes do vencimento encurtaria o que ja foi pago.
     *
     * @return int
     */
    public function get_access_duration(): int {
        if ($this->get('accessmode') === self::ACCESS_LIFETIME) {
            return 0;
        }
        return max(0, (int) $this->get('accessdays')) * DAYSECS;
    }

    /**
     * Cursos que esta oferta libera.
     *
     * Em catalog a lista NAO vem de offer_course: vem da categoria da empresa,
     * para que um curso publicado depois da compra ja entre para quem assina.
     *
     * @return int[] IDs de curso.
     */
    public function get_course_ids(): array {
        global $DB;

        if ($this->get('offertype') === self::TYPE_CATALOG) {
            $company = new company($this->get('companyid'));
            $categoryid = $company->get('categoryid');
            if (empty($categoryid)) {
                return [];
            }
            return $DB->get_fieldset_select('course', 'id', 'category = ?', [$categoryid]);
        }

        return $DB->get_fieldset_select(
            'local_marketplace_offer_course',
            'courseid',
            'offerid = ?',
            [$this->get('id')]
        );
    }

    /**
     * Vincula um curso a oferta.
     *
     * @param int $courseid
     * @return void
     */
    public function add_course(int $courseid): void {
        global $DB;

        if ($this->get('offertype') === self::TYPE_CATALOG) {
            throw new \coding_exception('Oferta de catalogo segue a categoria da empresa e nao lista cursos.');
        }
        $company = new company((int) $this->get('companyid'));
        if (!$company->owns_course($courseid)) {
            // Isolamento multi-tenant: empresa = categoria. Uma oferta so pode
            // vincular curso da propria categoria (ou subcategoria dela).
            throw new \moodle_exception('errorcoursenotowned', 'local_marketplace');
        }
        $params = ['offerid' => $this->get('id'), 'courseid' => $courseid];
        if (!$DB->record_exists('local_marketplace_offer_course', $params)) {
            $DB->insert_record('local_marketplace_offer_course', (object) $params);
        }
    }

    /**
     * Ofertas publicadas de uma empresa.
     *
     * @param int $companyid
     * @return offer[]
     */
    public static function get_published(int $companyid, string $sort = self::SORT_MANUAL): array {
        return self::get_records(
            ['companyid' => $companyid, 'status' => self::STATUS_PUBLISHED],
            self::sort_clause($sort)
        );
    }

    /**
     * Traduz a opcao de ordenacao para SQL.
     *
     * A ordem manual e o padrao, e nao o nome ou o preco. O vendedor define
     * sortorder justamente para colocar na frente o que quer vender primeiro -
     * trocar isso por alfabetica desfaria a curadoria dele a cada carregamento.
     *
     * @param string $sort
     * @return string
     */
    public static function sort_clause(string $sort): string {
        switch ($sort) {
            case self::SORT_NAME:
                return 'name';
            case self::SORT_PRICE:
                return 'price, name';
            case self::SORT_PRICEDESC:
                return 'price DESC, name';
            case self::SORT_NEWEST:
                // Desempate por id: ofertas criadas no mesmo segundo, o que
                // acontece no seed e em importacao, sairiam em ordem aleatoria.
                return 'timecreated DESC, id DESC';
            case self::SORT_MANUAL:
            default:
                return 'sortorder, name';
        }
    }

    /**
     * Opcoes de ordenacao oferecidas ao aluno.
     *
     * @return string[]
     */
    public static function sort_options(): array {
        return [
            self::SORT_MANUAL,
            self::SORT_NEWEST,
            self::SORT_NAME,
            self::SORT_PRICE,
            self::SORT_PRICEDESC,
        ];
    }

    /**
     * Em quais subcategorias da empresa esta oferta libera curso.
     *
     * Serve ao filtro da vitrine. Uma oferta pode alcancar mais de uma
     * subcategoria - um combo que junta cursos de trilhas diferentes - e por
     * isso devolve lista, nao valor unico.
     *
     * @return int[]
     */
    public function get_category_ids(): array {
        global $DB;

        $courseids = $this->get_course_ids();
        if (!$courseids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        return array_map('intval', array_unique(
            $DB->get_fieldset_select('course', 'category', "id $insql", $params)
        ));
    }
}
