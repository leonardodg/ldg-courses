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
 * Strings do local_partners.
 *
 * ORDEM ALFABETICA OBRIGATORIA, e paridade exata de chaves entre os idiomas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alreadyapproved'] = 'Esta candidatura já foi aprovada e a empresa {$a} existe.';
$string['alreadydecided'] = 'Esta candidatura já foi decidida.';
$string['analyticsid'] = 'ID de medição do Google Analytics';
$string['analyticsid_desc'] = 'Carregado somente nas páginas públicas de captação, e nunca dentro do LMS: mandar a atividade de um aluno para um terceiro é outra decisão, com outro peso. Este interruptor é técnico, e não jurídico. A tag grava cookie e envia dados para fora, o que sob a LGPD e o GDPR pede base legal e, na prática, aviso de consentimento.';
$string['applicationmessage'] = 'Algo mais que devemos saber';
$string['applications'] = 'Candidaturas de parceiros';
$string['applicationstatus'] = 'Situação';
$string['applybadge'] = 'Uma pessoa lê cada candidatura';
$string['applyformlead'] = 'Conte sobre a sua operação. Nada aqui é publicado, e respondemos por e-mail.';
$string['applylead'] = 'Conte sobre a sua operação. Respondemos todas as candidaturas.';
$string['applymetadescription'] = 'Candidate-se para publicar e vender seus cursos na plataforma. Sem mensalidade para começar, e uma pessoa lê cada candidatura.';
$string['applyreviewed'] = 'Revisado por uma pessoa';
$string['applysecure'] = 'Conexão cifrada';
$string['applytitle'] = 'Candidate-se para vender na plataforma';
$string['approvalpending'] = 'Aprovar uma candidatura provisiona uma empresa e uma categoria de curso. Esse passo ainda não foi construído: por enquanto, entre em contato com o candidato diretamente.';
$string['approvedbody'] = 'Sua candidatura para {$a->company} foi aprovada.

Voce ja pode entrar e preparar seus cursos:
{$a->url}

{$a->note}';
$string['approvedmessage'] = 'Candidatura aprovada. A empresa {$a} foi criada.';
$string['approvedsubject'] = 'Sua candidatura para {$a} foi aprovada';
$string['backtohome'] = 'Voltar para a página inicial';
$string['backtolanding'] = 'Voltar para a página de parceria';
$string['brandname'] = 'LDG Tecnologia';
$string['catalogmetadescription'] = 'Veja os cursos publicados na plataforma. Cada curso é vendido por quem o criou, pessoa ou empresa.';
$string['checklandingreachable'] = 'Página de parceria alcançável pelo visitante';
$string['checklandingreachableaction'] = 'Configurações de navegação';
$string['checklandingreachablefail'] = 'A página de parceria é a home do site, mas quem não entrou é mandado para a tela de login';
$string['checklandingreachablefaildetail'] = 'O Painel está desligado, e com ele desligado o Moodle redireciona todo visitante anônimo para fora da home antes de qualquer código de tema rodar. Não é a configuração "forçar usuários a se autenticarem". Além de a página nunca ser mostrada, a URL canônica, todos os hreflang e a entrada principal do sitemap apontam para a home, então o buscador segue cada um deles até um redirecionamento para uma página marcada como noindex. Ligue o Painel nas configurações de navegação.';
$string['checklandingreachablena'] = 'A página de parceria não é a home do site, então a home é a do próprio Moodle';
$string['checklandingreachableok'] = 'Quem não entrou alcança a página de parceria na home do site';
$string['cnpj'] = 'CNPJ';
$string['cnpj_help'] = 'Opcional. Pessoa física vende sem CNPJ. Se informar, precisa ser um número válido.';
$string['colormodetoggle'] = 'Alternar entre claro e escuro';
$string['companyname'] = 'Nome da empresa';
$string['companyowner'] = 'Dono da empresa';
$string['companyowner_help'] = 'O usuário do site que vai administrar a empresa e vincular a conta de recebimento. Se quem se candidatou ainda não tem conta, crie uma antes: criar usuário a partir de formulário público é risco de spam, e esta tela não faz isso.';
$string['companyshortname'] = 'Nome curto';
$string['companyshortname_help'] = 'Vai para a URL da empresa e para o nome da categoria de curso. É sugerido a partir do nome da empresa, e você confirma: a categoria é objeto global do site.';
$string['confirmbody'] = 'Confirme seu e-mail para enviar a candidatura de {$a->company}.

