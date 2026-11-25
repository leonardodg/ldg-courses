# Checklist de ativação no block_marketplace — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer o `block_marketplace` mostrar, para quem é dono/membro de uma
empresa ainda incompleta, um checklist de progresso ("conta de pagamento" +
"plano") em vez do widget de assinaturas do aluno — sem tabela nova, tudo
derivado do estado que já existe em `local_marketplace`.

**Architecture:** Nova classe `block_marketplace\onboarding` (lógica pura,
sem dependência de `$USER`/`$PAGE`) calcula o estado derivado. O
`block_marketplace::get_content()` passa a despachar por perfil: se o usuário
é membro de alguma empresa (`company::get_by_member()`), mostra o checklist
dessa empresa; senão, mantém o comportamento atual (assinaturas do aluno).

**Tech Stack:** PHP 8.4, Moodle 5.2 persistent API, PHPUnit.

## Global Constraints

- Escrever em português, sem acentos no código/comentários (padrão do
  projeto — ver `CLAUDE.md`).
- Strings de idioma em ordem alfabética estrita (`lang/en/block_marketplace.php`)
  — o `phpcs` do CI reprova ordem errada.
- TDD: teste vermelho → código → teste verde → commit, um commit por task.
- Rodar os testes com `-u 1000:33` dentro do container `ldg-courses-moodle-1`
  (ver `CLAUDE.md`, seção "Comandos que funcionam").
- Não inventar página, campo ou setting que não existe — este plano só usa
  APIs confirmadas por leitura direta do código nesta sessão (ver seção
  "Achados que restringem o escopo" abaixo).

## Achados que restringem o escopo (por que este plano é menor que o desenho original)

O desenho original (`docs/history/desenhar_fluxo_cadastrar_empresa.txt`)
previa 6 etapas e 7 telas novas (`onboarding.php`, `gateway.php`, `plan.php`,
`document.php`, `terms.php`, `approve.php`). Conferindo o código real:

- **`local_marketplace\company` não tem estado "em cadastro"** — só
  `STATUS_ACTIVE`/`STATUS_SUSPENDED`. Uma `company` só passa a existir depois
  que `local_partners\api::approve()` chama
  `local_marketplace\api::create_company()`. Não há empresa "escondida" antes
  da aprovação (ADR-0006 do desenho original nunca foi implementada) — isso
  é fora do escopo deste plano, que trabalha só com empresas que já existem.
- **Conta de pagamento e plano já têm página própria**:
  `local/marketplace/company.php` (aceita `?company=<shortname>`) já deixa o
  gerente vincular gateway e trocar de plano. Não precisamos de
  `gateway.php`/`plan.php` novos — só de um link para lá.
- **Termos** (`termsaccepted`) já existe em `local_partners\application`,
  carimbado na candidatura, antes da empresa nascer. Não há etapa de termos
  para uma empresa que já foi aprovada.
- **Documento (CNPJ)** é opcional (`company::cnpj` aceita `null`, ADR-0010) —
  vira item informativo do checklist, nunca bloqueante.
- **Aprovação do admin** já existe em `local_partners` (fila de candidaturas)
  — não é um passo do `block_marketplace`.

O que sobra, e que realmente falta: **um lugar que resuma "falta isso para
ficar pronto"**, para o gerente ver assim que entra no Dashboard. É esse
lugar que este plano constrói.

## Interfaces já existentes usadas neste plano (não recriar)

```php
// local_marketplace\company
public function get_plan(): ?\local_marketplace\plan;
public function get_payment_accounts(): array; // array<string país, \core_payment\account>
public static function get_by_member(int $userid): array; // company[]

// core_payment\account (public/payment/classes/account.php:145)
public function is_available(): bool; // enabled=1 E ao menos um gateway habilitado

// local_marketplace\api
public static function create_company(object $data, int $ownerid): company;
```

---

### Task 1: `block_marketplace\onboarding` — estado derivado, sem I/O de página

