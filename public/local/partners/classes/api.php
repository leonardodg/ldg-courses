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

namespace local_partners;

use core\context\system;
use core_user;
use local_marketplace\api as marketplace;
use local_marketplace\cnpj;
use local_marketplace\company;
use moodle_url;

/**
 * Operacoes de captacao de parceiros.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var int Quantas candidaturas o mesmo IP pode enviar por hora, por padrao. */
    const DEFAULT_MAX_PER_HOUR = 3;

    /**
     * Registra uma candidatura vinda do formulario publico.
     *
     * @param \stdClass $data Dados ja validados pelo formulario.
     * @return application
     */
    public static function submit(\stdClass $data): application {
        global $USER;

        $email = $data->contactemail;

        // Quem esta autenticado NAO confirma e-mail: o site ja confirmou o dele
        // quando a conta foi criada, e pedir de novo seria atrito sem ganho. A
        // candidatura fica ligada ao perfil, e esse usuario e o dono natural da
        // empresa na hora da aprovacao.
        $authenticated = isloggedin() && !isguestuser();

        if ($authenticated) {
            $email = $USER->email;
        }

        // Reenvio substitui a nao confirmada anterior, em vez de acumular linha
        // morta ou levar erro de duplicidade. Quem nao recebeu o link precisa
        // poder tentar de novo.
        application::purge_unconfirmed_for($email);

        $needsconfirmation = !$authenticated && self::email_confirmation_required();

        $record = (object) [
            'companyname' => $data->companyname,
            'cnpj' => !empty($data->cnpj) ? cnpj::normalise($data->cnpj) : null,
            'contactname' => $data->contactname,
            'contactemail' => $email,
            'contactphone' => !empty($data->contactphone) ? $data->contactphone : null,
            'website' => !empty($data->website) ? $data->website : null,
            'planid' => !empty($data->planid) ? (int) $data->planid : null,
            'country' => !empty($data->country) ? $data->country : null,
            'learnersband' => !empty($data->learnersband) ? $data->learnersband : null,
            // O aceite guarda QUANDO, e nao "sim": um booleano valendo 1 em toda
            // linha e redundante com a existencia da linha. Quem nao aceitou
            // fica nulo - zero diria "recusou", que e outra afirmacao.
            'termsaccepted' => !empty($data->termsaccepted) ? time() : null,
            'message' => !empty($data->message) ? $data->message : null,
            'status' => $needsconfirmation ? application::STATUS_UNCONFIRMED : application::STATUS_PENDING,
            'userid' => $authenticated ? (int) $USER->id : null,
            'confirmtoken' => $needsconfirmation ? random_string(32) : null,
            'timeconfirmed' => $authenticated ? time() : null,
            'submitterip' => getremoteaddr('', 45),
        ];

        $application = new application(0, $record);
        $application->create();

        if ($needsconfirmation) {
            // A fila NAO e avisada ainda: um e-mail nao confirmado nao e uma
            // candidatura, e avisar aqui encheria a caixa do administrador com
            // o que um robo digitou.
            self::send_confirmation($application);

            return $application;
        }

        self::notify_reviewers($application);

        return $application;
    }

    /**
     * Confirma o e-mail de uma candidatura e a coloca na fila.
     *
     * @param application $application A candidatura nao confirmada.
     * @return void
     */
    public static function confirm(application $application): void {
        global $DB;

        // Trava a linha (FOR UPDATE) antes de checar o status: sem isto, um
        // prefetcher de seguranca de email (Outlook Safe Links) acessando o
        // link antes do clique real do destinatario passava pela mesma
        // checagem em memoria que o clique real, e notify_reviewers() saia
        // duas vezes para uma unica candidatura.
        $transaction = $DB->start_delegated_transaction();
        $locked = $DB->get_record_sql(
            'SELECT status FROM {' . application::TABLE . '} WHERE id = ? FOR UPDATE',
            [$application->get('id')],
            MUST_EXIST
        );
        if ($locked->status !== application::STATUS_UNCONFIRMED) {
            $transaction->allow_commit();
            throw new \moodle_exception('erroralreadyconfirmed', 'local_partners');
        }

        $application->set('status', application::STATUS_PENDING);
        $application->set('timeconfirmed', time());
        // O token e de uso unico: some assim que cumpre a funcao.
        $application->set('confirmtoken', null);
        $application->update();
        $transaction->allow_commit();

        self::notify_reviewers($application);
    }

    /**
     * A confirmacao de e-mail e exigida de visitante anonimo?
     *
     * @return bool
     */
    public static function email_confirmation_required(): bool {
        return (bool) get_config('local_partners', 'requireemailconfirmation');
    }

    /**
     * Manda o link de confirmacao para o candidato.
     *
     * Diferente dos outros e-mails deste plugin, este NAO pode falhar em
     * silencio sem consequencia: sem ele a candidatura fica presa em
     * 'unconfirmed' e ninguem a ve. O erro vai para o log de propósito, para
     * aparecer quando o SMTP estiver mal configurado.
     *
     * @param application $application
     * @return void
     */
    protected static function send_confirmation(application $application): void {
        $to = self::synthetic_recipient($application);

        $context = (object) [
            // Escape=>false: o corpo do e-mail e FORMAT_PLAIN, e o escape
            // padrao do format_string() (pensado para HTML) transformava "&"
            // em "&amp;amp;" literal no texto puro.
            'company' => format_string($application->get('companyname'), true, ['escape' => false]),
            'url' => (new moodle_url('/local/partners/confirm.php', [
                'token' => $application->get('confirmtoken'),
            ]))->out(false),
        ];

        try {
            email_to_user(
                $to,
                \core_user::get_noreply_user(),
                get_string('confirmsubject', 'local_partners', $context->company),
                get_string('confirmbody', 'local_partners', $context)
            );
        } catch (\Throwable $e) {
            debugging(
                'local_partners: falha ao enviar a confirmacao para ' . $application->get('contactemail')
                    . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Um destinatario para quem ainda nao e usuario do site.
     *
     * O email_to_user precisa de um objeto de usuario. Quem se candidata
     * normalmente nao tem conta, e criar uma so para enviar e-mail seria criar
     * usuario a partir de formulario publico - exatamente o que se evita.
     *
     * @param application $application
     * @return \stdClass
     */
    protected static function synthetic_recipient(application $application): \stdClass {
        $to = clone \core_user::get_noreply_user();
        $to->email = $application->get('contactemail');
        $to->firstname = $application->get('contactname');
        $to->lastname = '';
        $to->maildisplay = 1;
        $to->mailformat = 0;
        // O -99 e o marcador que o proprio core usa para destinatario que nao e
        // usuario do site; sem um id o email_to_user recusa o envio.
        $to->id = -99;

        return $to;
    }

    /**
     * Avisa quem pode decidir que ha candidatura nova.
     *
     * Falha em silencio de proposito. O envio de mensagem depende de
     * configuracao de e-mail, e derrubar a candidatura de um parceiro porque o
     * SMTP esta fora seria perder o lead para nao perder o aviso.
     *
     * @param application $application
     * @return void
     */
    protected static function notify_reviewers(application $application): void {
        $reviewers = get_users_by_capability(system::instance(), 'local/partners:review');

        if (!$reviewers) {
            return;
        }

        $context = (object) [
            // Escape=>false: o corpo do e-mail e FORMAT_PLAIN, e o escape
            // padrao do format_string() (pensado para HTML) transformava "&"
            // em "&amp;amp;" literal no texto puro.
            'company' => format_string($application->get('companyname'), true, ['escape' => false]),
            'contact' => format_string($application->get('contactname'), true, ['escape' => false]),
            'url' => (new moodle_url('/local/partners/admin/applications.php'))->out(false),
        ];

        foreach ($reviewers as $reviewer) {
            $message = new \core\message\message();
            $message->component = 'local_partners';
            $message->name = 'newapplication';
            $message->userfrom = core_user::get_noreply_user();
            $message->userto = $reviewer;
            $message->subject = get_string('newapplicationsubject', 'local_partners', $context->company);
            $message->fullmessage = get_string('newapplicationbody', 'local_partners', $context);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->courseid = SITEID;
            $message->contexturl = $context->url;
            $message->contexturlname = get_string('applications', 'local_partners');

            try {
                message_send($message);
            } catch (\Throwable $e) {
                debugging(
                    'local_partners: falha ao notificar candidatura ' . $application->get('id') . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }

    /**
     * Quantas candidaturas o mesmo IP pode enviar por hora.
     *
     * @return int
     */
    public static function max_per_hour(): int {
        $configured = (int) get_config('local_partners', 'maxperhour');

        return $configured > 0 ? $configured : self::DEFAULT_MAX_PER_HOUR;
    }

    /**
     * O reCAPTCHA esta configurado no site?
     *
     * A camada mais forte do anti-spam nao pode ser esta: ela depende de duas
     * chaves que alguem precisa lembrar de cadastrar. O limite de taxa e o
     * honeypot valem sempre; o reCAPTCHA e o reforco de quem configurou.
     *
     * @return bool
     */
    public static function recaptcha_available(): bool {
        global $CFG;

        // Duas condicoes, e as duas precisam valer.
        //
        // O interruptor existe para ligar e desligar sem mexer nas chaves do
        // site, que sao globais: desabilitar o captcha aqui nao pode obrigar a
        // apagar chave que outros formularios do Moodle usam.
        if (!get_config('local_partners', 'enablerecaptcha')) {
            return false;
        }

        return !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey);
    }

    /**
     * O site tem as chaves do reCAPTCHA cadastradas?
     *
     * Separado do recaptcha_available() para a tela de configuracao poder
     * dizer POR QUE o captcha nao esta ativo: ligado sem chave e o caso em que
     * o administrador acha que esta protegido e nao esta.
     *
     * @return bool
     */
    public static function recaptcha_keys_present(): bool {
        global $CFG;

        return !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey);
    }

    /**
     * Aprova uma candidatura e provisiona a empresa.
     *
     * NAO cria categoria, papel nem membro por conta propria: chama o
     * local_marketplace\api::create_company, que ja faz tudo isso e e o unico
     * lugar do sistema que sabe como uma empresa nasce. Duplicar aquele fluxo
     * aqui criaria duas verdades sobre o que e uma empresa provisionada.
     *
     * A empresa nasce com commissionpct NULO de proposito, para que o PLANO
     * governe a comissao. Copiar o percentual do plano congelaria o numero: a
     * empresa pararia de acompanhar uma mudanca de plano e ninguem entenderia
     * por que.
     *
     * @param application $application A candidatura.
     * @param \stdClass $decision Dados do formulario de decisao.
     * @return company A empresa criada.
     */
    public static function approve(application $application, \stdClass $decision): company {
        global $DB;

        // A guarda vem ANTES de qualquer escrita, e trava a linha (FOR UPDATE)
        // ate o commit. Duas submissoes do formulario - um duplo clique, um F5
        // na tela de confirmacao - chegavam aqui com a mesma leitura de
        // status=pending antes de qualquer escrita, e produziam duas
        // categorias de curso para uma unica candidatura. Sem a trava, o
        // guard_pending() so lia um snapshot em memoria que a segunda
        // submissao tambem enxergava como pendente.
        $transaction = $DB->start_delegated_transaction();
        self::guard_pending($application);

        $company = marketplace::create_company((object) [
            'name' => $application->get('companyname'),
            'shortname' => $decision->shortname,
            'cnpj' => $application->get('cnpj'),
            'planid' => !empty($decision->planid) ? (int) $decision->planid : null,
            'commissionpct' => null,
        ], self::resolve_owner($application, self::owner_input($decision->ownerid ?? null)));

        $application->set('companyid', (int) $company->get('id'));
        $application->set('status', application::STATUS_APPROVED);
        self::stamp_review($application, $decision);
        $application->update();
        $transaction->allow_commit();

        self::notify_applicant($application, true);

        return $company;
    }

    /**
     * Recusa uma candidatura.
     *
     * @param application $application A candidatura.
     * @param \stdClass $decision Dados do formulario de decisao.
     * @return void
     */
    public static function reject(application $application, \stdClass $decision): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        self::guard_pending($application);

        $application->set('status', application::STATUS_REJECTED);
        self::stamp_review($application, $decision);
        $application->update();
        $transaction->allow_commit();

        self::notify_applicant($application, false);
    }

    /**
     * Recusa decidir de novo o que ja foi decidido.
     *
     * Trava a linha com FOR UPDATE ate o chamador commitar: precisa rodar
     * DENTRO de uma transacao aberta pelo chamador (approve()/reject()), senao
     * a trava e liberada antes da escrita seguinte e nao protege nada.
     *
     * @param application $application
     * @return void
     */
    protected static function guard_pending(application $application): void {
        global $DB;

        $locked = $DB->get_record_sql(
            'SELECT status FROM {' . application::TABLE . '} WHERE id = ? FOR UPDATE',
            [$application->get('id')],
            MUST_EXIST
        );
        if ($locked->status !== application::STATUS_PENDING) {
            throw new \moodle_exception('erroralreadydecided', 'local_partners');
        }
    }

    /**
     * Registra quem decidiu e quando.
     *
     * @param application $application
     * @param \stdClass $decision
     * @return void
     */
    protected static function stamp_review(application $application, \stdClass $decision): void {
        global $USER;

        $application->set('reviewnote', !empty($decision->reviewnote) ? $decision->reviewnote : null);
        $application->set('reviewerid', (int) $USER->id);
        $application->set('timereviewed', time());
    }

    /**
     * Avisa o candidato do resultado.
     *
     * Usa email_to_user com um usuario SINTETICO: quem se candidatou
     * normalmente nao tem conta no site, e criar uma so para mandar o aviso
     * seria criar usuario a partir de formulario publico - RISK_SPAM, e nao e
     * isso que este passo faz.
     *
     * Falha em silencio pelo mesmo motivo do aviso de candidatura nova: o SMTP
     * fora do ar nao pode desfazer uma empresa que ja foi provisionada.
     *
     * @param application $application
     * @param bool $approved
     * @return void
     */
    protected static function notify_applicant(application $application, bool $approved): void {
        global $CFG;

        $to = self::synthetic_recipient($application);

        $context = (object) [
            // Escape=>false: o corpo do e-mail e FORMAT_PLAIN, e o escape
            // padrao do format_string() (pensado para HTML) transformava "&"
            // em "&amp;amp;" literal no texto puro.
            'company' => format_string($application->get('companyname'), true, ['escape' => false]),
            'note' => (string) $application->get('reviewnote'),
            'url' => $CFG->wwwroot,
        ];

        $subject = get_string($approved ? 'approvedsubject' : 'rejectedsubject', 'local_partners', $context->company);
        $body = get_string($approved ? 'approvedbody' : 'rejectedbody', 'local_partners', $context);

        try {
            email_to_user($to, \core_user::get_noreply_user(), $subject, $body);
        } catch (\Throwable $e) {
            debugging(
                'local_partners: falha ao avisar o candidato ' . $application->get('id') . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Sugere um atalho a partir do nome da empresa.
     *
     * E so uma SUGESTAO: o atalho vai para a URL e para a categoria, e quem
     * confirma e o administrador. Se ja existir empresa com o mesmo atalho, o
     * sufixo numerico evita que ele precise inventar um do zero.
     *
     * @param string $name Nome da empresa.
     * @return string
     */
    public static function suggest_shortname(string $name): string {
        $slug = \core_text::specialtoascii($name);
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $slug) ?? '');
        $slug = substr($slug, 0, 40);

        if ($slug === '') {
            $slug = 'empresa';
        }

        $candidate = $slug;
        $suffix = 2;

        while (company::get_record(['shortname' => $candidate])) {
            $candidate = $slug . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Traduz o dono vindo do formulario.
     *
     * O autocomplete do moodleform manda STRING VAZIA quando ninguem foi
     * escolhido, e nao null - entao o `?? null` do chamador nao pegava, e ''
     * chegava num parametro ?int e estourava TypeError.
     *
     * Quebrava justamente o caminho mais comum: aprovar deixando o dono em
     * branco, que e o que a propria tela instrui a fazer quando o candidato
     * ainda nao tem conta.
     *
     * @param mixed $value
     * @return int|null Nulo quando ninguem foi escolhido.
     */
    protected static function owner_input($value): ?int {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return ((int) $value) ?: null;
    }

    /**
     * Descobre - ou cria - o usuario que sera dono da empresa.
     *
     * Uma empresa precisa de dono: o create_company exige um usuario, e e ele
     * que recebe o papel de vendedor na categoria. Sem isso a empresa nasce sem
     * ninguem que possa administra-la.
     *
     * Sao tres caminhos, nesta ordem:
     *
     *   1. o administrador escolheu alguem na tela   usa esse
     *   2. ja existe conta com o e-mail do contato   usa essa, sem perguntar
     *   3. nao existe conta                          cria e convida
     *
     * O caminho 3 CRIA USUARIO, e isso merece explicacao: a regra e que
     * formulario publico nao cria conta - seria RISK_SPAM, um formulario aberto
     * que fabrica milhares de contas. Aqui quem cria e o ADMINISTRADOR, no ato
     * de aprovar, sobre um e-mail que ele acabou de ler e decidir aceitar. E o
     * mesmo ato de /admin/user.php, so que sem obrigar a sair da tela, copiar o
     * e-mail e voltar.
     *
     * @param application $application A candidatura.
     * @param int|null $ownerid Usuario escolhido na tela, se houve.
     * @return int O id do dono.
     */
    protected static function resolve_owner(application $application, ?int $ownerid): int {
        global $CFG, $DB;

        if (!empty($ownerid)) {
            return (int) $ownerid;
        }

        // Candidatura enviada por alguem autenticado ja tem dono: e quem a
        // enviou. Procurar de novo pelo e-mail daria no mesmo, mas so por
        // coincidencia - o usuario pode ter trocado o e-mail depois.
        if (!empty($application->get('userid'))) {
            return (int) $application->get('userid');
        }

        // So confia no e-mail digitado quando ha prova de controle da caixa
        // postal: candidatura enviada por usuario autenticado ja voltou acima,
        // e confirm() so grava timeconfirmed quando o candidato clicou no
        // link mandado para o proprio e-mail. Sem uma das duas provas,
        // procurar conta existente por esse e-mail e dar ownership da empresa
        // a quem quer que seja dono dele hoje - um estranho que nunca
        // participou da candidatura, se o e-mail foi so digitado.
        if (!empty($application->get('timeconfirmed'))) {
            $email = $application->get('contactemail');

            $existing = $DB->get_record('user', [
                'email' => $email,
                'deleted' => 0,
                'mnethostid' => $CFG->mnet_localhost_id,
            ], 'id', IGNORE_MULTIPLE);

            if ($existing) {
                return (int) $existing->id;
            }
        }

        return self::create_owner($application);
    }

    /**
     * Cria a conta do candidato e manda o convite para definir a senha.
     *
     * A senha nao e gerada nem enviada por e-mail: o usuario nasce SEM senha
     * utilizavel e recebe o link de definicao. Senha em e-mail e senha que fica
     * na caixa de entrada para sempre.
     *
     * @param application $application A candidatura.
     * @return int O id do usuario criado.
     */
    protected static function create_owner(application $application): int {
        global $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        $email = $application->get('contactemail');
        [$firstname, $lastname] = self::split_name($application->get('contactname'));

        $user = new \stdClass();
        $user->auth = 'manual';
        $user->username = self::available_username($email);
        $user->email = $email;
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->lang = $CFG->lang ?? 'en';

        // Senha aleatoria que NINGUEM conhece, nem quem cria. O acesso acontece
        // pelo link de definicao que vai no convite.
        //
        // De proposito NAO se usa o setnew_password_and_mail(): aquela funcao
        // gera a senha e manda em TEXTO PURO no e-mail, onde ela fica na caixa
        // de entrada para sempre. O fluxo de redefinicao usa um token com prazo.
        $user->password = hash_internal_user_password(random_string(24));

        $userid = user_create_user($user, false, false);

        $created = \core_user::get_user($userid);

        // O convite pode falhar - SMTP fora do ar - e isso NAO pode desfazer a
        // empresa que ja foi provisionada. A conta fica com senha desconhecida,
        // e o proprio "esqueci minha senha" resolve; o administrador tambem
        // pode reenviar pela tela de usuarios.
        try {
            require_once($CFG->dirroot . '/login/lib.php');

            $resetrecord = core_login_generate_password_reset($created);
            send_password_change_confirmation_email($created, $resetrecord);
        } catch (\Throwable $e) {
            debugging(
                'local_partners: falha ao enviar o convite para ' . $email . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        return (int) $userid;
    }

    /**
     * Um nome de usuario livre, derivado do e-mail.
     *
     * @param string $email
     * @return string
     */
    protected static function available_username(string $email): string {
        global $DB;

        $base = \core_text::strtolower(explode('@', $email)[0]);
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';
        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'parceiro';
        }

        $base = substr($base, 0, 90);
        $candidate = $base;
        $suffix = 2;

        while ($DB->record_exists('user', ['username' => $candidate])) {
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Separa o nome do contato em nome e sobrenome.
     *
     * O formulario publico pede UM campo de nome de proposito - pedir dois a
     * quem so quer se candidatar e atrito. A separacao ingenua aqui e boa o
     * bastante, e o proprio usuario corrige no perfil dele depois.
     *
     * @param string $fullname
     * @return array [nome, sobrenome]
     */
    protected static function split_name(string $fullname): array {
        $parts = preg_split('/\s+/', trim($fullname), 2);

        return [$parts[0] ?? $fullname, $parts[1] ?? ''];
    }
}