Abra este link:
{$a->url}

Se nao foi voce, ignore esta mensagem e nada acontece.';
$string['confirmdone'] = 'E-mail confirmado. Sua candidatura está na fila e respondemos por e-mail.';
$string['confirminvalid'] = 'Este link não é válido, ou já foi usado.';
$string['confirmsubject'] = 'Confirme seu e-mail para se candidatar a parceiro';
$string['confirmtitle'] = 'Confirmação de e-mail';
$string['contactemail'] = 'E-mail';
$string['contactname'] = 'Nome do contato';
$string['contactphone'] = 'Telefone';
$string['cookiesurl'] = 'URL da política de cookies';
$string['cookiesurl_desc'] = 'Para onde aponta o link "{$a}" no rodapé público. Vazio, o link não aparece. Se você ligar o Google Analytics, este documento deixa de ser opcional.';
$string['country'] = 'País ou região de operação';
$string['ctalead'] = 'Sem mensalidade para começar e sem fidelidade. Você só paga quando vende.';
$string['ctanote'] = 'Não é preciso cartão para começar no plano de entrada.';
$string['ctasecondary'] = 'Ler as perguntas antes';
$string['ctatitle'] = 'Pronto para publicar o seu primeiro curso?';
$string['decision'] = 'Decisão';
$string['decisionapprove'] = 'Aprovar e criar a empresa';
$string['decisionreject'] = 'Recusar';
$string['enablelanding'] = 'Exibir a página de captação de parceiros';
$string['enablelanding_desc'] = 'Ligado, a landing é servida e o tema pode usá-la como página inicial para o visitante anônimo. Desligado, a página devolve erro e o tema volta para a frontpage padrão.';
$string['enablerecaptcha'] = 'Usar reCAPTCHA no formulário de candidatura';
$string['enablerecaptcha_desc'] = 'Acrescenta ao formulário público o reCAPTCHA que o Moodle já traz. As chaves do site estão cadastradas, então ligar aqui vale de imediato. O campo escondido e o limite de taxa por IP continuam ativos nos dois casos: não custam nada e nunca atrapalham uma pessoa.';
$string['enablerecaptcha_nokeys'] = 'Acrescenta ao formulário público o reCAPTCHA que o Moodle já traz. O site não tem chaves de reCAPTCHA cadastradas, então isto ainda não tem efeito. O campo escondido e o limite de taxa por IP continuam ativos nos dois casos.';
$string['erroralreadyconfirmed'] = 'Esta candidatura já foi confirmada.';
$string['erroralreadydecided'] = 'Esta candidatura já foi decidida.';
$string['errorapplicationnotfound'] = 'Candidatura não encontrada.';
$string['errorcnpjinvalid'] = 'Este CNPJ não é válido.';
$string['errorcountryinvalid'] = 'Escolha um país da lista.';
$string['errorduplicatepending'] = 'Já existe uma candidatura em aberto para este e-mail ou CNPJ. Vamos entrar em contato.';
$string['erroremailinvalid'] = 'Informe um e-mail válido.';
$string['errorlearnersbandinvalid'] = 'Escolha uma das faixas de alunos listadas.';
$string['errorownerrequired'] = 'Escolha o usuário do site que será dono da empresa.';
$string['errorplannotfound'] = 'O plano selecionado não existe.';
$string['errorshortnamerequired'] = 'Informe um nome curto para a empresa.';
$string['errorshortnametaken'] = 'Outra empresa já usa este nome curto.';
$string['errortermsrequired'] = 'Aceite os termos de parceria e a política de privacidade para continuar.';
$string['errortoolong'] = 'Use no máximo {$a} caracteres.';
$string['errortoomany'] = 'Candidaturas demais desta conexão. Tente de novo daqui a uma hora.';
$string['faq1answer'] = 'A venda é sua. A plataforma leva apenas a comissão, e o dinheiro cai na sua própria conta de recebimento.';
$string['faq1question'] = 'Quem recebe o dinheiro da venda?';
$string['faq2answer'] = 'Você. A cobrança nasce na sua conta, então a nota é emitida por você. A plataforma nunca emite nota no seu lugar.';
$string['faq2question'] = 'Quem emite a nota fiscal?';
$string['faq3answer'] = 'Sim. Curso gratuito não custa nada e não precisa de plano. A comissão só existe em venda paga.';
$string['faq3question'] = 'Posso publicar cursos gratuitos?';
$string['faq4answer'] = 'No plano Starter a plataforma paga a banda do vídeo, então a resolução máxima acompanha o preço do curso. Nos planos em que você conecta o próprio armazenamento não há teto.';
$string['faq4question'] = 'Por que a qualidade do vídeo depende do preço do curso?';
$string['faqeyebrow'] = 'Respostas diretas';
$string['faqlead'] = 'O que perguntam antes de se candidatar, sobre dinheiro, nota fiscal e entrega.';
$string['faqtitle'] = 'Perguntas que sempre chegam';
$string['footerbuilton'] = 'Feito sobre o Moodle 5.2';
$string['footercookies'] = 'Política de cookies';
$string['footercredit'] = 'Feito por {$a}';
$string['footerlegal'] = 'Jurídico';
$string['footernavlabel'] = 'Links do rodapé';
$string['footerplatform'] = 'Plataforma';
$string['footerprivacy'] = 'Política de privacidade';
$string['footertagline'] = 'Publique e venda seus próprios cursos online em Moodle 5.2 padrão, cobrando na sua própria conta.';
$string['footerterms'] = 'Termos de uso';
$string['frontpagemode'] = 'Página inicial para quem não está autenticado';
$string['frontpagemode_desc'] = 'Escolha o que o visitante anônimo vê no endereço do site. A landing de captação substitui a página inicial inteira para ele; quem está autenticado continua vendo a home normal nos dois casos. Ela nunca substitui a home de um domínio de vendedor.';
$string['frontpagemodedefault'] = 'Página inicial do Moodle';
$string['frontpagemodelanding'] = 'Landing de captação de parceiros';
$string['heroctasecondary'] = 'Ver os planos';
$string['heroctatext'] = 'Quero ser parceiro';
$string['herolead'] = 'Publique seus cursos, cobre em seu próprio nome e pague só quando vender.';
$string['heropill'] = 'Moodle 5.2, várias empresas';
$string['herostat1'] = 'Sem mensalidade no plano de entrada';
$string['herostat2'] = 'A cobrança nasce na sua conta';
$string['herostat3'] = 'Moodle 5.2 padrão, sem fork';
$string['herotitle'] = 'Venda seus cursos';
$string['herotitleaccent'] = 'sem construir uma plataforma';
$string['honeypotlabel'] = 'Deixe este campo vazio';
$string['howeyebrow'] = 'Da candidatura à primeira venda';
$string['howlead'] = 'São quatro passos, e uma pessoa lê cada candidatura.';
$string['howtitle'] = 'Como funciona';
$string['landingdisabled'] = 'A página de captação de parceiros está desligada.';
$string['landingtitle'] = 'Seja um parceiro';
$string['languageselector'] = 'Idioma';
$string['learnersband'] = 'Previsão de alunos ativos por mês';
$string['learnersbandlarge'] = 'De 1.000 a 5.000 alunos';
$string['learnersbandmedium'] = 'De 100 a 1.000 alunos';
$string['learnersbandsmall'] = 'Até 100 alunos';
$string['learnersbandundecided'] = 'Ainda não sei';
$string['learnersbandxlarge'] = 'Mais de 5.000 alunos';
$string['maxperhour'] = 'Candidaturas por hora, por conexão';
$string['maxperhour_desc'] = 'Limite de taxa do formulário público. É a camada de anti-spam que vale sempre: ao contrário do captcha, não depende de nenhuma chave estar cadastrada.';
$string['metadescription'] = 'Publique e venda seus cursos online. Sem mensalidade para começar, recebimento na sua própria conta e comissão só sobre o que você vender.';
$string['newapplicationbody'] = 'Chegou uma candidatura de parceria da {$a->company}, enviada por {$a->contact}.