**Files:**
- Create: `public/blocks/marketplace/classes/onboarding.php`
- Test: `public/blocks/marketplace/tests/onboarding_test.php`

**Interfaces:**
- Consumes: `local_marketplace\company::get_plan()`, `get_payment_accounts()`,
  `get('cnpj')`; `\core_payment\account::is_available()`.
- Produces: `onboarding::STEP_GATEWAY`, `onboarding::STEP_PLAN`,
  `onboarding::STEP_DOCUMENT` (constantes string); `onboarding::STATE_DONE`,
  `onboarding::STATE_PENDING`, `onboarding::STATE_OPTIONAL`;
  `onboarding::step_state(company $company): array` (mapa etapa => estado);
  `onboarding::progress(company $company): array` (`['done' => int, 'total' =>
  int, 'percent' => int, 'complete' => bool]`, ignorando etapas `OPTIONAL` no
  total). Task 2 usa as duas funções e as constantes.

- [ ] **Step 1: Escrever o teste (vermelho)**

Criar `public/blocks/marketplace/tests/onboarding_test.php`:

```php
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

namespace block_marketplace;

use local_marketplace\api;
use local_marketplace\company;
use local_marketplace\company_account;
use local_marketplace\plan;
use core_payment\account;
use core_payment\account_gateway;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/marketplace/classes/onboarding.php');

/**
 * O checklist de ativacao e derivado do estado que ja existe - nenhuma
 * tabela nova. Estes testes provam a derivacao, sem tocar em pagina nenhuma.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(onboarding::class)]
final class onboarding_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * @param array $overrides
     * @return company
     */
    private function make_company(array $overrides = []): company {
        return api::create_company((object) array_merge([
            'name' => 'Empresa de teste',
            'shortname' => 'onboardteste' . random_int(100000, 999999),
            'cnpj' => null,
            'themename' => null,
            'hostname' => null,
        ], $overrides), 2);
    }

    /**
     * Conta vinculada, habilitada, com gateway ligado - o que account::is_available() exige.
     *
     * @param company $company
     * @param string $country
     * @return account
     */
    private function make_available_account(company $company, string $country = 'BR'): account {
        $account = new account(0, (object) [
            'name' => 'Conta de teste',
            'idnumber' => 'onboardacc' . random_int(100000, 999999),
        ]);
        $account->create();

        $gateway = new account_gateway(0, (object) [
            'accountid' => $account->get('id'),
            'gateway' => 'mercadopago',
            'enabled' => true,
        ]);
        $gateway->create();

        $link = new company_account(0, (object) [
            'companyid' => $company->get('id'),
            'country' => $country,
            'accountid' => $account->get('id'),
        ]);
        $link->create();

        return $account;
    }

    /**
     * Conta vinculada mas SEM gateway habilitado - o caso que
     * account::is_available() recusa, e que o CLAUDE.md ja documentou como
     * armadilha ("sem meio de pagamento apos vincular").
     *
     * @param company $company
     * @return void
     */
    private function make_unavailable_account(company $company): void {
        $account = new account(0, (object) [
            'name' => 'Conta sem gateway',
            'idnumber' => 'semgw' . random_int(100000, 999999),
        ]);
        $account->create();

        $link = new company_account(0, (object) [
            'companyid' => $company->get('id'),
            'country' => 'BR',
            'accountid' => $account->get('id'),
        ]);
        $link->create();
    }

    public function test_empresa_nova_tem_gateway_e_plano_pendentes(): void {
        $company = $this->make_company();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_PENDING, $states[onboarding::STEP_GATEWAY]);
        $this->assertSame(onboarding::STATE_PENDING, $states[onboarding::STEP_PLAN]);
    }

    public function test_documento_e_opcional_quando_cnpj_e_nulo(): void {
        $company = $this->make_company();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_OPTIONAL, $states[onboarding::STEP_DOCUMENT]);
    }

    public function test_documento_fica_concluido_quando_cnpj_existe(): void {
        $company = $this->make_company(['cnpj' => '12345678000199']);

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_DOCUMENT]);
    }

    public function test_plano_fica_concluido_quando_planid_esta_definido(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'start_50_teste',
            'name' => 'Start 1080p',
            'monthlyfee' => 50,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $company = $this->make_company();
        $company->set('planid', (int) $plan->get('id'));
        $company->update();

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_PLAN]);
    }

    public function test_conta_habilitada_com_gateway_ligado_conclui_a_etapa(): void {
        $company = $this->make_company();
        $this->make_available_account($company);

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_DONE, $states[onboarding::STEP_GATEWAY]);
    }

    public function test_conta_sem_gateway_habilitado_nao_conclui_a_etapa(): void {
        $company = $this->make_company();
        $this->make_unavailable_account($company);

        $states = onboarding::step_state($company);

        $this->assertSame(onboarding::STATE_PENDING, $states[onboarding::STEP_GATEWAY]);
    }

    public function test_progress_conta_so_as_etapas_obrigatorias(): void {
        $company = $this->make_company(); // cnpj null: documento e opcional, fora do total.

        $progress = onboarding::progress($company);

        $this->assertSame(0, $progress['done']);
        $this->assertSame(2, $progress['total']);
        $this->assertFalse($progress['complete']);
    }

    public function test_progress_fica_completo_com_gateway_e_plano(): void {
        $plan = new plan(0, (object) [
            'shortname' => 'start_free_teste',
            'name' => 'Start Free',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $company = $this->make_company();
        $company->set('planid', (int) $plan->get('id'));
        $company->update();
        $this->make_available_account($company);

        $progress = onboarding::progress($company);

        $this->assertSame(2, $progress['done']);
        $this->assertSame(100, $progress['percent']);
        $this->assertTrue($progress['complete']);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha (classe não existe ainda)**

```bash
docker exec -u 1000:33 -e COMPOSER_HOME=/tmp/composer ldg-courses-moodle-1 \
  php /var/www/html/public/admin/tool/phpunit/cli/init.php
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/phpunit public/blocks/marketplace/tests/onboarding_test.php
```

Esperado: erro de classe/arquivo não encontrado (`onboarding.php` não existe).

- [ ] **Step 3: Implementação mínima**

Criar `public/blocks/marketplace/classes/onboarding.php`:

```php
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

