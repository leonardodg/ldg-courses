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
 * Strings for paygw_asaas.
 *
 * @package    paygw_asaas
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$string['apikey'] = 'Chave de API do Asaas';
$string['apikey_help'] = 'Gere uma chave na sua própria conta Asaas (Configurações > Integrações > Chave de API) e cole aqui. A chave é conferida no Asaas antes de ser gravada, e fica cifrada: ela não volta a aparecer na tela. É a sua conta que emite a cobrança, fica com o líquido e emite a nota — a plataforma só recebe a comissão, pelo split.';
$string['billingboleto'] = 'Somente boleto';
$string['billingcreditcard'] = 'Somente cartão de crédito';
$string['billingpix'] = 'Somente Pix';
$string['billingtype'] = 'Forma de pagamento';
$string['billingtype_desc'] = '"Deixar o aluno escolher" abre a fatura do Asaas com todas as formas habilitadas na conta do vendedor. Forçar uma forma é útil enquanto se testa um fluxo só.';
$string['billingundefined'] = 'Deixar o aluno escolher';
$string['chargeheading'] = 'Cobrança';
$string['defaultdescription'] = 'Compra de curso';
$string['documentfield'] = 'Campo de perfil com o documento do comprador';
$string['documentfield_desc'] = 'Nome curto de um campo de perfil personalizado com o CPF ou CNPJ do comprador. <strong>Obrigatório:</strong> o Asaas cria o cliente sem documento mas recusa emitir a cobrança, então deixar vazio faz toda compra falhar no último passo. Deixe o campo obrigatório no cadastro também.';
$string['duedays'] = 'Dias até o vencimento';
$string['duedays_desc'] = 'Por quanto tempo a cobrança continua pagável. Um Pix costuma ser pago em minutos; a janela existe por causa do boleto.';
$string['environment'] = 'Ambiente';
$string['environment_desc'] = 'Qual ambiente a plataforma está usando agora. As credenciais de homologação e de produção ficam guardadas lado a lado, então trocar não exige redigitar nada — e uma chave de homologação nunca tem como ser usada em produção por engano.';
$string['environmentactive'] = 'em uso';
$string['environmentheading'] = 'Aplicação do Asaas';
$string['environmentheading_desc'] = 'Cadastre esta URL em <strong>Integrações &gt; Webhooks</strong> no Asaas — o webhook de cobranças — com versão da API 3, envio sequencial, e somente os eventos <code>PAYMENT_RECEIVED</code> e <code>PAYMENT_CONFIRMED</code>: <code>{$a}</code><br>Ponha o token abaixo no campo de autenticação do webhook; token vazio lá faz toda entrega ser recusada. Não confunda com <em>Integrações &gt; Mecanismos de Segurança</em>, que valida dinheiro <em>saindo</em> da conta: apontar aquele para cá deixaria os saques do próprio vendedor reféns deste site estar no ar.';
$string['environmentproduction'] = 'Produção';
$string['environmentsandbox'] = 'Homologação';
$string['errorapi'] = 'O Asaas recusou a requisição: {$a}';
$string['errorcreatingcharge'] = 'Não foi possível criar a cobrança. Tente de novo em instantes.';
$string['errorcurl'] = 'Não foi possível falar com o Asaas: {$a}';
$string['errorinvalidresponse'] = 'O Asaas devolveu uma resposta inesperada.';
$string['errorkeyenvironment'] = 'Esta chave é de {$a->key}, mas você está vinculando {$a->chosen}.';
$string['errorkeyformat'] = 'Isto não parece uma chave de API do Asaas válida. Confira se não houve erro ao copiar e colar.';
$string['errorkeyrejected'] = 'O Asaas recusou esta chave: {$a}';
$string['errornodocument'] = 'Esta compra precisa do CPF ou CNPJ do comprador: o Asaas recusa emitir cobrança para um cliente sem documento. Aponte o campo de perfil que guarda esse dado nas configurações do gateway, e deixe esse campo obrigatório no cadastro.';
$string['errornoencryptionkey'] = 'Este site não tem chave de cifragem, então não há como guardar a chave de um vendedor com segurança. Crie uma com admin/cli/generate_key.php antes de vincular qualquer conta.';
$string['errornosite'] = 'O Asaas exige que a URL de retorno use <strong>o mesmo domínio cadastrado na conta que emite a cobrança</strong>. Este site é <code>{$a->expected}</code>, e a conta tem <code>{$a->found}</code>. Cadastre <code>{$a->expected}</code> em Minha Conta &gt; Informações no Asaas — e não o site próprio do vendedor, que é o que se cadastraria por instinto. Ou desligue "Trazer o aluno de volta" nas configurações do gateway.';
$string['errornotlinked'] = 'Nenhuma conta Asaas vinculada em {$a}.';
$string['errornowallet'] = 'A chave é válida, mas a conta não tem carteira e por isso não pode participar de um split.';
$string['errorrefundalready'] = 'Esta venda já foi estornada.';
$string['errorrefundbillingtype'] = 'O Asaas só estorna cobranças de cartão e Pix. Boleto não tem estorno — nem depois de baixa manual. Devolva o dinheiro fora da plataforma e cancele a assinatura por aqui.';
$string['errorrefundnotfirstcycle'] = 'Só a primeira cobrança de uma assinatura pode ser estornada. Do segundo ciclo em diante, cancele a assinatura — o aluno usou os meses anteriores.';
$string['errorrefundnotpaid'] = 'Só cobrança confirmada ou recebida pode ser estornada. Logo depois do pagamento o Asaas leva cerca de meio minuto para liberar o estorno.';
$string['errorrefundunknown'] = 'Não foi encontrada cobrança do Asaas para este pagamento.';
$string['errorsamewallet'] = 'Esta é a carteira da própria plataforma. O Asaas recusa split para a carteira que criou a cobrança, então o vendedor precisa de outra conta.';
$string['errorunknownenvironment'] = 'Ambiente desconhecido.';
$string['gatewaydescription'] = 'Cobre por Pix, boleto ou cartão, com a comissão dividida automaticamente.';
$string['gatewayname'] = 'Asaas';
$string['link'] = 'Vincular conta';
$string['linkdone'] = 'Conta Asaas vinculada em {$a}.';
$string['linkedas'] = '{$a->name} · carteira {$a->wallet} · chave terminada em {$a->tail}';
$string['linkheading'] = 'Vincular uma conta Asaas';
$string['linkintro'] = 'A cobrança é criada com a chave desta conta, então o dinheiro cai nela e é ela quem emite a nota. A plataforma recebe apenas a comissão, pelo split.';
$string['linkstatus'] = 'Conta Asaas';
$string['notlinked'] = 'Não vinculada.';
$string['platformwalletid'] = 'Wallet ID da plataforma';
$string['platformwalletid_desc'] = 'A carteira que recebe a comissão — a da plataforma, não a do vendedor. Encontre no painel do Asaas ou pelo GET /wallets.';
$string['pluginname'] = 'Asaas';
$string['pluginname_desc'] = 'Receba pelo Asaas com split de pagamento: a conta do vendedor emite a cobrança e fica com o líquido, e a carteira da plataforma recebe a comissão.';
$string['privacy:metadata:asaas'] = 'Dados do comprador enviados ao Asaas para a cobrança ser emitida.';
$string['privacy:metadata:asaas:cpfcnpj'] = 'Documento do comprador, quando há um campo de perfil configurado.';
$string['privacy:metadata:asaas:email'] = 'E-mail do comprador.';
$string['privacy:metadata:asaas:name'] = 'Nome completo do comprador.';
$string['privacy:metadata:asaas:value'] = 'Valor da cobrança.';
$string['privacy:metadata:paygw_asaas'] = 'Transações do Asaas.';
$string['privacy:metadata:paygw_asaas:amount'] = 'Valor cobrado.';
$string['privacy:metadata:paygw_asaas:asaaspaymentid'] = 'Identificador da cobrança no Asaas.';
$string['privacy:metadata:paygw_asaas:currency'] = 'Moeda.';
$string['privacy:metadata:paygw_asaas:customerid'] = 'Identificador do cliente no Asaas.';
$string['privacy:metadata:paygw_asaas:status'] = 'Situação da cobrança.';
$string['privacy:metadata:paygw_asaas:timecreated'] = 'Quando a cobrança foi criada.';
$string['privacy:metadata:paygw_asaas:userid'] = 'O usuário que fez a compra.';
$string['relink'] = 'Trocar a chave';
$string['returnheading'] = 'Pagamento';
$string['returnpending'] = 'Ainda não fomos avisados de que o pagamento caiu. No Pix isso leva alguns segundos; no boleto pode levar até o próximo dia útil. Assim que cair, seu acesso é liberado automaticamente — você não precisa ficar nesta página.';
$string['returnrefunded'] = 'Este pagamento foi estornado, então o acesso não foi liberado.';
$string['savebeforelinking'] = 'Salve esta conta primeiro e depois vincule a conta Asaas.';
$string['taskreconcile'] = 'Conferir cobranças pendentes no Asaas';
$string['unlink'] = 'Desvincular';
$string['unlinkconfirm'] = 'Desvincular a conta Asaas de {$a}? Cobranças já emitidas continuam funcionando, e o outro ambiente não é afetado.';
$string['unlinkdone'] = 'Conta Asaas desvinculada em {$a}.';
$string['unlinknotice'] = 'Isto só remove o vínculo aqui. A chave continua válida no painel do Asaas — revogue lá se for essa a intenção.';
$string['usecallback'] = 'Trazer o aluno de volta';
$string['usecallback_desc'] = 'Depois de pagar, traz o aluno de volta ao Moodle automaticamente. O Asaas só aceita URL de retorno de uma conta que tenha site cadastrado — sem isso ele recusa a cobrança inteira, e não só o retorno. Desligue se algum vendedor não conseguir cadastrar o domínio: ele continua vendendo, e o aluno volta pela fatura.';
$string['webhooktoken'] = 'Token do webhook';
$string['webhooktoken_desc'] = 'Um segredo que você inventa e cola no campo de autenticação ao cadastrar o webhook no Asaas. Ele é conferido em toda notificação. Enquanto estiver vazio o webhook recusa tudo: um endpoint aberto "até alguém configurar" é exatamente a janela que interessa a quem está atacando.';
