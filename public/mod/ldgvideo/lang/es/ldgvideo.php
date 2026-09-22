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
 * Strings del mod_ldgvideo.
 *
 * @package    mod_ldgvideo
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aspectratio'] = 'Proporción';
$string['aspectratio_help'] = 'La forma del cuadro del video. El ancho siempre sigue a la columna donde está el video, así que acá solo se elige la forma.

Si pegaste el fragmento para incrustar, la forma se leyó de ahí y ya está seleccionada.';
$string['configaspectratio'] = 'La proporción propuesta para las actividades de video nuevas.';
$string['erroraddressnotvideo'] = 'Esto no parece una dirección de video. Pegá el enlace del video, o el fragmento para incrustar completo que te dio el servicio de video.';
$string['errornopermissions'] = 'Lo sentís, pero no tenés permiso para ver esta actividad de video.';
$string['errorplayerdisabled'] = 'Este servicio de video se reconoce, pero su reproductor está apagado en este sitio. Pedile a un administrador que lo habilite en Administración del sitio > Plugins > Reproductores multimedia.';
$string['errorselfhosted'] = 'Los videos se alojan fuera de la plataforma. Pegá una dirección de YouTube, Vimeo u otro servicio de video, y no un archivo de este sitio.';
$string['ldgvideo:addinstance'] = 'Agregar una actividad de video';
$string['ldgvideo:view'] = 'Ver una actividad de video';
$string['modulename'] = 'Video';
$string['modulename_help'] = 'La actividad de video muestra una clase en video, alojada en un servicio externo como YouTube o Vimeo.

Pegá la dirección del video, o el fragmento para incrustar completo que te da el servicio: la dirección se extrae y el tamaño fijo en píxeles se descarta, así el video se ajusta al espacio que recibe: en la computadora, en el celular, y con las barras laterales del curso abiertas u ocultas.

El video nunca se sube a este sitio.';
$string['modulename_link'] = 'mod/ldgvideo/view';
$string['modulenameplural'] = 'Videos';
$string['page-mod-ldgvideo-x'] = 'Cualquier página de la actividad de video';
$string['pluginadministration'] = 'Administración del video';
$string['pluginname'] = 'Video';
$string['printintro'] = 'Mostrar la descripción del video';
$string['printintroexplain'] = 'Mostrar la descripción arriba del video.';
$string['privacy:metadata'] = 'La actividad de Video no guarda ningún dato personal. La dirección del video es contenido del curso, y no dato personal, pero tené en cuenta que reproducir un video incrustado envía la dirección IP del estudiante al servicio de video.';
$string['ratioclassic'] = '4:3';
$string['ratiolandscape'] = '16:9 (panorámico)';
$string['ratioportrait'] = '9:16 (vertical)';
$string['search:activity'] = 'Video';
$string['videoheader'] = 'Video';
$string['videourl'] = 'Dirección del video';
$string['videourl_help'] = 'Pegá cualquiera de estos:

* el enlace de la barra de direcciones, como "https://www.youtube.com/watch?v=xxxxxxxxxxx"
* el enlace corto para compartir, como "https://youtu.be/xxxxxxxxxxx"
* el fragmento para incrustar completo, que empieza con "<iframe"

Pegues lo que pegues, solo se guarda la dirección. Los parámetros de rastreo y el "width" y "height" fijos se descartan.';
