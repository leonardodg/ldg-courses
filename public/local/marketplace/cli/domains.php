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
 * Mapa de dominios dos vendedores.
 *
 * O mapa e regenerado sozinho quando um dominio muda pela tela. Este comando
 * existe para os casos em que isso nao aconteceu: restauracao de backup,
 * alteracao feita direto no banco, ou o arquivo apagado junto com o dataroot.
 *
 * Tambem serve de diagnostico: sem ele, descobrir por que um dominio nao
 * resolve exigiria abrir o arquivo gerado a mao.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_marketplace\api;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'rebuild' => false,
    ],
    ['h' => 'help', 'r' => 'rebuild']
);

if ($options['help']) {
    cli_writeln("Mapa de dominios dos vendedores.

Opcoes:
  -h, --help       Esta ajuda
  -r, --rebuild    Regenera o mapa a partir do banco

Sem opcao, apenas mostra o mapa em vigor.
");
    exit(0);
}

$file = $CFG->dataroot . '/marketplace_domains.php';

if ($options['rebuild']) {
    $n = api::regenerate_domain_map();
    cli_writeln("Mapa regenerado: {$n} dominio(s).");
    cli_writeln("Arquivo: {$file}");
    cli_writeln('');
}

if (!file_exists($file)) {
    cli_writeln('Nenhum mapa gerado ainda. Rode com --rebuild.');
    exit(0);
}

$map = include($file);

if (!is_array($map) || !$map) {
    cli_writeln('O mapa esta vazio: nenhuma empresa ativa tem dominio cadastrado.');
    exit(0);
}

cli_heading('Dominios em vigor');
cli_writeln(sprintf('%-40s %-20s %s', 'HOST', 'EMPRESA', 'WWWROOT'));
foreach ($map as $host => $entry) {
    cli_writeln(sprintf('%-40s %-20s %s', $host, $entry['company'], $entry['wwwroot']));
}

cli_writeln('');
cli_writeln('Cada host acima precisa de:');
cli_writeln('  1. DNS apontando para esta maquina');
cli_writeln('  2. server block no nginx encaminhando com o Host preservado');
cli_writeln('  3. certificado emitido pelo certbot');
cli_writeln('');
cli_writeln('Sem os tres, o dominio resolve para o site padrao ou nao resolve.');

exit(0);