Analise aqui:
{$a->url}';
$string['newapplicationsubject'] = 'Nova candidatura de parceria: {$a}';
$string['noapplications'] = 'Ainda não há candidaturas.';
$string['opencompany'] = 'Abrir a empresa';
$string['ownermatched'] = 'Já existe uma conta para {$a}, e ela está selecionada acima.';
$string['ownerwillbecreated'] = 'Não existe conta para {$a}. Deixe o dono em branco e uma será criada na aprovação, com um e-mail convidando a pessoa a definir a senha. Escolha outro usuário apenas se a empresa deve pertencer a outra pessoa.';
$string['permonth'] = '/mês';
$string['plan'] = 'Plano';
$string['planbadgepopular'] = 'O mais escolhido';
$string['plancommission'] = '{$a}% de comissão por venda';
$string['planctatext'] = 'Começar com este plano';
$string['planfree'] = 'Sem mensalidade';
$string['planhostingbyos'] = 'Você conecta o seu armazenamento de vídeo';
$string['planhostingnative'] = 'Hospedagem de vídeo inclusa';
$string['planofinterest'] = 'Plano de interesse';
$string['planseyebrow'] = 'Preço transparente';
$string['planslead'] = 'Comece sem mensalidade e mude de plano quando compensar.';
$string['plansnote'] = 'A comissão incide sobre o valor bruto da venda. Curso gratuito não tem comissão e não precisa de plano.';
$string['planstitle'] = 'Planos';
$string['planundecided'] = 'Ainda não sei';
$string['pluginname'] = 'Captação de parceiros';
$string['privacy:metadata:application'] = 'Candidaturas de empresas que querem vender na plataforma. Quem se candidata normalmente não é usuário cadastrado.';
$string['privacy:metadata:application:cnpj'] = 'O CNPJ da empresa, quando informado.';
$string['privacy:metadata:application:companyname'] = 'O nome da empresa como foi digitado.';
$string['privacy:metadata:application:contactemail'] = 'O e-mail de contato.';
$string['privacy:metadata:application:contactname'] = 'O nome de quem se candidatou.';
$string['privacy:metadata:application:contactphone'] = 'O telefone de contato, quando informado.';
$string['privacy:metadata:application:country'] = 'O país ou região em que a empresa opera.';
$string['privacy:metadata:application:learnersband'] = 'A previsão de alunos ativos por mês, em faixa.';
$string['privacy:metadata:application:message'] = 'A mensagem em texto livre enviada com a candidatura.';
$string['privacy:metadata:application:reviewerid'] = 'O usuário do site que analisou a candidatura.';
$string['privacy:metadata:application:submitterip'] = 'O endereço IP de onde a candidatura veio, usado apenas para o limite de taxa.';
$string['privacy:metadata:application:termsaccepted'] = 'Quando os termos de parceria e a política de privacidade foram aceitos.';
$string['privacy:metadata:application:timecreated'] = 'Quando a candidatura foi enviada.';
$string['privacy:metadata:application:userid'] = 'O usuário do site que enviou a candidatura, quando ela veio de alguém autenticado.';
$string['privacy:path:applications'] = 'Candidaturas de parceria enviadas';
$string['privacy:path:reviews'] = 'Candidaturas de parceria analisadas';
$string['privacyurl'] = 'URL da política de privacidade';
$string['privacyurl_desc'] = 'Para onde aponta o link "{$a}" no rodapé público. Vazio, o link não aparece.';
$string['rejectedbody'] = 'Sua candidatura para {$a->company} nao foi aprovada desta vez.