namespace block_marketplace;

use local_marketplace\company;

/**
 * Estado do checklist de ativacao de uma empresa, TODO derivado dos campos
 * que ja existem em local_marketplace - nenhuma tabela nova.
 *
 * @package    block_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onboarding {
    /** @var string */
    const STEP_GATEWAY = 'gateway';
    /** @var string */
    const STEP_PLAN = 'plan';
    /** @var string */
    const STEP_DOCUMENT = 'document';

    /** @var string */
    const STATE_DONE = 'done';
    /** @var string */
    const STATE_PENDING = 'pending';
    /** @var string */
    const STATE_OPTIONAL = 'optional';

    /**
     * Estado de cada etapa, derivado dos campos existentes.
     *
     * @param company $company
     * @return array<string, string> etapa => estado
     */
    public static function step_state(company $company): array {
        return [
            self::STEP_GATEWAY => self::gateway_state($company),
            self::STEP_PLAN => $company->get_plan() !== null ? self::STATE_DONE : self::STATE_PENDING,
            self::STEP_DOCUMENT => $company->get('cnpj') !== null ? self::STATE_DONE : self::STATE_OPTIONAL,
        ];
    }

    /**
     * Uma conta so conclui a etapa se account::is_available() for verdadeiro -
     * vinculada nao basta, precisa do gateway habilitado (armadilha ja
     * documentada no CLAUDE.md).
     *
     * @param company $company
     * @return string
     */
    private static function gateway_state(company $company): string {
        foreach ($company->get_payment_accounts() as $account) {
            if ($account->is_available()) {
                return self::STATE_DONE;
            }
        }

        return self::STATE_PENDING;
    }

    /**
     * Progresso agregado, contando so as etapas obrigatorias (documento e opcional).
     *
     * @param company $company
     * @return array{done: int, total: int, percent: int, complete: bool}
     */
    public static function progress(company $company): array {
        $states = self::step_state($company);
        $required = array_filter($states, fn ($state) => $state !== self::STATE_OPTIONAL);
        $done = count(array_filter($required, fn ($state) => $state === self::STATE_DONE));
        $total = count($required);

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 100,
            'complete' => $done === $total,
        ];
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/phpunit public/blocks/marketplace/tests/onboarding_test.php
```

Esperado: `OK (8 tests, ...)`.

- [ ] **Step 5: phpcs**

```bash
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle -p --report=summary public/blocks/marketplace/classes/onboarding.php \
  public/blocks/marketplace/tests/onboarding_test.php
