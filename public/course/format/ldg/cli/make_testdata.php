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
 * Monta o curso de demonstracao do formato LDG.
 *
 * O formato nao tem como ser avaliado num curso vazio: a lista de aulas precisa
 * de aula concluida, aula pendente e aula BLOQUEADA para as tres aparencias
 * ficarem visiveis lado a lado. Montar isso a mao pela interface leva dezenas de
 * cliques e nunca sai igual duas vezes - dai o script.
 *
 * Ele usa o gerador do proprio core (testing_data_generator). Isso nao e
 * gambiarra de teste vazando para producao: e o mesmo caminho do
 * admin/tool/generator, que tambem roda em site real. A vantagem e nao
 * reimplementar as regras de cada modulo.
 *
 * O que monta:
 *
 *   Secao 0  Abertura        1 page
 *   Modulo 1 Fundamentos     3 aulas (1 manual, 2 automaticas)
 *   Modulo 2 Pratica         2 aulas + 1 url
 *   Modulo 3 Avaliacao       1 aula + 1 quiz com pergunta
 *   Modulo 4 Certificado     1 customcert TRANCADO
 *
 * O certificado e o ponto do exercicio. Ele fica atras de DUAS condicoes em E:
 * a conclusao de todas as aulas e a compra de uma oferta. Sem as duas, o aluno
 * ve o cadeado - que e exatamente o estado que o formato precisa saber desenhar.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/questionlib.php');
// A constante RESOURCELIB_DISPLAY_DOWNLOAD vive aqui, e nao no mod_resource.
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'run' => false,
    'reset' => false,
    'shortname' => 'ldg-demo',
    'student' => 'alunoteste',
    'company' => 0,
    'force' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || !$options['run']) {
    cli_writeln(<<<TEXT
    Monta o curso de demonstracao do formato LDG.

    Opcoes:
      --run                 Executa. Sem ela o script so mostra esta ajuda.
      --reset               Apaga o curso e a oferta antes de recriar.
      --shortname=NOME      Nome curto do curso. Padrao: ldg-demo
      --student=USUARIO     Aluno a matricular. Padrao: alunoteste
      --company=ID          Empresa dona da oferta. Padrao: a primeira do site.
      --force               Ignora a trava de ambiente. Leia o aviso abaixo.
      -h, --help            Esta ajuda.

    ATENCAO: o script CRIA curso, oferta e matricula. Ele se recusa a rodar fora
    de um endereco local, porque o deploy publica este diretorio na VPS e um
    curso de demonstracao aparecendo em producao seria dano visivel ao cliente.
    A --force existe para o caso legitimo de um ambiente de homologacao com
    endereco proprio - e nao para calar o aviso em producao.

    Exemplo:
      php course/format/ldg/cli/make_testdata.php --run --reset
    TEXT);
    exit(0);
}

// Trava de ambiente. Vem antes de qualquer escrita, de proposito.
if (!$options['force'] && !preg_match('#^https?://(localhost|127\.0\.0\.1|192\.168\.|10\.)#', $CFG->wwwroot)) {
    cli_error("Recusado: {$CFG->wwwroot} nao parece um ambiente local. Use --force se tiver certeza.");
}

\core\session\manager::set_user(get_admin());

// O gerador avisa por debugging se qualquer um dos dois estiver desligado, e as
// condicoes de acesso do certificado dependem do segundo.
set_config('enablecompletion', 1);
set_config('enableavailability', 1);

$generator = \core\test\phpunit\phpunit_util::get_data_generator();

// Empresa: o certificado e trancado por uma oferta, e oferta pertence a empresa.
$company = $options['company']
    ? $DB->get_record('local_marketplace_company', ['id' => $options['company']], '*', MUST_EXIST)
    : $DB->get_record_sql('SELECT * FROM {local_marketplace_company} ORDER BY id ASC', [], IGNORE_MULTIPLE);

if (!$company) {
    cli_error('Nenhuma empresa cadastrada. Crie uma antes: o certificado depende de uma oferta.');
}

$student = $DB->get_record('user', ['username' => $options['student'], 'deleted' => 0]);

if (!$student) {
    cli_error("Usuario '{$options['student']}' nao encontrado.");
}

$offername = "Acesso ao certificado ({$options['shortname']})";

