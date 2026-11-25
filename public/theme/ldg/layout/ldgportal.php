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
 * Layout do portal do aluno.
 *
 * Usado pela pagina do curso no format_ldg, e so para quem nao esta editando.
 * Quem pede este layout e o \format_ldg\hook_callbacks; aqui so se desenha.
 *
 * O que este layout NAO tem, de proposito: navbar, drawers, breadcrumb e
 * regiao de bloco. O que ele nao pode deixar de ter e o menu de usuario - tirar
 * o chrome do Moodle nao pode tirar junto o "sair" do aluno.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

// O menu de usuario vem do core, pela mesma classe que o layout de drawers usa.
// Reaproveitar em vez de montar um menu proprio mantem perfil, preferencias,
// trocar papel e sair funcionando sem este tema saber o que cada um faz.
$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

// A navegacao secundaria do curso - Configuracoes, Participantes, Notas,
// Relatorios - so aparece para quem tem a capacidade, porque e o proprio core
// que monta a lista a partir das permissoes. Para o aluno ela vem vazia, e o
// cabecalho do portal continua limpo.
//
// Sem isto, o professor tinha que LIGAR A EDICAO para chegar nas notas: o portal
// nao tem navbar, e a navegacao do curso mora nela. Ninguem espera isso, e foi o
// que o usuario notou ao abrir o portal como manager.
//
// E o mesmo helper do layout de drawers deste tema, nao um menu paralelo: um
// segundo lugar decidindo o que o professor pode ver seria uma segunda verdade
// sobre permissao. A trava de "so quem gerencia" e o argumento true.
$secondarynavigation = \theme_ldg\util\layouthead::secondary_more_menu(true);

$templatecontext = [
    'sitename' => \theme_ldg\util\layouthead::sitename(),
    'output' => $OUTPUT,
    // A classe e 'ldg-portal-page', e NAO 'ldg-portal': o formato usa .ldg-portal na div
    // raiz dele, e as duas classes iguais casavam com a mesma regra: o recuo
    // lateral era aplicado duas vezes, 24px no body mais 24px na div, contra os
    // 24px do desenho. Medido no Chrome.
    'bodyattributes' => $OUTPUT->body_attributes(['ldg-portal-page']),
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'secondarymoremenu' => $secondarynavigation,
    'coursename' => format_string($COURSE->fullname),

    // O botao de fechar devolve o aluno para a area dele, e nao para a home do
    // site: quem esta dentro de um curso quer voltar para a lista de cursos.
    'exiturl' => (new moodle_url('/my/'))->out(false),
    'exitlabel' => get_string('exitcourse', 'format_ldg'),
];

echo $OUTPUT->render_from_template('theme_ldg/ldgportal', $templatecontext);
