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
 * Strings de mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bunnystream:addinstance'] = 'Añadir un nuevo video Bunny Stream';
$string['bunnystream:manage'] = 'Gestionar videos Bunny Stream (subir, editar, borrar)';
$string['bunnystream:view'] = 'Ver videos Bunny Stream';
$string['cancel_upload'] = 'Cancelar subida';
$string['caption_add'] = 'Subir .vtt';
$string['caption_remove'] = 'Quitar';
$string['caption_transcribe'] = 'Transcribir automáticamente (en)';
$string['captions_empty'] = 'Todavía no hay subtítulos.';
$string['captions_section'] = 'Subtítulos';
$string['chapter_add'] = 'Añadir capítulo';
$string['chapter_save'] = 'Guardar capítulos';
$string['chapters_section'] = 'Capítulos';
$string['choose_video'] = 'Elegir video';
$string['completion_percent'] = 'Umbral de finalización (%)';
$string['completion_percent_help'] = 'Marca la actividad como completada cuando el alumno haya visto al menos este porcentaje del video.';
$string['delete_confirm_body'] = 'El video se eliminará de Bunny.net y de esta actividad. Esto no se puede deshacer.';
$string['delete_confirm_no'] = 'Cancelar';
$string['delete_confirm_title'] = '¿Borrar este video?';
$string['delete_confirm_yes'] = 'Sí, borrar';
$string['delete_video'] = 'Borrar video';
$string['drop_video_here'] = 'Soltá un archivo de video acá, o';
$string['error'] = '{$a}';
$string['error_failed_upload'] = 'La subida falló. Revisá tu conexión e intentá de nuevo.';
$string['error_no_video'] = 'Todavía no se subió ningún video.';
$string['error_not_configured'] = 'Esta empresa todavía no tiene una library de Bunny Stream. Pedile a la plataforma que termine de aprovisionarla antes de subir video.';
$string['error_too_many_deletes'] = 'Demasiados pedidos de borrado. Probá de nuevo en un minuto.';
$string['error_too_many_uploads'] = 'Demasiadas subidas. Probá de nuevo en un minuto.';
$string['failed_label'] = 'Algo salió mal con este video.';
$string['modulename'] = 'Bunny Stream';
$string['modulename_help'] = 'La actividad Bunny Stream deja que un vendedor suba un video directo a la library de Bunny.net Stream de su propia empresa, y lo incrusta con un reproductor firmado y protegido contra hotlink. Cada empresa tiene su propia library — un vendedor nunca llega al video de otra empresa.';
$string['modulenameplural'] = 'Videos Bunny Stream';
$string['name'] = 'Nombre de la actividad';
$string['pluginadministration'] = 'Administración de Bunny Stream';
$string['pluginname'] = 'Bunny Stream';
$string['privacy:metadata:bunny_net'] = 'Bunny.net es el proveedor externo de streaming de video. Incrustar un video hace que el navegador del alumno haga una solicitud a iframe.mediadelivery.net y al hostname de CDN de la empresa; no se envía ningún identificador de usuario de Moodle.';
$string['privacy:metadata:bunny_net:guid'] = 'El GUID del video en Bunny pedido para la reproducción.';
$string['privacy:metadata:bunnystream_progress'] = 'Guarda el mayor porcentaje visto por usuario y por video, para calcular finalización y calificación.';
$string['privacy:metadata:bunnystream_progress:bunnystreamid'] = 'El id de la actividad Bunny Stream.';
$string['privacy:metadata:bunnystream_progress:max_percent'] = 'Mayor porcentaje del video que el usuario vio.';
$string['privacy:metadata:bunnystream_progress:timemodified'] = 'Cuándo se actualizó el progreso por última vez.';
$string['privacy:metadata:bunnystream_progress:userid'] = 'El id del usuario en Moodle.';
$string['processing_elapsed'] = 'Transcurrido';
$string['processing_label'] = 'Bunny está procesando tu video — puede tardar unos minutos en archivos más grandes.';
$string['replace_video'] = 'Reemplazar video';
$string['setting_completion_percent'] = 'Umbral de finalización por defecto (%)';
$string['setting_completion_percent_desc'] = 'Porcentaje por defecto del video que el alumno debe ver para que la actividad se marque como completada. Se puede cambiar por actividad.';
$string['settings_heading'] = 'Bunny Stream';
$string['settings_heading_desc'] = 'Cada empresa aprovisiona su propia library de Bunny.net Stream automáticamente al crearse — no hay library ni clave de API para pegar acá. La clave de cuenta de la plataforma, que crea libraries nuevas, está en Administración del sitio → Complementos → Plugins locales → Marketplace.';
$string['style_cinema'] = 'Cine';
$string['style_compact'] = 'Compacto';
$string['style_default'] = 'Por defecto';
$string['style_padded'] = 'Con espaciado';
$string['style_rounded'] = 'Esquinas redondeadas';
$string['thumbnail_replace'] = 'Reemplazar miniatura';
$string['thumbnail_section'] = 'Miniatura';
$string['uploading_label'] = 'Subiendo';
$string['video'] = 'Video';
$string['video_duration'] = 'Duración';
$string['video_id'] = 'GUID en Bunny';
$string['video_style'] = 'Estilo de visualización del video';
$string['video_title'] = 'Título del video';