```

Esperado: saída vazia (ler o total, não cortar — regra do `CLAUDE.md`).

- [ ] **Step 6: Commit**

```bash
git add public/blocks/marketplace/classes/onboarding.php \
        public/blocks/marketplace/tests/onboarding_test.php
git commit -m "feat(block_marketplace): deriva o checklist de ativacao da empresa"
```

---

### Task 2: `block_marketplace::get_content()` despacha por perfil (dono de empresa vs. aluno)

**Files:**
- Modify: `public/blocks/marketplace/block_marketplace.php`
- Modify: `public/blocks/marketplace/version.php` (bump)
- Test: `public/blocks/marketplace/tests/content_test.php` (adicionar casos,
  sem tocar nos existentes)

**Interfaces:**
- Consumes: `onboarding::step_state()`, `onboarding::progress()`,
  `onboarding::STEP_*`, `onboarding::STATE_DONE` (Task 1);
  `local_marketplace\company::get_by_member(int $userid): array`.
- Produces: comportamento observável de `block_marketplace::get_content()`
  (não uma API nova) — nenhuma task depois desta consome símbolo novo.

- [ ] **Step 1: Escrever os testes novos (vermelho)**

Adicionar ao final da classe `content_test` em
`public/blocks/marketplace/tests/content_test.php` (antes do `}` final),
mantendo os testes existentes intactos:

```php
    /**
     * Dono de empresa incompleta ve o checklist, nao o widget de assinatura.
     *
     * @return void
     */
    public function test_dono_de_empresa_incompleta_ve_checklist(): void {
        // A propria empresa de teste ja nasce sem plano e sem conta - api::create_company()
        // no setUp() nao define planid nem conta de pagamento.
        $this->setUser($this->company_owner());

        $content = $this->content();

        $this->assertNotSame('', $content->text);
        $this->assertStringContainsString('0%', $content->text);
        $this->assertStringNotContainsString('Curso de Xadrez', $content->text);
    }

    /**
     * Empresa com checklist completo nao mostra nada (sem gateway/plano pendente
     * e sem assinatura de aluno) - mesmo comportamento vazio de sempre.
     *
     * @return void
     */
    public function test_dono_de_empresa_completa_nao_ve_checklist(): void {
        $plan = new \local_marketplace\plan(0, (object) [
            'shortname' => 'contenttest_plan',
            'name' => 'Plano de teste',
            'monthlyfee' => 0,
            'commissionpct' => 10,
        ]);
        $plan->create();
        $this->company->set('planid', (int) $plan->get('id'));
        $this->company->update();

        $account = new \core_payment\account(0, (object) [
            'name' => 'Conta completa',
            'idnumber' => 'contenttestacc' . random_int(100000, 999999),
        ]);
        $account->create();
        $gateway = new \core_payment\account_gateway(0, (object) [
            'accountid' => $account->get('id'),
            'gateway' => 'mercadopago',
            'enabled' => true,
        ]);
        $gateway->create();
        $link = new \local_marketplace\company_account(0, (object) [
            'companyid' => $this->company->get('id'),
            'country' => 'BR',
            'accountid' => $account->get('id'),
        ]);
        $link->create();

        $this->setUser($this->company_owner());

        $this->assertSame('', $this->content()->text);
    }

    /**
     * O usuario dono da empresa de teste - api::create_company() no setUp()
     * usa ownerid=2, que e sempre o admin num Moodle recem-instalado/testado.
     *
     * @return \stdClass
     */
    protected function company_owner(): \stdClass {
        global $DB;

        return $DB->get_record('user', ['id' => 2]);
    }
