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
 * Ambiente local espelho da producao: confere (padrao) ou aplica (--apply).
 *
 * A lista abaixo saiu do mdl_config de courses.leodg.dev em 25/11/2026,
 * comparado com uma instalacao limpa. So entra o que DIFERE do padrao do
 * Moodle, mais o contrato dos plugins do projeto. Mudou a producao de proposito,
 * muda aqui no mesmo PR.
 *
 * Uso, de dentro do container:
 *   php /var/www/html/.devcontainer/devtools/ambiente-local.php           # confere
 *   php /var/www/html/.devcontainer/devtools/ambiente-local.php --apply   # aplica e confere
 *
 * Guia: docs/dev/ambiente-local.md.
 *
 * @package    core
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'apply' => false,
        'secrets' => __DIR__ . '/../secrets/ambiente-local.env',
        'backups' => $CFG->dataroot . '/ambiente-local-backups',
    ],
    ['h' => 'help']
);

if ($options['help'] || $unrecognised) {
    cli_writeln("Confere (ou aplica, com --apply) o espelho da producao.

  --apply          Grava config, usuarios, cursos e matricula; depois confere
  --secrets=ARQ    Segredos (padrao: .devcontainer/secrets/ambiente-local.env)
  --backups=DIR    Pasta com os .mbz a restaurar (padrao: <dataroot>/ambiente-local-backups)

Sai 0 so quando tudo confere.");
    exit($unrecognised ? 2 : 0);
}

/** Categoria onde os backups da Ivana Academy sao restaurados. */
const IVANA_CATEGORY = 'Ivana Academy (teste)';

/** Prefixo do idnumber dos cursos restaurados: e ele que torna o --apply idempotente. */
const IVANA_IDNUMBER = 'ambiente-local:';

/**
 * Dominios de e-mail que podem existir no banco local. O SMTP e o real: qualquer
 * outro dominio e uma pessoa de verdade que pode receber e-mail daqui.
 * example.com e dos acessos de teste; exemplo.local, do seed_demo.php.
 */
const SAFE_EMAIL_DOMAINS = ['example.com', 'exemplo.local'];

// Configuracao do core que difere da instalacao limpa. [nome, valor].
$mirrorcore = [
    ['allowcategorythemes', '1'],
    ['allowemojipicker', '0'],
    ['allowguestmymoodle', '0'],
    ['autolang', '0'],
    ['bloglevel', '1'],
    ['blogshowcommentscount', '0'],
    ['blogusecomments', '0'],
    ['customusermenuitems', "accessibility,theme_ldg|/theme/ldg/accessibility.php\n" .
        "profile,moodle|/user/profile.php\ngrades,grades|/grade/report/mygrades.php\n" .
        "calendar,core_calendar|/calendar/view.php?view=month\nprivatefiles,moodle|/user/files.php\n" .
        "reports,core_reportbuilder|/reportbuilder/index.php"],
    ['defaulthomepage', '3'],
    ['enableblogs', '0'],
    ['enabledashboard', '0'],
    ['enablemycourses', '1'],
    ['enablemyhome', '1'],
    ['enablenotes', '0'],
    ['file_redactor_exifremovermimetype', 'image/jpeg'],
    ['forcelogin', '0'],
    ['forceloginforprofileimage', '1'],
    ['frontpage', ''],
    ['lang', 'en'],
    ['messaging', '0'],
    ['navadduserpostslinks', '0'],
    ['navshowfullcoursenames', '1'],
    ['navshowmycoursecategories', '1'],
    ['noreplyaddress', 'noreply@leodg.dev'],
    ['registerauth', 'email'],
    ['smtpauthtype', 'LOGIN'],
    ['smtphosts', 'smtp.gmail.com:587'],
    ['smtpmaxbulk', '1'],
    ['smtpsecure', 'tls'],
    ['supportavailability', '0'],
    ['supportemail', 'callme@leodg.dev'],
    ['supportname', 'Admin LDG'],
    ['theme', 'ldg'],
    ['timezone', 'Europe/London'],
    ['useblogassociations', '0'],
    ['usecomments', '0'],
    ['useexternalblogs', '0'],
    // Diferenca DELIBERADA: o tunel e publico, e indexar mp.leodg.dev seria
    // conteudo duplicado de courses.leodg.dev aos olhos do buscador.
    ['allowindexing', '0'],
    ['opentowebcrawlers', '0'],
];

// Plugins: o que difere do padrao, e o contrato dos plugins do projeto.
// [plugin, nome, valor].
$mirrorplugins = [
    ['local_marketplace', 'commissionbase', 'gross'],
    ['local_marketplace', 'defaultcountry', 'BR'],
    ['local_marketplace', 'defaultfeepercent', '25'],
    ['local_partners', 'analyticsid', ''],
    ['local_partners', 'cookiesurl', ''],
    ['local_partners', 'enablelanding', '1'],
    ['local_partners', 'enablerecaptcha', '1'],
    ['local_partners', 'frontpagemode', 'landing'],
    ['local_partners', 'maxperhour', '3'],
    ['local_partners', 'privacyurl', ''],
    ['local_partners', 'requireemailconfirmation', '1'],
    ['local_partners', 'termsurl', ''],
    ['local_partners', 'unconfirmedretentiondays', '7'],
    ['paygw_mercadopago', 'cardcapture', 'brick'],
    ['paygw_mercadopago', 'methodboleto', '1'],
    ['paygw_mercadopago', 'methodcard', '1'],
    ['paygw_mercadopago', 'methodpix', '1'],
    ['paygw_mercadopago', 'platformsite', 'MLB'],
    // Diferenca DELIBERADA: compra no localhost nao cobra de verdade.
    ['paygw_mercadopago', 'testmode', '1'],
    ['theme_boost_union', 'enablefooterbutton', 'enablefooterbuttonnone'],
    ['theme_ldg', 'brandcolor', '#007AFF'],
    ['theme_ldg', 'defaultcolormode', 'dark'],
    ['theme_ldg', 'enablecolormodetoggle', '1'],
    ['theme_ldg', 'fontsite', 'Inter'],
    ['theme_ldg', 'preset', 'default.scss'],
    ['theme_ldg', 'secondarymenucolor', '#1E1E1E'],
];

$secrets = read_secrets($options['secrets']);

// Segredos: so o que esta preenchido entra. Vazio vira falha no --check, e nao
// valor vazio gravado por cima do que ja funcionava.
$secretcore = [
    ['smtpuser', $secrets['SMTP_USER'] ?? ''],
    ['smtppass', $secrets['SMTP_PASS'] ?? ''],
];
$secretplugins = [
    ['paygw_mercadopago', 'clientid', $secrets['MP_CLIENTID'] ?? ''],
    ['paygw_mercadopago', 'clientsecret', $secrets['MP_CLIENTSECRET'] ?? ''],
    ['paygw_mercadopago', 'publickeytest', $secrets['MP_PUBLICKEYTEST'] ?? ''],
];

if ($options['apply']) {
    cli_heading('Aplicando o espelho');
    $admin = get_admin();
    \core\session\manager::set_user($admin);

    foreach ($mirrorcore as [$name, $value]) {
        set_config($name, $value);
    }
    foreach ($mirrorplugins as [$plugin, $name, $value]) {
        set_config($name, $value, $plugin);
    }
    foreach ($secretcore as [$name, $value]) {
        if ($value !== '') {
            set_config($name, $value);
        }
    }
    foreach ($secretplugins as [$plugin, $name, $value]) {
        if ($value !== '') {
            set_config($name, $value, $plugin);
        }
    }
    // Opcional por natureza: vazio desliga a conferencia de assinatura, como
    // esta hoje em producao. Por isso grava mesmo vazio.
    set_config('webhooksecret', $secrets['MP_WEBHOOKSECRET'] ?? '', 'paygw_mercadopago');

    \core\plugininfo\paygw::enable_plugin('mercadopago', 1);
    \core\plugininfo\format::enable_plugin('social', 1);

    apply_users($secrets);
    $restored = apply_courses($options['backups']);
    cli_writeln("  cursos restaurados agora: {$restored}");
    apply_enrolment($secrets);

    purge_caches();
    cli_writeln('');
}

cli_heading('Conferindo');
$failures = 0;
$report = function (bool $ok, string $message) use (&$failures): void {
    cli_writeln(($ok ? '  ok    ' : '  FALHA ') . $message);
    if (!$ok) {
        $failures++;
    }
};

foreach ($mirrorcore as [$name, $value]) {
    $actual = (string) get_config('core', $name);
    $report($actual === $value, "core/{$name}" . ($actual === $value ? '' : " = '{$actual}', esperado '{$value}'"));
}
foreach ($mirrorplugins as [$plugin, $name, $value]) {
    $actual = (string) get_config($plugin, $name);
    $report($actual === $value, "{$plugin}/{$name}" . ($actual === $value ? '' : " = '{$actual}', esperado '{$value}'"));
}

// Segredos: confere que estao preenchidos e iguais ao arquivo, sem imprimir.
foreach ($secretcore as [$name, $value]) {
    $actual = (string) get_config('core', $name);
    $report($value !== '' && $actual === $value, "core/{$name} (segredo)" . ($value === '' ? ': vazio no arquivo' : ''));
}
foreach ($secretplugins as [$plugin, $name, $value]) {
    $actual = (string) get_config($plugin, $name);
    $report($value !== '' && $actual === $value, "{$plugin}/{$name} (segredo)" . ($value === '' ? ': vazio no arquivo' : ''));
}

$enabledgw = \core\plugininfo\paygw::get_enabled_plugins();
$report(isset($enabledgw['mercadopago']), 'gateway mercadopago habilitado');
$report(empty(get_config('format_social', 'disabled')), 'formato social habilitado');

check_users($secrets, $report);
check_courses($report);
check_safe_emails($report);

cli_writeln('');
if ($failures) {
    cli_writeln("{$failures} divergencia(s). Rode com --apply, ou confira o arquivo de segredos.");
    exit(1);
}
cli_writeln('Espelho conferido.');
exit(0);

/**
 * Le o arquivo CHAVE=valor dos segredos. Sem arquivo, devolve vazio: o --check
 * acusa cada chave faltando em vez de morrer aqui.
 *
 * @param string $path Caminho do arquivo
 * @return array<string, string>
 */
function read_secrets(string $path): array {
    if (!is_readable($path)) {
        cli_writeln("  aviso: segredos nao encontrados em {$path}");
        return [];
    }
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        // Sem parse_ini_file: ele interpreta "!", ";" e aspas, e senha gerada
        // tem esses caracteres.
        if (preg_match('/^\s*([A-Z0-9_]+)=(.*)$/', $line, $m)) {
            $values[$m[1]] = trim($m[2]);
        }
    }
    return $values;
}

