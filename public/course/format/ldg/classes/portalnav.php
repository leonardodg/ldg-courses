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
 * A navegacao do portal.
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
 * A navegacao do portal: quatro destinos, um corrente.
 *
 * Sao tres superficies para a MESMA navegacao - menu a esquerda no desktop,
 * abas no meio e barra embaixo no celular. Por isso a lista de destinos e
 * montada uma vez e desenhada tres, e quem esconde duas delas e o CSS.
 *
 * O estado viaja na URL, e nao no cliente: botao voltar, favorito e Ctrl+clique
 * saem de graca, e a pagina continua funcionando sem JavaScript.
 *
 * @package    format_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class portalnav {
    /** @var string[] A ordem em que os destinos aparecem, sempre. */
    public const ORDER = [catalog::AULA, catalog::MATERIAL, catalog::CERTIFICADO, catalog::FORUM];

    /** @var course_format */
    protected course_format $format;

    /** @var catalog */
    protected catalog $catalog;

    /** @var string */
    protected string $current;

    /** @var cm_info|null A aula em foco, que viaja junto nos links. */
    protected ?cm_info $selected;

    /**
     * Construtor.
     *
     * @param course_format $format
     * @param catalog $catalog
     * @param string $requested O que veio na URL, cru.
     * @param cm_info|null $selected A aula em foco, para nao se perder na troca.
     */
    public function __construct(
        course_format $format,
        catalog $catalog,
        string $requested,
        ?cm_info $selected = null
    ) {
        $this->format = $format;
        $this->catalog = $catalog;
        $this->selected = $selected;

        // Pedido invalido, desconhecido ou de destino vazio cai em aulas. Nao e
        // erro: URL velha, link colado e curso que perdeu o forum sao normais, e
        // nenhum deles justifica uma tela de erro para o aluno.
        $valid = in_array($requested, self::ORDER, true) && $catalog->has($requested);

        $this->current = $valid ? $requested : catalog::AULA;
    }

    /**
     * O destino corrente.
     *
     * @return string
     */
    public function current(): string {
        return $this->current;
    }

    /**
     * Os destinos que existem neste curso.
     *
     * Destino sem conteudo nao aparece - e o que desarma o risco de classificar
     * por tipo sem o professor configurar nada.
     *
     * @return array
     */
    public function destinations(): array {
        $destinations = [];

        // A aula em foco viaja em TODOS os links: ir em Materiais e voltar tem
        // que devolver a mesma aula, e nao a primeira do curso.
        $options = [];

        if ($this->selected !== null) {
            $options['lesson'] = $this->selected->id;
        }

        // Forum e Certificado sao destino de item UNICO, sem lista propria que
        // mostre cadeado por item (ao contrario de Aula e Material) - has()
        // sozinho mostraria a aba com o unico item bloqueado, levando a um
        // embed cru de "acesso negado" em vez de a aba nao aparecer.
        $onlyifvisible = [catalog::FORUM, catalog::CERTIFICADO];

        foreach (self::ORDER as $key) {
            $hascontent = in_array($key, $onlyifvisible, true)
                ? $this->catalog->has_visible($key)
                : $this->catalog->has($key);
            if (!$hascontent) {
                continue;
            }

            $destinations[] = [
                'key' => $key,
                'label' => get_string('view' . $key, 'format_ldg'),
                'url' => $this->format->get_view_url(null, $options + ['ldgview' => $key])->out(false),
                'active' => $key === $this->current,
            ];
        }

        return $destinations;
    }
}