```

- [ ] **Step 2: Rodar e confirmar que falha**

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/phpunit public/blocks/marketplace/tests/content_test.php
```

Esperado: os dois testes novos falham (bloco ainda não sabe distinguir dono
de empresa), os antigos continuam passando.

- [ ] **Step 3: Implementação**

Editar `public/blocks/marketplace/block_marketplace.php`. A classe
`block_marketplace` fica no namespace global (arquivo de bloco do Moodle) —
por isso o `use` de uma classe **namespaced** feita nesta task
(`block_marketplace\onboarding`, Task 1) é obrigatório, senão
`onboarding::progress()` não resolve. Trocar o topo (usos) e o
`get_content()`:

```php
use block_marketplace\onboarding;
use local_marketplace\company;
use local_marketplace\entitlement;
use local_marketplace\offer;
use local_marketplace\task\notify_expiring;
```

`get_content()` passa a ser:

```php
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $companies = company::get_by_member((int) $USER->id);
        if ($companies) {
            return $this->content_for_owner(reset($companies));
        }

        return $this->content_for_student((int) $USER->id);
    }

    /**
     * Checklist de ativacao, para quem e membro de uma empresa ainda
     * incompleta. Empresa completa nao mostra nada aqui - o widget de
     * assinatura do aluno so aparece pra quem NAO e dono de empresa.
     *
     * @param company $company
     * @return stdClass
     */
    private function content_for_owner(company $company): stdClass {
        $progress = onboarding::progress($company);
        if ($progress['complete']) {
            return $this->content;
        }

        $labels = [
            onboarding::STEP_GATEWAY => get_string('stepgateway', 'block_marketplace'),
            onboarding::STEP_PLAN => get_string('stepplan', 'block_marketplace'),
        ];
        $states = onboarding::step_state($company);

        $items = [];
        foreach ($labels as $step => $label) {
            $done = $states[$step] === onboarding::STATE_DONE;
            $items[] = html_writer::div(
                ($done ? '&check; ' : '') . $label,
                $done ? 'small text-success' : 'small'
            );
        }

        $this->content->text = html_writer::div(
            get_string('onboardingprogress', 'block_marketplace', $progress['percent']),
            'fw-semibold mb-2'
        ) . implode('', $items);

        $this->content->footer = html_writer::link(
            new moodle_url('/local/marketplace/company.php', ['company' => $company->get('shortname')]),
            get_string('onboardingcontinue', 'block_marketplace')
        );

        return $this->content;
    }

    /**
     * Comportamento original: assinaturas ativas do aluno.
     *
     * @param int $userid
     * @return stdClass
     */
    private function content_for_student(int $userid): stdClass {
        $ents = entitlement::get_active_for_user($userid);
        if (!$ents) {
            return $this->content;
        }

        $now = time();
        $notice = notify_expiring::NOTICE_DAYS * DAYSECS;
        $items = [];

        foreach ($ents as $ent) {
            $offer = offer::get_record(['id' => (int) $ent->get('offerid')]);
            $companyrec = company::get_record(['id' => (int) $ent->get('companyid')]);
            if (!$offer || !$companyrec) {
                continue;
            }
            $end = (int) $ent->get('timeend');
            $cancelled = (int) $ent->get('norenew') === 1;
            $recurring = $offer->get('accessmode') === offer::ACCESS_RECURRING;

            if ($end === 0 && !$cancelled) {
                continue;
            }
            $parts = [];
            $parts[] = html_writer::tag('strong', format_string($offer->get('name')));
            $parts[] = html_writer::div(format_string($companyrec->get('name')), 'small text-muted');

            if ($end > 0) {
                $days = (int) ceil(($end - $now) / DAYSECS);
                $urgent = ($end - $now) < $notice;
                $parts[] = html_writer::div(
                    get_string($cancelled ? 'blockendson' : 'blockrenewson', 'block_marketplace',
                        userdate($end, get_string('strftimedaydate'))),
                    'small ' . ($urgent ? 'text-danger fw-semibold' : 'text-muted')
                );
                if ($urgent && !$cancelled && $recurring && $offer->accepts_cycle((int) $ent->get('cycles'))) {
                    $parts[] = html_writer::link(
                        new moodle_url('/local/marketplace/offers.php', [
                            'company' => $companyrec->get('shortname'),
                            'highlight' => $offer->get('id'),
                        ]),
                        get_string('blockpaynow', 'block_marketplace', $days),
                        ['class' => 'btn btn-sm btn-primary mt-1']
                    );
                }
            }
            if ($cancelled) {
                $parts[] = html_writer::div(get_string('blockcancelled', 'block_marketplace'), 'small fst-italic');
            }
            $items[] = html_writer::div(implode('', $parts), 'mb-3');
        }

        if (!$items) {
            return $this->content;
        }

        $this->content->text = implode('', $items);
        $this->content->footer = html_writer::link(
            new moodle_url('/local/marketplace/mysubscriptions.php'),
            get_string('blockhistory', 'block_marketplace')
        );

        return $this->content;
    }
```