// Reiniciar apaga tambem a oferta: deixar a antiga para tras faria o proximo
// curso ser trancado por uma oferta orfa, e o cadeado apontaria para o nada.
if ($options['reset']) {
    if ($old = $DB->get_record('course', ['shortname' => $options['shortname']])) {
        cli_writeln("Apagando o curso anterior (id {$old->id})...");
        delete_course($old, false);
    }
    foreach ($DB->get_records('local_marketplace_offer', ['name' => $offername]) as $oldoffer) {
        $DB->delete_records('local_marketplace_offer_course', ['offerid' => $oldoffer->id]);
        $DB->delete_records('local_marketplace_entitlement', ['offerid' => $oldoffer->id]);
        $DB->delete_records('local_marketplace_offer', ['id' => $oldoffer->id]);
    }
}

if ($DB->record_exists('course', ['shortname' => $options['shortname']])) {
    cli_error("Ja existe curso com o nome curto '{$options['shortname']}'. Use --reset para recriar.");
}

cli_writeln('Criando o curso...');

$course = create_course((object) [
    'fullname' => 'Portal LDG - curso de demonstracao',
    'shortname' => $options['shortname'],
    'category' => $company->categoryid,
    'format' => 'ldg',
    'enablecompletion' => 1,
    'summary' => 'Curso montado por script para exercitar o formato LDG: aulas concluidas, '
        . 'pendentes e bloqueadas na mesma tela.',
    'summaryformat' => FORMAT_HTML,
]);

// O formato nao declara numsections - desde o Moodle 4.0 a secao se cria por
// aqui, e nao por opcao de formato.
course_create_sections_if_missing($course, [0, 1, 2, 3, 4]);

$sections = [
    1 => 'Modulo 1 - Fundamentos',
    2 => 'Modulo 2 - Pratica',
    3 => 'Modulo 3 - Avaliacao',
    4 => 'Modulo 4 - Certificado',
];

foreach ($sections as $num => $name) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num], '*', MUST_EXIST);
    course_update_section($course, $section, ['name' => $name]);
}

/**
 * Cria uma aula do tipo page.
 *
 * @param testing_data_generator $generator
 * @param stdClass $course
 * @param int $section
 * @param string $name
 * @param int $completion COMPLETION_TRACKING_MANUAL ou COMPLETION_TRACKING_AUTOMATIC
 * @return stdClass
 */
function format_ldg_make_page($generator, $course, int $section, string $name, int $completion): stdClass {
    $record = [
        'course' => $course->id,
        'section' => $section,
        'name' => $name,
        'intro' => "Aula: {$name}.",
        'introformat' => FORMAT_HTML,
        'content' => '<p>Conteudo de demonstracao de <strong>' . s($name) . '</strong>.</p>',
        'contentformat' => FORMAT_HTML,
        'completion' => $completion,
    ];

    // Conclusao automatica precisa de um criterio; sem completionview ela nunca
    // fecha, e a aula fica pendente para sempre sem dizer por que.
    if ($completion == COMPLETION_TRACKING_AUTOMATIC) {
        $record['completionview'] = 1;
    }

    return $generator->get_plugin_generator('mod_page')->create_instance($record);
}

cli_writeln('Criando as aulas...');

$lessons = [];

$lessons[] = format_ldg_make_page($generator, $course, 0, 'Boas-vindas', COMPLETION_TRACKING_AUTOMATIC);

$lessons[] = format_ldg_make_page($generator, $course, 1, 'Aula 1 - O que e o portal', COMPLETION_TRACKING_AUTOMATIC);
$lessons[] = format_ldg_make_page($generator, $course, 1, 'Aula 2 - Como navegar', COMPLETION_TRACKING_AUTOMATIC);
$lessons[] = format_ldg_make_page($generator, $course, 1, 'Aula 3 - Marque voce mesmo', COMPLETION_TRACKING_MANUAL);

$lessons[] = format_ldg_make_page($generator, $course, 2, 'Aula 4 - Primeiro exercicio', COMPLETION_TRACKING_AUTOMATIC);
$lessons[] = format_ldg_make_page($generator, $course, 2, 'Aula 5 - Segundo exercicio', COMPLETION_TRACKING_MANUAL);