/**
 * Senha do admin e o aluno de teste.
 *
 * @param array $secrets Segredos lidos
 */
function apply_users(array $secrets): void {
    global $CFG, $DB;

    $admin = get_admin();
    if (!empty($secrets['ADMIN_PASSWORD'])) {
        update_internal_user_password($admin, $secrets['ADMIN_PASSWORD']);
    }

    $username = $secrets['STUDENT_USERNAME'] ?? '';
    if ($username === '' || empty($secrets['STUDENT_PASSWORD'])) {
        return;
    }
    $student = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$student) {
        $id = user_create_user((object) [
            'username' => $username,
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => 'Aluno',
            'lastname' => 'Teste',
            'email' => $secrets['STUDENT_EMAIL'] ?? $username . '@example.com',
            'password' => $secrets['STUDENT_PASSWORD'],
        ], true, false);
        $student = $DB->get_record('user', ['id' => $id]);
        cli_writeln("  aluno criado: {$username}");
    } else {
        update_internal_user_password($student, $secrets['STUDENT_PASSWORD']);
    }
}

/**
 * Restaura cada .mbz da pasta (recursivo) numa categoria propria, SEM usuarios.
 *
 * Os backups da Ivana Academy trazem as alunas reais, sem anonimizar. Com o
 * SMTP de producao ligado, restaurar com usuarios mandaria e-mail para elas.
 *
 * @param string $dir Pasta dos backups
 * @return int Quantos cursos foram restaurados nesta rodada
 */