(O corpo de `content_for_student()` é literalmente o `get_content()` antigo,
só trocando `$USER->id` pelo parâmetro `$userid` — nenhum comportamento
muda, por isso os testes antigos continuam de pé.)

Bump em `public/blocks/marketplace/version.php`:

```php
$plugin->version   = 2026091800; // Era 2026082501.
```

- [ ] **Step 4: Rodar e confirmar que passa**

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  vendor/bin/phpunit public/blocks/marketplace/tests/content_test.php
```

Esperado: `OK` — os 8 testes antigos + os 2 novos.

- [ ] **Step 5: phpcs**

```bash
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle -p --report=summary public/blocks/marketplace/block_marketplace.php \
  public/blocks/marketplace/tests/content_test.php public/blocks/marketplace/version.php
```

- [ ] **Step 6: Commit**

```bash
git add public/blocks/marketplace/block_marketplace.php \
        public/blocks/marketplace/tests/content_test.php \
        public/blocks/marketplace/version.php
git commit -m "feat(block_marketplace): mostra checklist de ativacao para dono de empresa incompleta"
```

---

### Task 3: strings de idioma e verificação visual no navegador

**Files:**
- Modify: `public/blocks/marketplace/lang/en/block_marketplace.php`
- Modify: `public/blocks/marketplace/lang/pt_br/block_marketplace.php`
- Modify: `public/blocks/marketplace/lang/es/block_marketplace.php`

**Interfaces:**
- Consumes: chaves `onboardingprogress`, `onboardingcontinue`, `stepgateway`,
  `stepplan` lidas por `get_string()` na Task 2.
- Produces: nada consumido por outra task — última desta fase.

- [ ] **Step 1: Adicionar as quatro chaves, em ordem alfabética**

`lang/en/block_marketplace.php` (posição alfabética real, entre as chaves
existentes — não anexar no fim):

```php
$string['blockcancelled'] = 'Cancelled';
$string['blockendson'] = 'Ends on {$a}';
$string['blockhistory'] = 'All subscriptions and payments';
$string['blockpaynow'] = 'Pay now — {$a} day(s) left';
$string['blockrenewson'] = 'Renews by {$a}';
$string['marketplace:addinstance'] = 'Add a new subscriptions block';
$string['marketplace:myaddinstance'] = 'Add a new subscriptions block to the Dashboard';
$string['onboardingcontinue'] = 'Continue setup';
$string['onboardingprogress'] = 'Setup {$a}% complete';
$string['pluginname'] = 'My subscriptions';
$string['privacy:metadata'] = 'The My subscriptions block shows entitlements stored by local_marketplace and stores no data of its own.';
$string['stepgateway'] = 'Payment account linked';
$string['stepplan'] = 'Plan selected';
```

`lang/pt_br/block_marketplace.php` — mesmas chaves, mesma ordem alfabética
(conferir contra o arquivo real antes de editar, pois já tem traduções
próprias além destas quatro):

```php
$string['onboardingcontinue'] = 'Continuar configuracao';
$string['onboardingprogress'] = 'Configuracao {$a}% concluida';
$string['stepgateway'] = 'Conta de pagamento vinculada';
$string['stepplan'] = 'Plano selecionado';
```

`lang/es/block_marketplace.php` — idem:

```php
$string['onboardingcontinue'] = 'Continuar configuracion';
$string['onboardingprogress'] = 'Configuracion {$a}% completa';
$string['stepgateway'] = 'Cuenta de pago vinculada';
$string['stepplan'] = 'Plan seleccionado';
```

- [ ] **Step 2: phpcs nos três arquivos (a ordem alfabética é regra do CI)**

```bash
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle -p --report=summary \
  public/blocks/marketplace/lang/en/block_marketplace.php \
  public/blocks/marketplace/lang/pt_br/block_marketplace.php \
  public/blocks/marketplace/lang/es/block_marketplace.php