{$a->note}';
$string['rejectedmessage'] = 'Candidatura de {$a} recusada.';
$string['rejectedsubject'] = 'Sobre a sua candidatura para {$a}';
$string['requiredfields'] = 'Campos obrigatórios';
$string['requireemailconfirmation'] = 'Exigir confirmação de e-mail de visitante anônimo';
$string['requireemailconfirmation_desc'] = 'A candidatura só chega à fila depois de a pessoa abrir um link enviado para o endereço que ela digitou. É a camada anti-robô que as outras não substituem: limite de taxa e captcha custam tempo ao robô, esta custa uma caixa de e-mail real e funcional por candidatura. Depende de o site conseguir enviar e-mail — com o SMTP quebrado, ligar isto trava a fila. Nunca vale para usuário autenticado: o site já confirmou o endereço dele.';
$string['reviewnote'] = 'Observação';
$string['reviewnote_help'] = 'Vai no e-mail para quem se candidatou. Numa recusa, é a única explicação que a pessoa recebe - escreva pensando nela.';
$string['savedecision'] = 'Salvar decisão';
$string['searchconsoletoken'] = 'Código de verificação do Google Search Console';
$string['searchconsoletoken_desc'] = 'Apenas o valor do content da meta tag que o Google fornece, sem a tag em volta. Vazio, nenhuma tag é emitida: meta de verificação com valor errado é pior que ausente, porque o Search Console passa a reportar "falhou" em vez de "faltando". Também é possível verificar por DNS, o que não exige nada aqui.';
$string['sectionapply'] = 'Candidatar-se';
$string['sectionfaq'] = 'Perguntas';
$string['sectionfeatures'] = 'Recursos';
$string['sectionhow'] = 'Como funciona';
$string['sectionnavlabel'] = 'Seções desta página';
$string['sectionpricing'] = 'Planos';
$string['seotitle'] = 'Venda seus cursos online';
$string['skiptomain'] = 'Ir para o conteúdo';
$string['statusapproved'] = 'Aprovada';
$string['statuspending'] = 'Na fila';
$string['statusrejected'] = 'Recusada';
$string['statusunconfirmed'] = 'Aguardando confirmação de e-mail';
$string['step1text'] = 'Envie o formulário. Leva um minuto e pergunta só o necessário para conversarmos.';
$string['step1title'] = 'Candidate-se';
$string['step2text'] = 'A gente responde, combina o plano e prepara o seu espaço na plataforma.';
$string['step2title'] = 'Conversamos';
$string['step3text'] = 'Você conecta a sua própria conta de recebimento. O dinheiro de cada venda cai lá.';
$string['step3title'] = 'Conecte sua conta';
$string['step4text'] = 'Suba seus cursos e comece a vender. A comissão é cobrada por venda, nunca antecipada.';
$string['step4title'] = 'Publique e venda';
$string['submitapplication'] = 'Enviar candidatura';
$string['submittedon'] = 'Enviada em';
$string['taskpurgeunconfirmed'] = 'Apagar candidaturas de parceria não confirmadas';
$string['termsaccept'] = 'Aceito os termos de parceria e a {$a}.';
$string['termsacceptedon'] = 'Termos aceitos em';
$string['termsacceptplain'] = 'Aceito os termos de parceria e a política de privacidade.';
$string['termslinktext'] = 'política do site';
$string['termsurl'] = 'URL dos termos de uso';
$string['termsurl_desc'] = 'Para onde aponta o link "{$a}" no rodapé público. Vazio, o link não aparece: link legal que não leva a lugar nenhum é pior que link ausente, porque promete um documento. O padrão é a política do site, quando houver uma.';
$string['thanksbody'] = 'Sua candidatura chegou. A gente lê todas e responde por e-mail.';
$string['thanksbodyunconfirmed'] = 'Confira sua caixa de entrada. Sua candidatura chega até nós assim que você abrir o link que acabamos de enviar.';
$string['thankstitle'] = 'Candidatura recebida';
$string['tierabove'] = 'Acima de {$a}';
$string['tierany'] = 'Qualquer preço';
$string['tierslabel'] = 'Qualidade do vídeo por preço do curso';
$string['tierupto'] = 'Até {$a}';
$string['unconfirmedretentiondays'] = 'Guardar candidaturas não confirmadas por (dias)';
$string['unconfirmedretentiondays_desc'] = 'A candidatura cujo e-mail nunca for confirmado é apagada depois deste prazo, junto com o nome, o telefone e o endereço IP que vieram nela. Use 0 para guardar para sempre.';
$string['value1note'] = 'Sem custo fixo para começar';
$string['value1text'] = 'Sem mensalidade no plano de entrada. A plataforma ganha quando você ganha, e não antes.';
$string['value1title'] = 'Você paga quando vende';
$string['value2note'] = 'A nota é emitida por você';
$string['value2text'] = 'A cobrança nasce na sua própria conta. O dinheiro é seu desde o início, e a nota é emitida por você.';
$string['value2title'] = 'O dinheiro cai na sua conta';
$string['value3note'] = 'Nada para manter';
$string['value3text'] = 'Matrícula, progresso, certificado e relatório vêm do Moodle, usado por universidades no mundo inteiro.';
$string['value3title'] = 'Uma plataforma que você não precisa construir';
$string['value4note'] = 'Separado por desenho';
$string['value4text'] = 'Sua empresa recebe categoria de cursos própria, papéis próprios e conta de pagamento própria. Seus alunos não se misturam com os de ninguém.';
$string['value4title'] = 'Um espaço só seu';
$string['valueeyebrow'] = 'Como a parceria funciona';
$string['valuelead'] = 'A plataforma ganha uma parte do que você vende, e nada além disso. Todo o resto abaixo decorre daí.';
$string['valuetitle'] = 'Por que vender aqui';
$string['website'] = 'Site';
