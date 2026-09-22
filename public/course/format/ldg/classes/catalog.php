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
 * O curso separado por papel da atividade.
 *
 * @package    format_ldg
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ldg;

use cm_info;
use core_courseformat\base as course_format;

/**
 * O curso separado por PAPEL da atividade, e nao por secao.
 *
 * O portal tem quatro destinos e o professor nao configura nenhum: o papel sai
 * do TIPO da atividade. E decisao de projeto - tela de aluno que depende de
 * configuracao certa e tela que um dia aparece vazia, e o curso ja diz o que
 * cada coisa e no momento em que o professor escolhe o tipo.
 *
 * O que esta classe NAO faz: nao pergunta ao local_marketplace quem comprou o
 * que. O bloqueio chega pronto em cm_info->uservisible, resolvido pelo
 * availability_marketplace antes de qualquer coisa aqui rodar.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string Aula: e o que sobra, e o destino padrao do portal. */
    public const AULA = 'lessons';

    /** @var string Material de apoio. */
    public const MATERIAL = 'materials';

    /** @var string Forum de alunos. */
    public const FORUM = 'forum';

    /** @var string Certificado. */
    public const CERTIFICADO = 'certificate';

    /** @var string Nao entra em destino nenhum. */
    public const NENHUM = 'none';

    /** @var string[] Modulos que sao material de apoio. */
    public const MODS_MATERIAL = ['resource', 'folder', 'url'];

    /** @var array<string, cm_info[]> Preenchido uma vez, no construtor. */
    protected array $buckets;

    /**
     * Varre o curso UMA vez.
     *
     * @param course_format $format
     */
    public function __construct(course_format $format) {
        $this->buckets = [
            self::AULA => [],
            self::MATERIAL => [],
            self::FORUM => [],
            self::CERTIFICADO => [],
        ];

        $modinfo = $format->get_modinfo();

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$format->is_section_visible($section)) {
                continue;
            }

            foreach ($modinfo->sections[$section->sectionnum] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $type = self::classify($cm);

                if ($type === self::NENHUM) {
                    continue;
                }

                $this->buckets[$type][$cm->id] = $cm;
            }
        }
    }

    /**
     * O papel de uma atividade.
     *
     * A ausencia de URL e o primeiro corte, e o unico que pega o ROTULO. Nao da
     * para usar is_of_type_that_can_display() para isso: ele e
     * plugin_supports(FEATURE_CAN_DISPLAY, true), com default TRUE, e o
     * mod_label nunca declara essa flag - entao rotulo passaria por aula. O que
     * nao tem pagina para abrir nao pode ser destino de navegacao.
     *
     * O par de visibilidade continua sendo o mesmo do core, e e o
     * is_of_type_that_can_display() que mantem o banco de questoes fora, porque
     * o mod_qbank declara FEATURE_CAN_DISPLAY como false.
     *
     * @param cm_info $cm
     * @return string
     */
    public static function classify(cm_info $cm): string {
        if (empty($cm->url)) {
            return self::NENHUM;
        }

        if (!$cm->is_visible_on_course_page() || !$cm->is_of_type_that_can_display()) {
            return self::NENHUM;
        }

        if ($cm->modname === 'forum') {
            return self::FORUM;
        }

        if ($cm->modname === 'customcert') {
            return self::CERTIFICADO;
        }

        if (in_array($cm->modname, self::MODS_MATERIAL, true)) {
            return self::MATERIAL;
        }

        return self::AULA;
    }

    /**
     * As atividades de um destino, na ordem do curso.
     *
     * @param string $type
     * @return cm_info[]
     */
    public function get(string $type): array {
        return $this->buckets[$type] ?? [];
    }

    /**
     * Se o destino tem conteudo.
     *
     * E o que decide se ele aparece no menu: destino vazio nao vira aba vazia, e
     * e assim que classificar por tipo deixa de ter o risco de mostrar uma tela
     * sem nada dentro.
     *
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool {
        return !empty($this->buckets[$type]);
    }

    /**
     * Se o destino tem conteudo que o usuario ATUAL pode ver.
     *
     * Diferente de has(): FORUM e CERTIFICADO sao destinos de item UNICO, sem
     * lista propria que mostre cadeado por item (ao contrario de AULA e
     * MATERIAL). Mostrar a aba com o unico item bloqueado levava o aluno a um
     * embed cru de "acesso negado", em vez da aba simplesmente nao aparecer.
     *
     * @param string $type
     * @return bool
     */
    public function has_visible(string $type): bool {
        foreach ($this->buckets[$type] ?? [] as $cm) {
            if ($cm->uservisible) {
                return true;
            }
        }

        return false;
    }
}
