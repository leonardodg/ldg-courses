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
 * Strings do mod_ldgvideo.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aspectratio'] = 'Proporção';
$string['aspectratio_help'] = 'O formato do quadro do vídeo. A largura acompanha sempre a coluna em que o vídeo está, então aqui se escolhe só o formato.

Se você colou o trecho de incorporação, o formato foi lido dele e já está selecionado.';
$string['configaspectratio'] = 'A proporção proposta para atividades de vídeo novas.';
$string['erroraddressnotvideo'] = 'Isto não parece um endereço de vídeo. Cole o link do vídeo, ou o trecho de incorporação inteiro que o serviço de vídeo te deu.';
$string['errornopermissions'] = 'Desculpe, mas você não tem permissão para ver esta atividade de vídeo.';
$string['errorplayerdisabled'] = 'Este serviço de vídeo é reconhecido, mas o player dele está desligado neste site. Peça a um administrador para habilitá-lo em Administração do site > Plugins > Players de mídia.';
$string['errorselfhosted'] = 'Os vídeos ficam hospedados fora da plataforma. Cole um endereço do YouTube, do Vimeo ou de outro serviço de vídeo — não um arquivo deste site.';
$string['ldgvideo:addinstance'] = 'Acrescentar uma atividade de vídeo';
$string['ldgvideo:view'] = 'Ver uma atividade de vídeo';
$string['modulename'] = 'Vídeo';
$string['modulename_help'] = 'A atividade de vídeo mostra uma aula em vídeo, hospedada num serviço externo como o YouTube ou o Vimeo.

Cole o endereço do vídeo, ou o trecho de incorporação inteiro que o serviço te dá: o endereço é extraído e o tamanho fixo em pixels é descartado, então o vídeo se ajusta ao espaço que recebe — no computador, no celular, e com as laterais do curso abertas ou escondidas.

O vídeo nunca é enviado para este site.';
$string['modulename_link'] = 'mod/ldgvideo/view';
$string['modulenameplural'] = 'Vídeos';
$string['page-mod-ldgvideo-x'] = 'Qualquer página da atividade de vídeo';
$string['pluginadministration'] = 'Administração do vídeo';
$string['pluginname'] = 'Vídeo';
$string['printintro'] = 'Mostrar a descrição do vídeo';
$string['printintroexplain'] = 'Mostrar a descrição acima do vídeo.';
$string['privacy:metadata'] = 'A atividade de Vídeo não guarda nenhum dado pessoal. O endereço do vídeo é conteúdo do curso, e não dado pessoal — mas note que tocar um vídeo incorporado envia o endereço IP do aluno ao serviço de vídeo.';
$string['ratioclassic'] = '4:3';
$string['ratiolandscape'] = '16:9 (widescreen)';
$string['ratioportrait'] = '9:16 (vertical)';
$string['search:activity'] = 'Vídeo';
$string['videoheader'] = 'Vídeo';
$string['videourl'] = 'Endereço do vídeo';
$string['videourl_help'] = 'Cole qualquer um destes:

* o link da barra de endereços, como "https://www.youtube.com/watch?v=xxxxxxxxxxx"
* o link curto de compartilhar, como "https://youtu.be/xxxxxxxxxxx"
* o trecho de incorporação inteiro, que começa com "<iframe"

O que quer que você cole, só o endereço é guardado. Parâmetros de rastreio e o "width" e "height" fixos são descartados.';
