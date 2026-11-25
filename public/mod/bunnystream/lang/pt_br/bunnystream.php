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
 * Strings do mod_bunnystream.
 *
 * @package    mod_bunnystream
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bunnystream:addinstance'] = 'Adicionar um novo vídeo Bunny Stream';
$string['bunnystream:manage'] = 'Gerenciar vídeos Bunny Stream (enviar, editar, apagar)';
$string['bunnystream:view'] = 'Ver vídeos Bunny Stream';
$string['cancel_upload'] = 'Cancelar envio';
$string['caption_add'] = 'Enviar .vtt';
$string['caption_remove'] = 'Remover';
$string['caption_transcribe'] = 'Transcrever automaticamente (en)';
$string['captions_empty'] = 'Nenhuma legenda ainda.';
$string['captions_section'] = 'Legendas';
$string['chapter_add'] = 'Adicionar capítulo';
$string['chapter_save'] = 'Salvar capítulos';
$string['chapters_section'] = 'Capítulos';
$string['choose_video'] = 'Escolher vídeo';
$string['completion_percent'] = 'Limite de conclusão (%)';
$string['completion_percent_help'] = 'Marca a atividade como concluída quando o aluno tiver assistido pelo menos este percentual do vídeo.';
$string['delete_confirm_body'] = 'O vídeo será removido da Bunny.net e desta atividade. Isto não pode ser desfeito.';
$string['delete_confirm_no'] = 'Cancelar';
$string['delete_confirm_title'] = 'Apagar este vídeo?';
$string['delete_confirm_yes'] = 'Sim, apagar';
$string['delete_video'] = 'Apagar vídeo';
$string['drop_video_here'] = 'Arraste um arquivo de vídeo aqui, ou';
$string['error'] = '{$a}';
$string['error_failed_upload'] = 'O envio falhou. Confira sua conexão e tente de novo.';
$string['error_no_video'] = 'Nenhum vídeo enviado ainda.';
$string['error_not_configured'] = 'Esta empresa ainda não tem uma library da Bunny Stream. Peça à plataforma para concluir o provisionamento antes de enviar vídeo.';
$string['error_too_many_deletes'] = 'Muitos pedidos de remoção. Tente de novo em um minuto.';
$string['error_too_many_uploads'] = 'Muitos envios. Tente de novo em um minuto.';
$string['failed_label'] = 'Algo deu errado com este vídeo.';
$string['modulename'] = 'Bunny Stream';
$string['modulename_help'] = 'A atividade Bunny Stream deixa o vendedor enviar um vídeo direto para a library da Bunny.net Stream da própria empresa, e incorpora com player assinado e protegido contra hotlink. Cada empresa tem a própria library — um vendedor nunca alcança o vídeo de outra empresa.';
$string['modulenameplural'] = 'Vídeos Bunny Stream';
$string['name'] = 'Nome da atividade';
$string['pluginadministration'] = 'Administração do Bunny Stream';
$string['pluginname'] = 'Bunny Stream';
$string['privacy:metadata:bunny_net'] = 'A Bunny.net é o provedor externo de streaming de vídeo. Incorporar um vídeo faz o navegador do aluno fazer uma requisição a iframe.mediadelivery.net e ao hostname de CDN da empresa; nenhum identificador de usuário do Moodle é enviado.';
$string['privacy:metadata:bunny_net:guid'] = 'O GUID do vídeo na Bunny pedido para reprodução.';
$string['privacy:metadata:bunnystream_progress'] = 'Guarda o maior percentual assistido por usuário e por vídeo, para calcular conclusão e nota.';
$string['privacy:metadata:bunnystream_progress:bunnystreamid'] = 'O id da atividade Bunny Stream.';
$string['privacy:metadata:bunnystream_progress:max_percent'] = 'Maior percentual do vídeo que o usuário assistiu.';
$string['privacy:metadata:bunnystream_progress:timemodified'] = 'Quando o progresso foi atualizado pela última vez.';
$string['privacy:metadata:bunnystream_progress:userid'] = 'O id do usuário no Moodle.';
$string['processing_elapsed'] = 'Decorrido';
$string['processing_label'] = 'A Bunny está processando seu vídeo — pode levar alguns minutos em arquivos maiores.';
$string['replace_video'] = 'Substituir vídeo';
$string['setting_completion_percent'] = 'Limite de conclusão padrão (%)';
$string['setting_completion_percent_desc'] = 'Percentual padrão do vídeo que o aluno precisa assistir para a atividade ser marcada como concluída. Pode ser sobrescrito por atividade.';
$string['settings_heading'] = 'Bunny Stream';
$string['settings_heading_desc'] = 'Cada empresa provisiona a própria library da Bunny.net Stream automaticamente na criação — não há library nem chave de API para colar aqui. A chave de conta da plataforma, que cria libraries novas, fica em Administração do site → Extensões → Plugins locais → Marketplace.';
$string['style_cinema'] = 'Cinema';
$string['style_compact'] = 'Compacto';
$string['style_default'] = 'Padrão';
$string['style_padded'] = 'Com espaçamento';
$string['style_rounded'] = 'Cantos arredondados';
$string['thumbnail_replace'] = 'Substituir miniatura';
$string['thumbnail_section'] = 'Miniatura';
$string['uploading_label'] = 'Enviando';
$string['video'] = 'Vídeo';
$string['video_duration'] = 'Duração';
$string['video_id'] = 'GUID na Bunny';
$string['video_style'] = 'Estilo de exibição do vídeo';
$string['video_title'] = 'Título do vídeo';