```

Esperado: saída vazia.

- [ ] **Step 3: Purge caches e conferir no navegador (regra do projeto: UI se prova no navegador)**

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php admin/cli/purge_caches.php
```

No Chrome, logado como o dono de uma empresa sem plano/conta (ou criando uma
via `local_partners` + aprovação manual), abrir `/my/` e conferir:

- O bloco mostra "Configuração X% completa" e as duas linhas de etapa.
- Depois de vincular conta E escolher plano pela página
  `local/marketplace/company.php`, o bloco volta a ficar vazio (empresa
  completa não mostra nada, igual hoje).
- Um aluno comum (sem empresa) continua vendo só as próprias assinaturas.

- [ ] **Step 4: Commit**

```bash
git add public/blocks/marketplace/lang/
git commit -m "docs(block_marketplace): strings do checklist de ativacao, pt_br/en/es"
```

---

## Verificação final (fecha o plano)

- `vendor/bin/phpunit --testsuite block_marketplace_testsuite` (ou o caminho
  dos dois arquivos de teste) verde.
- `phpcs --standard=moodle -p --report=summary public/blocks/marketplace/`
  sem saída.
- Conferência visual no Chrome (Step 3 da Task 3) feita e sem regressão no
  widget de assinatura do aluno.
- PR só depois disso — nunca antes, para não repetir o commit órfão (ver
  `CLAUDE.md`, "PR e merjeado antes de eu terminar").

## Fora de escopo (deliberado, não esquecido)

- Fila de aprovação nova / modo automático de aprovação — não existe
  `approvalmode` hoje, e criar um é decisão de produto, não desta
  implementação.
- Páginas `gateway.php`/`plan.php`/`document.php`/`terms.php` novas —
  `local/marketplace/company.php` já resolve as duas primeiras, e as outras
  duas já são resolvidas por `local_partners`.
- Bloco dentro do curso (C1–C4 do mockup) — mesmo `onboarding::step_state()`
  serviria, mas é ampliação de escopo (`applicable_formats()` já inclui
  `course-view`; falta decidir o que aparece lá) — plano futuro, quando este
  estiver em produção e revisado.
