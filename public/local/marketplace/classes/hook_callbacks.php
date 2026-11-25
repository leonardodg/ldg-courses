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

/**
 * Ganchos do marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Barra a plataforma inteira num dominio de empresa suspensa.
     *
     * A divisao de responsabilidade e:
     *
     *   arquivo gerado -> "este dominio existe?"   fronteira de SEGURANCA
     *   este gancho    -> "a empresa esta ativa?"  estado de execucao
     *
     * O arquivo nao filtra por situacao de proposito. Se filtrasse, o dominio
     * suspenso cairia no site padrao e o visitante veria a plataforma generica
     * sem entender o que houve - e suspender passaria a depender de a
     * regeneracao do arquivo ter funcionado.
     *
     * O bloqueio e da PLATAFORMA, nao so da vitrine. Deixar o resto navegavel
     * permitiria alcancar /course/view.php pelo dominio suspenso, que e
     * exatamente o que a suspensao existe para impedir.
     *
     * Roda no after_config, que dispara logo depois do setup.php: o $DB ja
     * existe e nenhuma pagina comecou a ser montada.
     *
     * @param \core\hook\after_config $hook
     * @return void
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG, $DB;

        // So faz sentido quando a requisicao chegou por dominio de vendedor. O
        // config.php grava esta marca ao casar o Host com o mapa.
        if (empty($CFG->marketplacecompany)) {
            return;
        }

        // Durante instalacao e upgrade a tabela pode nao existir. Uma excecao
        // aqui derrubaria o proprio upgrade que criaria a tabela.
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }

        try {
            $status = $DB->get_field(
                company::TABLE,
                'status',
                ['shortname' => $CFG->marketplacecompany],
                IGNORE_MISSING
            );
        } catch (\Throwable $e) {
            // Banco indisponivel ou tabela ausente: nao e hora de bloquear
            // ninguem, e o erro tem dono proprio.
            return;
        }

        // Empresa que sumiu do banco mas continua no mapa: o arquivo esta
        // velho. Bloqueia igual - o dominio nao pertence mais a ninguem.
        if ($status === false || $status !== company::STATUS_ACTIVE) {
            self::render_unavailable();
        }
    }

    /**
     * Mostra a pagina de indisponivel e encerra.
     *
     * HTML cru, sem o renderer do Moodle: montar $PAGE aqui exigiria um tema, e
     * o tema pode ser justamente o da empresa suspensa. Uma pagina que nao
     * depende de nada e a unica que funciona com certeza neste ponto.
     *
     * Responde 503 com Retry-After: a suspensao costuma ser temporaria, e o
     * status certo evita que buscadores tirem o dominio do indice por causa de
     * uma pendencia administrativa.
     *
     * @return void
     */
    protected static function render_unavailable(): void {
        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 3600');
            header('Cache-Control: no-store');
        }

        // O get_string() aqui e seguro: o after_config roda depois do setup.php.
        $title = get_string('domainsuspendedtitle', 'local_marketplace');
        $body = get_string('domainsuspendedbody', 'local_marketplace');

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex">'
            . '<title>' . s($title) . '</title>'
            . '<style>'
            . 'body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
            . 'background:#faf8f6;color:#1c1815;display:flex;min-height:100vh;'
            . 'align-items:center;justify-content:center;margin:0;padding:1.5rem;line-height:1.6}'
            . 'main{max-width:32rem;text-align:center}'
            . 'h1{font-size:1.5rem;font-weight:650;letter-spacing:-.02em;margin:0 0 .75rem}'
            . 'p{color:#5b524a;margin:0}'
            . '@media(prefers-color-scheme:dark){body{background:#16130f;color:#f2ede7}p{color:#b2a69a}}'
            . '</style></head><body><main>'
            . '<h1>' . s($title) . '</h1>'
            . '<p>' . s($body) . '</p>'
            . '</main></body></html>';

        die();
    }
}