// A url existe para exercitar o override de atividade que ficou pendente de
// avaliacao quando o tema deixou de herdar do Moove.
$lessons[] = $generator->get_plugin_generator('mod_url')->create_instance([
    'course' => $course->id,
    'section' => 2,
    'name' => 'Leitura complementar',
    'externalurl' => 'https://moodle.org/',
    'completion' => COMPLETION_TRACKING_AUTOMATIC,
    'completionview' => 1,
]);

$lessons[] = format_ldg_make_page($generator, $course, 3, 'Aula 6 - Revisao', COMPLETION_TRACKING_AUTOMATIC);

cli_writeln('Criando o material de apoio...');

// Dois materiais que caem em ramos DIFERENTES da regra do miolo, e e por isso
// que sao dois. A pasta tem pagina propria e abre dentro do quadro embutido; o
// arquivo com download forcado vira link, porque dentro de um iframe o download
// dispara e o quadro fica em branco - o aluno acha que a pagina quebrou.
//
// Com um so, metade da regra nunca seria exercida no curso de demonstracao.
$generator->get_plugin_generator('mod_folder')->create_instance([
    'course' => $course->id,
    'section' => 2,
    'name' => 'Anexos do modulo 2',
    'intro' => 'Os arquivos que acompanham as aulas deste modulo.',
]);

$generator->get_plugin_generator('mod_resource')->create_instance([
    'course' => $course->id,
    'section' => 2,
    'name' => 'Apostila em PDF',
    'display' => RESOURCELIB_DISPLAY_DOWNLOAD,
]);

cli_writeln('Criando o forum...');

// O forum e o QUARTO destino do portal. Sem ele o curso de demonstracao mostra
// so tres, e qualquer conferencia visual estaria comparando com uma tela que
// nao existe no desenho.
$generator->get_plugin_generator('mod_forum')->create_instance([
    'course' => $course->id,
    'section' => 3,
    'name' => 'Duvidas da turma',
    'intro' => 'Onde a turma pergunta e responde.',
    'completion' => COMPLETION_TRACKING_MANUAL,
]);

cli_writeln('Criando o quiz...');

$quiz = $generator->get_plugin_generator('mod_quiz')->create_instance([
    'course' => $course->id,
    'section' => 3,
    'name' => 'Prova final',
    'grade' => 10.0,
    'sumgrades' => 1,
    'completion' => COMPLETION_TRACKING_AUTOMATIC,
    'completionview' => 1,
]);

// Quiz sem pergunta nao abre tentativa, e as telas que precisam ser avaliadas -
// cronometro, lista de tentativas, resumo - nunca aparecem.
//
// A pergunta NAO sai do gerador do core, ao contrario de tudo o mais aqui: o
// core_question_generator carrega question/engine/tests/helpers.php, que estende
// PHPUnit\Framework\TestCase, e o container nao tem as dependencias de dev. Sai
// pela API de verdade, que e o caminho da propria tela de edicao.
//
// Desde o Moodle 4.5 a pergunta tambem nao mora mais no contexto do quiz: o
// banco de questoes virou modulo (mod_qbank). O caminho abaixo e o mesmo que o
// tool_generator usa em site real - ver mod/qbank/lib.php:118.
$qbank = $generator->get_plugin_generator('mod_qbank')->create_instance([
    'course' => $course->id,
    'name' => 'Banco de questoes do curso',
], ['section' => 0]);

// O mod_qbank declara FEATURE_CAN_DISPLAY => false, entao ele nao vira uma aula
// solta na lista, apesar de estar na secao 0.
$category = question_get_default_category(context_module::instance($qbank->cmid)->id, true);

$qtype = question_bank::get_qtype('truefalse');
$empty = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
$form = (object) [
    'category' => $category->id,
    'name' => 'O portal mostra o progresso do aluno',
    'questiontext' => [
        'text' => 'O portal mostra o progresso do aluno na propria tela do curso?',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ],
    'generalfeedback' => $empty,
    'defaultmark' => 1,
    'penalty' => 0,
    'correctanswer' => 1,
    'feedbacktrue' => ['text' => 'Isso mesmo.', 'format' => FORMAT_HTML, 'itemid' => 0],
    'feedbackfalse' => ['text' => 'Ela fica no topo da tela.', 'format' => FORMAT_HTML, 'itemid' => 0],
    'showstandardinstruction' => 1,
];
$question = $qtype->save_question((object) [
    'qtype' => 'truefalse',
    'category' => $category->id,
    'createdby' => $USER->id,
], $form);

