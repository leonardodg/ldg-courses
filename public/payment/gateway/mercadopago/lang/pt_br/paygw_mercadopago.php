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
 * Strings do paygw_mercadopago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['appheading'] = 'Aplicações da plataforma';
$string['appheading_desc'] = 'Aplicações do Mercado Pago que pertencem à plataforma, e não ao vendedor. Cada tipo de integração é uma aplicação separada, com credenciais próprias, e cada uma precisa da própria autorização de cada vendedor.<br><br>Cadastre estes endereços exatos em todas elas:<br>Redirect URI: <code>{$a->callback}</code><br>Webhook: <code>{$a->webhook}</code>';
$string['apptype_bricks'] = 'Checkout Bricks';
$string['apptype_bricks_desc'] = 'Declare o modelo de integração como <strong>Checkout Bricks</strong> e assine o evento <code>payment</code>. É esta aplicação que cobra os ciclos de assinatura: a comissão viaja como <code>application_fee</code> no pagamento, e o cartão é tokenizado pelo componente do próprio Mercado Pago, então número de cartão nunca chega a este servidor.';
$string['apptype_preferences'] = 'API de Preferências (Checkout Pro)';
$string['apptype_preferences_desc'] = 'Declare o modelo de integração como <strong>API de Preferências</strong> e assine o evento <code>payment</code>. Serve à venda avulsa: a comissão viaja como <code>marketplace_fee</code> na preferência. Declarar o modelo errado faz esse campo ser ignorado sem erro nenhum.';
$string['apptype_subscriptions'] = 'Assinaturas';
$string['apptype_subscriptions_desc'] = 'Declare o modelo de integração como <strong>Assinaturas</strong> e assine os eventos <code>subscription_preapproval</code> e <code>subscription_authorized_payment</code>. Use só para assinatura sem comissão: medido em 15/09/2026, o <code>POST /preapproval</code> aceita todos os campos de taxa conhecidos, responde 201 e descarta todos em silêncio.';
$string['cardcapture'] = 'Onde o cartão é digitado';
$string['cardcapture_desc'] = 'A página de pagamento é nossa nos três modos, com o nosso layout. O que muda é o caminho que o número do cartão percorre — e com ele onde este projeto se enquadra no PCI DSS. Detalhe em docs/legal/pci-dss-captura-de-cartao.md.';
$string['cardcaptureblocked'] = 'Este site não é servido por HTTPS, então a página que coleta o cartão poderia ser reescrita em trânsito. A opção abaixo está sendo IGNORADA e os campos do Mercado Pago estão em uso. Não há configuração que compense a falta de TLS.';
$string['cardcapturebrick'] = 'Campos do Mercado Pago dentro da nossa página — o número nunca chega até nós (SAQ {$a->scope})';
$string['cardcapturedirect'] = 'Formulário nosso, o navegador manda o cartão direto ao Mercado Pago — nunca pelo nosso backend (SAQ {$a->scope})';
$string['cardcapturenative'] = 'Formulário nosso, número do cartão pelo nosso backend (SAQ {$a->scope})';
$string['cardexpiration'] = 'Validade';
$string['cardholder'] = 'Nome impresso no cartão';
$string['cardholderdoc'] = 'CPF do titular';
$string['cardmonth'] = 'Mês';
$string['cardnumber'] = 'Número do cartão';
$string['cardsecuritycode'] = 'Código de segurança';
$string['cardyear'] = 'Ano';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Número da aplicação mostrado no painel de desenvolvedor do Mercado Pago.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Usado apenas para trocar o código de autorização pelo token do vendedor. Nunca vai para o navegador.';
$string['commonheading'] = 'Configurações comuns a todas as aplicações';
$string['commonheading_desc'] = 'São propriedades do marketplace como um todo, e não de uma integração. Tê-las por aplicação permitiria justamente as misturas que o Mercado Pago recusa.';
$string['confirmcycleamount'] = 'Confirme a cobrança de {$a} deste ciclo.';
$string['confirmcyclecancel'] = 'Agora não — voltar para minhas assinaturas';
$string['confirmcycleexplain'] = 'Esta conta não consegue cobrar seu cartão automaticamente. Digite o código de segurança para confirmar — ele vai direto para o Mercado Pago e nunca é guardado.';
$string['confirmcycletitle'] = 'Confirmar ciclo da assinatura';
$string['errorapi'] = 'O Mercado Pago recusou a requisição. {$a}';
$string['errorapistep'] = 'O Mercado Pago recusou a requisição no passo "{$a->step}". {$a->message}';
$string['errorauthorisationrefused'] = 'A autorização não foi concluída no Mercado Pago. Nada foi vinculado; você pode tentar de novo.';
$string['errorcardtokenmissing'] = 'Não foi possível ler o cartão. Confira os dados e tente de novo.';
$string['errorcreatingpreference'] = 'Não foi possível iniciar o pagamento. Tente de novo em instantes.';
$string['errorcurl'] = 'Não foi possível falar com o Mercado Pago: {$a}';
$string['errorcustomer'] = 'O Mercado Pago não conseguiu criar o cliente desta assinatura.';
$string['errorinvalidresponse'] = 'O Mercado Pago devolveu uma resposta inesperada para {$a}';
$string['errormissingappconfig'] = 'A aplicação {$a} não está configurada. Informe o client ID e o secret dela nas configurações do plugin.';
$string['errormissingpublickey'] = 'A aplicação {$a} está sem public key configurada, então não há como montar os campos do cartão. Informe-a nas configurações do plugin.';
$string['errornopendingflow'] = 'Não há autorização em andamento neste navegador. Isso acontece quando esta página é aberta diretamente — por exemplo para conferir o redirect URI recém-cadastrado — ou quando a sessão terminou enquanto você autorizava no Mercado Pago. Comece de novo pela conta de pagamento.';
$string['errornotlinked'] = 'Vincule a conta do Mercado Pago antes de habilitar este gateway.';
$string['errorrefundalready'] = 'Esta venda já foi estornada.';
$string['errorrefundnotfirstcycle'] = 'Só o primeiro ciclo de uma assinatura pode ser estornado. O estorno parcial não reduz a comissão já repassada à plataforma, então o vendedor ficaria com ela no prejuízo.';
$string['errorrefundnotpaid'] = 'Esta venda não foi paga, então não há o que estornar.';
$string['errorrefundunknown'] = 'Esta venda não foi encontrada no gateway do Mercado Pago.';
$string['errorsitemismatch'] = 'Este marketplace opera em {$a->platform} e a conta autorizada é de {$a->seller}. O Mercado Pago só divide pagamento entre contas do mesmo país, então esta conta não pode ser vinculada. Use uma conta de {$a->platform}.';
$string['errorstaleflow'] = 'Esta autorização começou antes de o plugin ser atualizado, então não há como saber a que aplicação do Mercado Pago ela pertence. Nada foi vinculado. Comece de novo.';
$string['errorstatemismatch'] = 'Não foi possível verificar a autorização. Comece o processo de novo.';
$string['errorsubscriptionnotfound'] = 'Esta assinatura não foi encontrada, ou não pertence a você.';
$string['errorunknownapptype'] = 'Tipo de aplicação do Mercado Pago desconhecido.';
$string['errorverifyaccount'] = 'A conta foi autorizada mas não pôde ser verificada no Mercado Pago, então não foi vinculada. Tente de novo. ({$a})';
$string['gatewaydescription'] = 'Pague com Pix, cartão ou boleto pelo Checkout Pro do Mercado Pago. O valor é dividido automaticamente entre o vendedor e a plataforma.';
$string['gatewayname'] = 'Mercado Pago';
$string['invoicedue_body'] = 'Sua assinatura tem uma cobrança de {$a->amount} pendente. Pague aqui: {$a->url}';
$string['invoicedue_subject'] = 'Cobrança pendente da assinatura';
$string['linkaccount'] = 'Vincular conta do Mercado Pago';
$string['linkaccounttype'] = 'Vincular conta para {$a}';
$string['messageprovider:invoicedue'] = 'Fatura de assinatura pendente (Pix ou boleto)';
$string['messageprovider:reminderupcoming'] = 'Renovação de assinatura chegando (Pix ou boleto)';
$string['oauthcurrency'] = 'Recebe em {$a}.';
$string['oauthexpired'] = 'A autorização venceu. Vincule a conta de novo.';
$string['oauthlinked'] = 'Vinculada ao usuário {$a->mpuserid} do Mercado Pago. Autorização válida até {$a->expires}.';
$string['oauthnotlinked'] = 'Ainda não vinculada.';
$string['oauthstatus'] = 'Contas do Mercado Pago';
$string['payerboletoaddress'] = 'Endereço de cobrança (obrigatório para boleto)';
$string['payercity'] = 'Cidade';
$string['payerdoc'] = 'CPF';
$string['payername'] = 'Nome completo';
$string['payerneighborhood'] = 'Bairro';
$string['payerstate'] = 'Estado (UF)';
$string['payerstreet'] = 'Rua';
$string['payerstreetnumber'] = 'Número';
$string['payerzipcode'] = 'CEP';
$string['paymentapproved'] = 'Pagamento aprovado. Bom curso!';
$string['paymentpending'] = 'Estamos esperando o Mercado Pago confirmar o pagamento. Com Pix isso costuma levar alguns segundos. O acesso é liberado sozinho assim que cair — você não precisa pagar de novo.';
$string['paymentrejected'] = 'O pagamento não foi concluído. Nada foi cobrado.';
$string['platformsite'] = 'País do marketplace';
$string['platformsite_desc'] = 'País da conta do Mercado Pago que recebe a comissão. Ele define onde os vendedores autorizam e quais contas podem ser vinculadas: o split só funciona entre contas do mesmo país, porque a comissão cai na conta da plataforma e uma conta só guarda a moeda do próprio país. Vendedores de outros países precisam de um marketplace separado, com aplicação própria.';
$string['pluginname'] = 'Mercado Pago';
$string['privacy:metadata'] = 'O plugin do Mercado Pago guarda o token de autorização do vendedor na conta de pagamento e envia ao Mercado Pago o valor do pagamento e o e-mail do comprador.';
$string['privacy:metadata:mercadopago'] = 'Dados enviados ao Mercado Pago para que o pagamento possa ser cobrado. O Mercado Pago é o controlador do que recebe.';
$string['privacy:metadata:mercadopago:amount'] = 'O valor a cobrar.';
$string['privacy:metadata:mercadopago:currency'] = 'A moeda da cobrança.';
$string['privacy:metadata:mercadopago:itemname'] = 'Uma descrição do que está sendo comprado.';
$string['privacy:metadata:paygw_mercadopago'] = 'Transações de pagamento tratadas por este gateway.';
$string['privacy:metadata:paygw_mercadopago:amount'] = 'O valor cobrado.';
$string['privacy:metadata:paygw_mercadopago:currency'] = 'A moeda cobrada.';
$string['privacy:metadata:paygw_mercadopago:cycles'] = 'De que ciclo da assinatura esta cobrança é.';
$string['privacy:metadata:paygw_mercadopago:mpcardid'] = 'O identificador do cartão guardado pelo Mercado Pago. O número do cartão nunca é guardado por este site.';
$string['privacy:metadata:paygw_mercadopago:mpcustomerid'] = 'O identificador do cliente que o Mercado Pago mantém para esta pessoa. Não é dado de cartão.';
$string['privacy:metadata:paygw_mercadopago:mppaymentid'] = 'O identificador do pagamento no Mercado Pago.';
$string['privacy:metadata:paygw_mercadopago:status'] = 'Se o pagamento foi aprovado, recusado ou está pendente.';
$string['privacy:metadata:paygw_mercadopago:subscriptionid'] = 'A que assinatura esta cobrança pertence.';
$string['privacy:metadata:paygw_mercadopago:timecreated'] = 'Quando o pagamento foi iniciado.';
$string['privacy:metadata:paygw_mercadopago:userid'] = 'Quem pagou.';
$string['publickey'] = 'Public key (produção)';
$string['publickey_desc'] = 'Mostrada no painel do Mercado Pago ao lado do access token de produção. Só é necessária para montar os campos do cartão no navegador — ela é pública por natureza e aparece no fonte da página.';
$string['publickeytest'] = 'Public key (teste)';
$string['publickeytest_desc'] = 'A chave com prefixo TEST- da mesma aplicação. Qual das duas vale segue a opção "Modo de teste" abaixo, para que comprador, vendedor e aplicação fiquem do mesmo lado. O client ID e o secret são os mesmos nos dois ambientes — só as chaves mudam.';
$string['relinkaccount'] = 'Vincular outra conta';
$string['reminderdays'] = 'Dias de antecedência para avisar sobre Pix/boleto';
$string['reminderdays_desc'] = 'O cartão cobra sozinho, então não precisa de aviso. Pix e boleto precisam: cada ciclo exige o aluno agir, e sem um aviso antes de a fatura existir, ele só fica sabendo quando ela já está vencendo.';
$string['reminderupcoming_body'] = 'Sua assinatura vai renovar em breve, e a próxima cobrança é de {$a}. Pix e boleto não são cobrados sozinhos — um novo será emitido quando vencer, e você recebe um aviso de novo.';
$string['reminderupcoming_subject'] = 'Sua assinatura renova em breve';
$string['savebeforelinking'] = 'Salve este gateway primeiro e volte para vincular a conta do Mercado Pago.';
$string['settingforapp'] = '{$a->setting} · {$a->app}';
$string['subscribeamount'] = 'Você está assinando por {$a} a cada ciclo.';
$string['subscribeboleto'] = 'Boleto';
$string['subscribeboletonotautomatic'] = 'Boleto não é cobrado sozinho. Um novo é emitido a cada ciclo, e você recebe um aviso quando estiver pronto para pagar.';
$string['subscribecard'] = 'Cartão';
$string['subscribecardnotstored'] = 'Quem guarda o cartão é o Mercado Pago, nunca este site.';
$string['subscribecardonly'] = 'Somente cartão — Pix e boleto não podem ser cobrados automaticamente.';
$string['subscribeconfirm'] = 'Confirmar assinatura';
$string['subscribecycles'] = 'No máximo {$a} cobranças no total.';
$string['subscribeevery'] = 'Cobrança a cada {$a} dias.';
$string['subscribeinvoicewaiting'] = 'Esperando o pagamento. Esta página se atualiza sozinha assim que o Mercado Pago confirmar.';
$string['subscribepaymentmethod'] = 'Forma de pagamento';
$string['subscribepix'] = 'Pix';
$string['subscribepixnotautomatic'] = 'Pix não é cobrado sozinho. Um novo QR code é emitido a cada ciclo, e você recebe um aviso quando estiver pronto para pagar.';
$string['subscribetitle'] = 'Dados do cartão';
$string['subscribeuntilcancelled'] = 'Cobrança até você cancelar.';
$string['subscribeviewboleto'] = 'Abrir boleto';
$string['subscribewheretocancel'] = 'Você pode cancelar quando quiser, em Minhas assinaturas.';
$string['subscriptioncycle'] = 'Assinatura — ciclo {$a}';
$string['switchtocardcancel'] = 'Continuar pagando por Pix/boleto';
$string['switchtocarddone'] = 'Pronto. A partir do próximo ciclo, esta assinatura cobra o cartão automaticamente.';
$string['switchtocardexplain'] = 'Isto não cobra nada agora. O cartão fica guardado para os PRÓXIMOS ciclos — o atual, se já foi pago por Pix ou boleto, continua como estava.';
$string['taskchargeduecycles'] = 'Emitir ciclos vencidos das assinaturas do Mercado Pago (pede confirmação do aluno)';
$string['taskreconcile'] = 'Conciliar transações pendentes do Mercado Pago';
$string['taskrefreshtokens'] = 'Renovar tokens de vendedores do Mercado Pago';
$string['taskremindupcomingcycles'] = 'Avisar sobre ciclos de assinatura por Pix/boleto chegando';
$string['testmode'] = 'Modo de teste';
$string['testmode_desc'] = 'Emite tokens de teste quando os vendedores vinculam a conta, para que todo o fluxo rode no sandbox do Mercado Pago. Comprador, vendedor e a aplicação da plataforma precisam estar todos do mesmo lado: uma aplicação real com vendedor de teste é recusada com "uma das partes é de teste". Mudar isto não converte vínculos existentes — os vendedores precisam vincular de novo. Nunca deixe ligado em produção: os pagamentos reais parariam de funcionar.';
$string['unlinkaccount'] = 'Desvincular conta';
$string['unlinkconfirm'] = 'Desvincular o usuário {$a} do Mercado Pago desta conta de pagamento? O gateway será desabilitado e esta empresa deixa de vender até que uma conta seja vinculada de novo. Cursos já comprados mantêm o acesso. Isto não revoga a autorização dentro do Mercado Pago — o vendedor pode removê-la nas configurações da conta dele.';
$string['unlinkdone'] = 'Conta desvinculada. O gateway foi desabilitado.';
$string['webhooksecret'] = 'Assinatura secreta do webhook';
$string['webhooksecret_desc'] = 'A "assinatura secreta" que esta aplicação mostra no painel do Mercado Pago. Cada aplicação tem a sua, e as notificações são recusadas quando a assinatura não confere. Em branco, a assinatura não é conferida — aceitável só durante a configuração.';
