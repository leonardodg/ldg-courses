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

use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Quem sao os alunos de uma empresa.
 *
 * A pergunta e respondida a partir do DIREITO DE ACESSO, nao da venda. A
 * diferenca aparece nos testes: quem pegou oferta gratuita e aluno, e o aluno
 * de outra empresa nunca pode vazar para este relatorio.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_marketplace\entitlement::class, 'get_for_company')]
#[CoversMethod(\local_marketplace\entitlement::class, 'count_active_students')]
final class students_report_test extends \advanced_testcase {
    /** @var company */
    protected $company;

    /** @var company Segunda empresa, para provar o isolamento. */
    protected $other;

    /**
     * Duas empresas, para o teste de vazamento fazer sentido.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $owner = $this->getDataGenerator()->create_user();
        $this->company = api::create_company((object) [
            'name' => 'Empresa A',
            'shortname' => 'a' . random_int(1000, 9999),
        ], (int) $owner->id);

        $this->other = api::create_company((object) [
            'name' => 'Empresa B',
            'shortname' => 'b' . random_int(1000, 9999),
        ], (int) $owner->id);
    }

    /**
     * Cria uma oferta de uma empresa.
     *
     * @param company $company
     * @param float $price
     * @return offer
     */
    protected function make_offer(company $company, float $price = 100.0): offer {
        $offer = new offer();
        $offer->set('companyid', (int) $company->get('id'));
        $offer->set('name', 'Oferta ' . random_int(1000, 9999));
        $offer->set('price', $price);
        $offer->set('country', 'BR');
        $offer->set('status', offer::STATUS_PUBLISHED);
        $offer->create();

        return $offer;
    }

    /**
     * Da acesso a um aluno.
     *
     * @param offer $offer
     * @param int $userid
     * @param int $timeend 0 = vitalicio.
     * @param string $status
     * @return entitlement
     */
    protected function grant(offer $offer, int $userid, int $timeend = 0, string $status = entitlement::STATUS_ACTIVE) {
        $ent = new entitlement();
        $ent->set('userid', $userid);
        $ent->set('offerid', (int) $offer->get('id'));
        $ent->set('companyid', (int) $offer->get('companyid'));
        $ent->set('timestart', time() - DAYSECS);
        $ent->set('timeend', $timeend);
        $ent->set('status', $status);
        $ent->set('cycles', 1);
        $ent->create();

        return $ent;
    }

    /**
     * Os alunos da empresa aparecem, com nome, e-mail e oferta.
     *
     * @return void
     */
    public function test_lists_students_with_their_offer(): void {
        $offer = $this->make_offer($this->company);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Silva']);
        $this->grant($offer, (int) $student->id);

        $rows = entitlement::get_for_company((int) $this->company->get('id'));

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('Ana', $row->firstname);
        $this->assertSame($student->email, $row->email);
        $this->assertSame($offer->get('name'), $row->offername);
    }

    /**
     * O aluno de outra empresa NAO aparece.
     *
     * E o isolamento entre inquilinos. Um vazamento aqui entregaria a base de
     * alunos de um vendedor para o concorrente dele.
     *
     * @return void
     */
    public function test_does_not_leak_other_companies(): void {
        $mine = $this->make_offer($this->company);
        $theirs = $this->make_offer($this->other);

        $this->grant($mine, (int) $this->getDataGenerator()->create_user()->id);
        $this->grant($theirs, (int) $this->getDataGenerator()->create_user()->id);
        $this->grant($theirs, (int) $this->getDataGenerator()->create_user()->id);

        $this->assertCount(1, entitlement::get_for_company((int) $this->company->get('id')));
        $this->assertCount(2, entitlement::get_for_company((int) $this->other->get('id')));
    }

    /**
     * Quem pegou oferta gratuita conta como aluno.
     *
     * E a razao de o relatorio sair do direito e nao da venda: nao ha venda
     * nenhuma neste caso, e a pessoa esta estudando do mesmo jeito.
     *
     * @return void
     */
    public function test_free_offer_counts_as_a_student(): void {
        $free = $this->make_offer($this->company, 0.0);
        $this->grant($free, (int) $this->getDataGenerator()->create_user()->id);

        $this->assertCount(1, entitlement::get_for_company((int) $this->company->get('id')));
        $this->assertSame(1, entitlement::count_active_students((int) $this->company->get('id')));
    }

    /**
     * Um aluno com tres ofertas e UM aluno.
     *
     * A contagem e de pessoas distintas. Contar linhas daria um numero maior
     * que a base real - e e esse numero que o vendedor usa para decidir.
     *
     * @return void
     */
    public function test_counts_distinct_people(): void {
        $student = $this->getDataGenerator()->create_user();
        foreach (range(1, 3) as $ignored) {
            $this->grant($this->make_offer($this->company), (int) $student->id);
        }

        $this->assertCount(3, entitlement::get_for_company((int) $this->company->get('id')), 'tres direitos');
        $this->assertSame(1, entitlement::count_active_students((int) $this->company->get('id')), 'uma pessoa');
    }

    /**
     * Vencidos e cancelados aparecem na lista, mas nao na contagem de vigentes.
     *
     * Quem vende assinatura precisa enxergar quem saiu tanto quanto quem ficou.
     *
     * @return void
     */
    public function test_expired_and_cancelled_are_listed_but_not_counted(): void {
        $offer = $this->make_offer($this->company);

        $expired = $this->getDataGenerator()->create_user();
        $cancelled = $this->getDataGenerator()->create_user();

        $this->grant($offer, (int) $this->getDataGenerator()->create_user()->id);
        $this->grant($this->make_offer($this->company), (int) $expired->id, time() - DAYSECS);
        $this->grant(
            $this->make_offer($this->company),
            (int) $cancelled->id,
            0,
            entitlement::STATUS_CANCELLED
        );

        $this->assertCount(3, entitlement::get_for_company((int) $this->company->get('id')));
        $this->assertSame(1, entitlement::count_active_students((int) $this->company->get('id')));
    }

    /**
     * Um direito que venceu ontem nao conta como vigente.
     *
     * A situacao gravada so muda quando o cron roda. Contar por ela mostraria
     * como aluno quem perdeu o acesso na virada da noite.
     *
     * @return void
     */
    public function test_expiry_date_beats_the_stored_status(): void {
        $offer = $this->make_offer($this->company);
        $student = $this->getDataGenerator()->create_user();

        // Situacao ainda 'active', mas a data ja passou.
        $this->grant($offer, (int) $student->id, time() - HOURSECS);

        $this->assertSame(0, entitlement::count_active_students((int) $this->company->get('id')));
    }

    /**
     * Empresa sem aluno nenhum devolve lista vazia, e nao erro.
     *
     * @return void
     */
    public function test_company_without_students(): void {
        $this->assertSame([], entitlement::get_for_company((int) $this->company->get('id')));
        $this->assertSame(0, entitlement::count_active_students((int) $this->company->get('id')));
    }
}