quiz_add_quiz_question($question->id, $quiz);

// Sem isto o quiz fica valendo zero, e a tentativa termina sem nota nenhuma.
\mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

$lessons[] = $quiz;

cli_writeln('Criando a oferta que tranca o certificado...');

$offer = new \local_marketplace\offer(0, (object) [
    'companyid' => $company->id,
    'name' => $offername,
    'description' => 'Oferta de demonstracao. Ela existe para o certificado aparecer bloqueado.',
    'offertype' => \local_marketplace\offer::TYPE_SINGLE,
    'price' => 97.00,
    'country' => 'BR',
    'currency' => 'BRL',
    'accessmode' => \local_marketplace\offer::ACCESS_LIFETIME,
    'status' => \local_marketplace\offer::STATUS_PUBLISHED,
]);
$offer->create();
$offer->add_course($course->id);

cli_writeln('Criando o certificado bloqueado...');

// As duas condicoes do enunciado, em E. O "100%" nao e uma condicao unica no
// core: vira a conclusao de CADA aula, somada. Se uma aula nova entrar no curso
// depois, ela NAO passa a contar aqui - a arvore e uma fotografia.
$conditions = [];
$showc = [];

foreach ($lessons as $lesson) {
    $conditions[] = \availability_completion\condition::get_json($lesson->cmid, COMPLETION_COMPLETE);
    $showc[] = true;
}

$conditions[] = \availability_marketplace\condition::get_json($offer->get('id'));
$showc[] = true;

$certificate = $generator->get_plugin_generator('mod_customcert')->create_instance([
    'course' => $course->id,
    'section' => 4,
    'name' => 'Certificado de conclusao',
    'intro' => 'Disponivel depois de concluir todas as aulas e adquirir o acesso.',
    'introformat' => FORMAT_HTML,
    'availability' => json_encode((object) [
        'op' => '&',
        'c' => $conditions,
        'showc' => $showc,
    ]),
]);

cli_writeln('Matriculando...');

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual->enrol_user($manualinstance, $student->id, $studentrole->id);

// Autoinscricao ligada para dar para entrar com outro usuario sem passar pelo
// admin toda vez.
$self = enrol_get_plugin('self');
$selfinstanceid = $self->add_instance($course, [
    'status' => ENROL_INSTANCE_ENABLED,
    'roleid' => $studentrole->id,
    'customint6' => 1,
]);

cli_writeln('Marcando parte das aulas como concluidas...');

$completion = new completion_info($course);
$modinfo = get_fast_modinfo($course, $student->id);

// Tres concluidas de oito: o suficiente para a barra sair do zero sem chegar ao
// fim - se chegasse, o certificado destrancaria e o cadeado, que e o que
// queremos ver, sumiria da tela.
$tocomplete = ['Boas-vindas', 'Aula 1 - O que e o portal', 'Aula 3 - Marque voce mesmo'];
$done = 0;

foreach ($modinfo->get_cms() as $cm) {
    if (!in_array($cm->name, $tocomplete, true)) {
        continue;
    }

    if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);
    } else {
        // Conclusao automatica nao aceita ser gravada direto: ela e recalculada
        // a partir do criterio, e so o "visualizou" a satisfaz aqui.
        $completion->set_module_viewed($cm, $student->id);
    }

    $done++;
}

rebuild_course_cache($course->id, true);

$url = new moodle_url('/course/view.php', ['id' => $course->id]);

cli_writeln('');
cli_writeln('Pronto.');
cli_writeln("  Curso        {$course->fullname} (id {$course->id})");
cli_writeln('  Endereco     ' . $url->out(false));
cli_writeln('  Aulas        ' . count($lessons) . ", {$done} concluidas para {$student->username}");
cli_writeln("  Certificado  cmid {$certificate->cmid}, trancado por " . count($conditions) . ' condicoes em E');
cli_writeln('  Oferta       ' . $offer->get('id') . " ({$offername})");
cli_writeln('  Autoinscr.   instancia ' . $selfinstanceid);
cli_writeln('');
cli_writeln("Entre como {$student->username} para ver o cadeado no certificado.");
