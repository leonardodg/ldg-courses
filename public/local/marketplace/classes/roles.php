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
 * Os papeis de empresa.
 *
 * @package    local_marketplace
 * @author     LeoDG <callme@leodg.dev>
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_marketplace;

/**
 * Os dois papeis de empresa, e a fronteira que os dois compartilham.
 *
 * SAO DOIS PAPEIS, divididos por RISCO e nao por hierarquia:
 *
 *   marketplacemanager  o dono. Monta curso E mexe em dinheiro e em gente.
 *   marketplaceseller   o editor. So monta curso.
 *
 * Ate 04/09/2026 havia um papel so, e o memberrole 'owner' era distincao
 * apenas de registro - as capabilities eram identicas. Quem so montava curso
 * alcancava a credencial financeira da empresa.
 *
 * A FRONTEIRA DO PLANO FREE VIVE AQUI, e nao numa capability propria.
 *
 * O plano Free existe para custar ZERO de banda: o video e embed de servico
 * externo. O que garante isso nao e o formulario do mod_ldgvideo recusar uma
 * URL do proprio site - aquilo protege contra o engano, nao contra a intencao.
 * O que garante e a AUSENCIA das capabilities que colocam arquivo no
 * moodledata: sem arquivo local, nao ha arquivo local para apontar.
 *
 * Nao vale confiar em maxbytes: limita tamanho, nao tipo, e um video curto
 * passaria.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles {
    /** @var string O dono da empresa: monta curso e responde pelo dinheiro. */
    public const MANAGER = 'marketplacemanager';

    /** @var string O editor: monta curso, e so. */
    public const SELLER = 'marketplaceseller';

    /**
     * @var string[] O que o editor faz - e o gerente tambem, porque ele e o
     *               editor mais a coluna comercial.
     */
    public const ALLOW_EDITOR = [
        'local/marketplace:publishcourse',
        'moodle/category:viewcourselist',
        'moodle/course:create',
        'moodle/course:manageactivities',
        'moodle/course:update',
        'moodle/course:viewhiddencourses',
        'moodle/course:visibility',
    ];

    /**
     * @var string[] So do gerente: dinheiro e gente.
     *
     * moodle/role:assign fica aqui de proposito. Quem pode atribuir papel pode
     * tentar se dar um que permita upload - nao funciona, porque o PROHIBIT
     * abaixo nao e sobreponivel, mas a capability nao tem por que estar nas
     * maos de quem so monta curso.
     *
     * O ESTORNO NAO ESTA AQUI, e a ausencia e deliberada. Ele devolve dinheiro
     * de verdade e revoga acesso, e nao tem desfazer - fica com o
     * administrador da plataforma, que concede por empresa quando confiar em
     * quem vai usar. Ver local/marketplace:refundsale em db/access.php.
     */
    public const ALLOW_MANAGER = [
        'enrol/fee:config',
        'enrol/manual:enrol',
        'local/marketplace:managecompany',
        'local/marketplace:managepayment',
        'local/marketplace:managesales',
        'local/marketplace:viewreport',
        'moodle/course:enrolreview',
        'moodle/role:assign',
    ];

    /**
     * @var string[] Os repositorios que NAO precisam ser proibidos.
     *
     * O criterio e um so: o repositorio devolve um LINK EXTERNO, ou copia o
     * arquivo para dentro do moodledata? Todo seletor de arquivos do Moodle
     * copia o que foi escolhido para a area de arquivos - inclusive o do
     * Flickr e o do Wikimedia, que parecem "externos" e nao sao.
     *
     *   youtube    devolve o endereco do video, que e exatamente o que se quer
     *   areafiles  mostra o que JA esta embutido naquele mesmo campo de texto;
     *              escolher dali nao faz entrar arquivo novo
     *
     * Qualquer repositorio fora desta lista entra na proibicao.
     */
    public const REPOSITORIES_ALLOWED = ['youtube', 'areafiles'];

    /**
     * @var string[] Todo caminho conhecido para colocar arquivo no moodledata.
     *
     * PROHIBIT, e nao PREVENT. O vendedor tambem carrega o papel de usuario
     * autenticado, que PERMITE repository/upload:view - o archetype 'user' e
     * CAP_ALLOW no db/access.php do proprio repositorio. E no Moodle, quando
     * dois papeis se contradizem no mesmo contexto, o ALLOW vence o PREVENT.
     * So o PROHIBIT nao pode ser sobreposto por papel nenhum, em contexto
     * nenhum, que e a semantica correta para uma regra de negocio.
     *
     * A lista e ESTATICA de proposito. Ler os repositorios habilitados e
     * proibir dinamicamente pareceria mais completo, e seria seguranca que
     * depende de a configuracao estar certa. A lista e o guarda; quem avisa
     * quando ela envelhece e open_repositories(), lida pelo cli/status.php.
     */
    public const PROHIBIT = [
        'moodle/contentbank:upload',
        'moodle/course:ignorefilesizelimits',
        'moodle/course:managefiles',
        'moodle/restore:uploadfile',
        'moodle/user:manageownfiles',
        'repository/contentbank:view',
        'repository/coursefiles:view',
        'repository/dropbox:view',
        'repository/equella:view',
        'repository/filesystem:view',
        'repository/flickr:view',
        'repository/flickr_public:view',
        'repository/googledocs:view',
        'repository/local:view',
        'repository/merlot:view',
        'repository/nextcloud:view',
        'repository/onedrive:view',
        'repository/recent:view',
        'repository/s3:view',
        'repository/upload:view',
        'repository/url:view',
        'repository/user:view',
        'repository/webdav:view',
        'repository/wikimedia:view',
    ];

    /**
     * O papel do Moodle que corresponde a um memberrole.
     *
     * O vinculo sao duas coisas inseparaveis: a linha em
     * local_marketplace_member e o papel no contexto da categoria. Este metodo
     * e a unica ponte entre as duas, para nao existir um if espalhado por ai.
     *
     * @param string $memberrole member::ROLE_OWNER ou member::ROLE_SELLER.
     * @return string
     */
    public static function shortname_for(string $memberrole): string {
        return $memberrole === member::ROLE_OWNER ? self::MANAGER : self::SELLER;
    }

    /**
     * As capabilities permitidas de um papel.
     *
     * @param string $shortname
     * @return string[]
     */
    public static function allow_for(string $shortname): array {
        if ($shortname === self::MANAGER) {
            return array_merge(self::ALLOW_EDITOR, self::ALLOW_MANAGER);
        }

        return self::ALLOW_EDITOR;
    }

    /**
     * O id de um papel, ou explode.
     *
     * Explodir e o certo: um papel que sumiu significa instalacao quebrada, e
     * seguir em frente produziria um membro sem acesso nenhum, sem erro
     * visivel.
     *
     * @param string $shortname
     * @return int
     */
    public static function get_id(string $shortname): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if (!$roleid) {
            throw new \moodle_exception('errorsellerrolemissing', 'local_marketplace');
        }

        return (int) $roleid;
    }

    /**
     * Cria ou atualiza os dois papeis. Idempotente.
     *
     * Chamado pelo db/install.php e pelo db/upgrade.php, que e a razao de a
     * idempotencia nao ser opcional: um upgrade que morre no meio e rodado de
     * novo, e a segunda passada nao pode duplicar papel nem apagar capability.
     *
     * NUNCA sai cedo quando o papel ja existe. Uma instalacao que falhou no
     * meio deixa o papel criado e sem capability nenhuma, e um "return" nesse
     * ponto produziria um vendedor que nao pode fazer nada - sem erro visivel.
     *
     * @return array<string, int> shortname => roleid, na ordem gerente, editor.
     */
    public static function ensure(): array {
        global $DB;

        // As capabilities do db/access.php podem ainda NAO estar registradas:
        // o Moodle roda o install.php antes de processar o access.php. Sem
        // isto, assign_capability() aborta com "Capability ... was not found".
        update_capabilities('local_marketplace');

        $names = [
            self::MANAGER => ['managerrole', 'managerroledesc'],
            self::SELLER => ['sellerrole', 'sellerroledesc'],
        ];

        $ids = [];

        foreach ($names as $shortname => [$name, $description]) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);

            if (!$roleid) {
                $roleid = create_role(
                    get_string($name, 'local_marketplace'),
                    $shortname,
                    get_string($description, 'local_marketplace')
                );
            }

            $roleid = (int) $roleid;

            // O papel so faz sentido numa categoria: e la que vive a empresa.
            // No sistema, ele daria a plataforma inteira.
            set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);

            self::apply_capabilities($roleid, $shortname);

            $ids[$shortname] = $roleid;
        }

        return $ids;
    }

    /**
     * Escreve as capabilities de um papel - e APAGA o que nao esta na lista.
     *
     * RECONCILIA, e nao so acrescenta. A primeira versao deste metodo so
     * chamava assign_capability, e isso passou no banco de teste e nao fez nada
     * em producao: assign_capability nunca remove, entao numa base que JA TINHA
     * o papel de vendedor ele seguia com managepayment, managecompany,
     * viewreport e role:assign do desenho de um papel so. A separacao existia
     * no codigo e nao existia no site. Encontrado em 04/09/2026, rodando o
     * upgrade contra um banco com dado - e nao ha teste de banco limpo que
     * pegue isso.
     *
     * O efeito colateral e desejado: o conjunto de capabilities destes papeis e
     * REGRA DE NEGOCIO ESCRITA EM CODIGO, e nao preferencia de administrador.
     * Uma capability acrescentada a mao - um repository/dropbox:view, digamos -
     * some no upgrade seguinte, que e exatamente o que se quer de um papel cuja
     * razao de existir e fechar caminhos para o moodledata.
     *
     * @param int $roleid
     * @param string $shortname
     * @return void
     */
    protected static function apply_capabilities(int $roleid, string $shortname): void {
        global $DB;

        $syscontext = \context_system::instance();

        $expected = [];

        foreach (self::allow_for($shortname) as $capability) {
            $expected[$capability] = CAP_ALLOW;
        }

        foreach (self::PROHIBIT as $capability) {
            // Uma capability que o site nao tem - repositorio desinstalado, ou
            // removido pelo upstream - faria o assign_capability abortar, e o
            // upgrade inteiro morreria por causa de um plugin que nem esta la.
            if (!get_capability_info($capability)) {
                continue;
            }

            // Depois do ALLOW de proposito: se uma capability caisse nas duas
            // listas, a proibicao tem que vencer.
            $expected[$capability] = CAP_PROHIBIT;
        }

        $current = $DB->get_records('role_capabilities', [
            'roleid' => $roleid,
            'contextid' => $syscontext->id,
        ]);

        foreach ($current as $existing) {
            if (!isset($expected[$existing->capability])) {
                unassign_capability($existing->capability, $roleid, $syscontext->id);
            }
        }

        foreach ($expected as $capability => $permission) {
            assign_capability($capability, $permission, $roleid, $syscontext->id, true);
        }
    }

    /**
     * Passa quem e dono para o papel de gerente.
     *
     * Existe porque o db/install.php NAO roda em base que ja existe. Sem este
     * passo, a producao fica com todo mundo no papel de editor - dono
     * inclusive -, e o dono perde o acesso a conta de pagamento da propria
     * empresa sem que nada avise.
     *
     * IDEMPOTENTE: so mexe em quem ainda nao esta no papel certo, entao rodar
     * de novo depois de um upgrade interrompido nao duplica nem derruba nada.
     *
     * @return int Quantos vinculos foram migrados.
     */
    public static function migrate_owners(): int {
        global $DB;

        $managerid = self::get_id(self::MANAGER);
        $sellerid = self::get_id(self::SELLER);

        $migrated = 0;

        foreach (company::get_records() as $company) {
            if (!$company->get('categoryid')) {
                continue;
            }

            $context = $company->get_context();

            foreach (member::get_records(['companyid' => $company->get('id')]) as $member) {
                if (!$member->is_owner()) {
                    continue;
                }

                $userid = (int) $member->get('userid');

                $already = $DB->record_exists('role_assignments', [
                    'roleid' => $managerid,
                    'userid' => $userid,
                    'contextid' => $context->id,
                ]);

                if (!$already) {
                    role_assign($managerid, $userid, $context->id);
                    $migrated++;
                }

                // O papel antigo sai depois de o novo entrar. Na ordem
                // inversa, um erro no meio deixaria o dono sem papel nenhum.
                role_unassign($sellerid, $userid, $context->id);
            }
        }

        return $migrated;
    }

    /**
     * Os repositorios habilitados que a lista de proibicao nao cobre.
     *
     * NAO conserta nada, e e assim de proposito: quem tranca e a constante
     * PROHIBIT. Este metodo so relata, para o cli/status.php, que alguem
     * habilitou um caminho novo para o moodledata.
     *
     * @return string[] Nomes dos repositorios em aberto, em ordem.
     */
    public static function open_repositories(): array {
        global $DB;

        $prohibited = array_flip(self::PROHIBIT);
        $open = [];

        $enabled = $DB->get_fieldset_select('repository', 'type', 'visible = 1');

        foreach ($enabled as $type) {
            if (in_array($type, self::REPOSITORIES_ALLOWED, true)) {
                continue;
            }

            if (!isset($prohibited["repository/{$type}:view"])) {
                $open[] = $type;
            }
        }

        sort($open);

        return $open;
    }
}