function apply_courses(string $dir): int {
    global $DB;

    if (!is_dir($dir)) {
        cli_writeln("  aviso: sem pasta de backups em {$dir}");
        return 0;
    }
    $category = $DB->get_record('course_categories', ['name' => IVANA_CATEGORY]);
    if (!$category) {
        $category = core_course_category::create(['name' => IVANA_CATEGORY])->get_db_record();
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $restored = 0;
    foreach ($files as $file) {
        if (strtolower($file->getExtension()) !== 'mbz') {
            continue;
        }
        $idnumber = IVANA_IDNUMBER . $file->getBasename('.mbz');
        if ($DB->record_exists('course', ['idnumber' => $idnumber])) {
            continue;
        }
        restore_one($file->getPathname(), (int) $category->id, $idnumber);
        $restored++;
    }
    return $restored;
}

/**
 * Restaura um backup como curso novo.
 *
 * @param string $path Arquivo .mbz
 * @param int $categoryid Categoria de destino
 * @param string $idnumber Marca do curso, para nao restaurar duas vezes
 */
function restore_one(string $path, int $categoryid, string $idnumber): void {
    global $DB;

    $admin = get_admin();
    $backupdir = 'ambiente_local_' . uniqid();
    $temp = make_backup_temp_directory($backupdir);
    get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($path, $temp);

    [$fullname, $shortname] = restore_dbops::calculate_course_names(0, 'restaurando', 'restaurando');
    $courseid = restore_dbops::create_new_course($fullname, $shortname, $categoryid);

    // Curso pela metade nao pode ficar: sem idnumber, o proximo --apply nao o
    // reconhece e restaura de novo, deixando uma copia quebrada para tras.
    try {
        restore_into($backupdir, $courseid, $admin->id, $path);
    } catch (Throwable $e) {
        delete_course($courseid, false);
        throw $e;
    }

    $DB->set_field('course', 'idnumber', $idnumber, ['id' => $courseid]);
    $course = get_course($courseid);
    cli_writeln("  curso restaurado: {$course->fullname} (id {$courseid})");
}

/**
 * Executa o restore ja sem usuarios.
 *
 * @param string $backupdir Pasta temporaria com o backup extraido
 * @param int $courseid Curso de destino, recem-criado
 * @param int $userid Quem restaura
 * @param string $path Arquivo de origem, so para a mensagem de erro
 */
function restore_into(string $backupdir, int $courseid, int $userid, string $path): void {
    $rc = new restore_controller(
        $backupdir,
        $courseid,
        backup::INTERACTIVE_NO,
        backup::MODE_GENERAL,
        $userid,
        backup::TARGET_NEW_COURSE
    );
    $plan = $rc->get_plan();
    foreach (['users', 'role_assignments', 'userscompletion', 'comments', 'badges', 'logs', 'grade_histories'] as $name) {
        if ($plan->setting_exists($name)) {
            $plan->get_setting($name)->set_value(false);
        }
    }
    // Sem a confirmacao, o restore segue com usuarios se a trava falhar em silencio.
    if ($plan->setting_exists('users') && $plan->get_setting('users')->get_value()) {
        throw new coding_exception("restore de {$path} ainda com usuarios");
    }
    $rc->execute_precheck();
    $rc->execute_plan();
    $rc->destroy();
}

/**
 * Matricula o aluno, como estudante, no primeiro curso restaurado.
 *
 * @param array $secrets Segredos lidos
 */
function apply_enrolment(array $secrets): void {
    global $DB;

    $student = $DB->get_record('user', ['username' => $secrets['STUDENT_USERNAME'] ?? '', 'deleted' => 0]);
    $course = first_ivana_course();
    if (!$student || !$course) {
        return;
    }
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
    if (!$instance) {
        $manual->add_default_instance($course);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
    }
    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
    $manual->enrol_user($instance, $student->id, $roleid);
    cli_writeln("  aluno matriculado em: {$course->fullname} (id {$course->id})");
}

/**
 * O curso onde o aluno de teste fica matriculado: o primeiro restaurado VISIVEL.
 *
 * Alguns backups da Ivana vem com o curso oculto, e aluno nao ve curso oculto:
 * a matricula existia, o --check passava, e o painel dizia "No courses".
 *
 * @return stdClass|null
 */
function first_ivana_course(): ?stdClass {
    global $DB;
    $courses = $DB->get_records_select(
        'course',
        $DB->sql_like('idnumber', ':p') . ' AND visible = 1',
        ['p' => IVANA_IDNUMBER . '%'],
        'id ASC',
        '*',
        0,
        1
    );
    return $courses ? reset($courses) : null;
}

/**
 * Admin e aluno entram com a senha do arquivo; o aluno esta matriculado.
 *
 * @param array $secrets Segredos lidos
 * @param callable $report Registra o resultado
 */
function check_users(array $secrets, callable $report): void {
    global $DB;

    $admin = get_admin();
    $report(!empty($secrets['ADMIN_PASSWORD'])
        && validate_internal_user_password($admin, $secrets['ADMIN_PASSWORD']), 'admin entra com ADMIN_PASSWORD');

    $username = $secrets['STUDENT_USERNAME'] ?? '';
    $student = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    $report(
        (bool) $student && !empty($secrets['STUDENT_PASSWORD'])
        && validate_internal_user_password($student, $secrets['STUDENT_PASSWORD']),
        "aluno '{$username}' entra com STUDENT_PASSWORD"
    );

    $course = first_ivana_course();
    $enrolled = $student && $course
        && is_enrolled(context_course::instance($course->id), $student, '', true)
        && user_has_role_assignment(
            $student->id,
            (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            context_course::instance($course->id)->id
        );
    $report((bool) $enrolled, 'aluno matriculado como estudante' . ($course ? " em '{$course->fullname}'" : ''));

    // Matricula nao e acesso: conferir pelo mesmo caminho que o painel usa.
    $visible = $student && $course && can_access_course($course, $student);
    $report((bool) $visible, 'aluno consegue abrir o curso matriculado');
}

/**
 * Ha cursos restaurados, e o cenario do marketplace (seed_demo.php) existe.
 *
 * @param callable $report Registra o resultado
 */
function check_courses(callable $report): void {
    global $DB;

    $count = $DB->count_records_select('course', $DB->sql_like('idnumber', ':p'), ['p' => IVANA_IDNUMBER . '%']);
    $report($count > 0, "cursos da Ivana restaurados: {$count}");

    // O seed nomeia a conta pelo pais: "Empresa Demo (BR)".
    $accountid = $DB->get_field_select(
        'payment_accounts',
        'id',
        $DB->sql_like('name', ':n'),
        ['n' => 'Empresa Demo%'],
        IGNORE_MULTIPLE
    );
    $report((bool) $accountid, "conta de pagamento 'Empresa Demo' (seed_demo.php --company=demo)");

    // O vinculo so nasce pelo OAuth, no navegador, logado como o vendedor de
    // teste do Mercado Pago. Fica vermelho ate alguem fazer isso.
    $linked = $accountid && $DB->record_exists(
        'payment_gateways',
        ['accountid' => $accountid, 'gateway' => 'mercadopago', 'enabled' => 1]
    );
    $report((bool) $linked, 'Mercado Pago vinculado e habilitado na conta da Empresa Demo (OAuth pelo tunel)');
}

/**
 * Nenhum usuario com e-mail de pessoa real. A guarda que da sentido ao resto:
 * o SMTP daqui e o da producao.
 *
 * @param callable $report Registra o resultado
 */
function check_safe_emails(callable $report): void {
    global $DB;

    $unsafe = [];
    foreach ($DB->get_records('user', ['deleted' => 0], 'id', 'id, username, email') as $user) {
        if ($user->username === 'guest') {
            continue;
        }
        $domain = strtolower(substr(strrchr((string) $user->email, '@') ?: '', 1));
        if (!in_array($domain, SAFE_EMAIL_DOMAINS, true)) {
            $unsafe[] = "{$user->username} (id {$user->id})";
        }
    }
    $report(!$unsafe, 'nenhum e-mail real no banco' . ($unsafe ? ': ' . implode(', ', $unsafe) : ''));
}
