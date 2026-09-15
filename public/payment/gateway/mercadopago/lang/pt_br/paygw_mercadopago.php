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
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Número da aplicação mostrado no painel de desenvolvedor do Mercado Pago.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Usado apenas para trocar o código de autorização pelo token do vendedor. Nunca vai para o navegador.';
$string['commonheading'] = 'Configurações comuns a todas as aplicações';
$string['commonheading_desc'] = 'São propriedades do marketplace como um todo, e não de uma integração. Tê-las por aplicação permitiria justamente as misturas que o Mercado Pago recusa.';
$string['errorapi'] = 'O Mercado Pago recusou a requisição. {$a}';
$string['errorcreatingpreference'] = 'Não foi possível iniciar o pagamento. Tente de novo em instantes.';
$string['errorcurl'] = 'Não foi possível falar com o Mercado Pago: {$a}';
$string['errorinvalidresponse'] = 'O Mercado Pago devolveu uma resposta inesperada para {$a}';
$string['errormissingappconfig'] = 'A aplicação {$a} não está configurada. Informe o client ID e o secret dela nas configurações do plugin.';
$string['errornotlinked'] = 'Vincule a conta do Mercado Pago antes de habilitar este gateway.';
$string['errorsitemismatch'] = 'Este marketplace opera em {$a->platform} e a conta autorizada é de {$a->seller}. O Mercado Pago só divide pagamento entre contas do mesmo país, então esta conta não pode ser vinculada. Use uma conta de {$a->platform}.';
$string['errorstatemismatch'] = 'Não foi possível verificar a autorização. Comece o processo de novo.';
$string['errorunknownapptype'] = 'Tipo de aplicação do Mercado Pago desconhecido.';
$string['errorverifyaccount'] = 'A conta foi autorizada mas não pôde ser verificada no Mercado Pago, então não foi vinculada. Tente de novo. ({$a})';
$string['gatewaydescription'] = 'Pague com Pix, cartão ou boleto pelo Checkout Pro do Mercado Pago. O valor é dividido automaticamente entre o vendedor e a plataforma.';
$string['gatewayname'] = 'Mercado Pago';
$string['linkaccount'] = 'Vincular conta do Mercado Pago';
$string['linkaccounttype'] = 'Vincular conta para {$a}';
$string['oauthcurrency'] = 'Recebe em {$a}.';
$string['oauthexpired'] = 'A autorização venceu. Vincule a conta de novo.';
$string['oauthlinked'] = 'Vinculada ao usuário {$a->mpuserid} do Mercado Pago. Autorização válida até {$a->expires}.';
$string['oauthnotlinked'] = 'Ainda não vinculada.';
$string['oauthstatus'] = 'Contas do Mercado Pago';
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
$string['privacy:metadata:paygw_mercadopago:mppaymentid'] = 'O identificador do pagamento no Mercado Pago.';
$string['privacy:metadata:paygw_mercadopago:status'] = 'Se o pagamento foi aprovado, recusado ou está pendente.';
$string['privacy:metadata:paygw_mercadopago:timecreated'] = 'Quando o pagamento foi iniciado.';
$string['privacy:metadata:paygw_mercadopago:userid'] = 'Quem pagou.';
$string['relinkaccount'] = 'Vincular outra conta';
$string['savebeforelinking'] = 'Salve este gateway primeiro e volte para vincular a conta do Mercado Pago.';
$string['taskreconcile'] = 'Conciliar transações pendentes do Mercado Pago';
$string['taskrefreshtokens'] = 'Renovar tokens de vendedores do Mercado Pago';
$string['testmode'] = 'Modo de teste';
$string['testmode_desc'] = 'Emite tokens de teste quando os vendedores vinculam a conta, para que todo o fluxo rode no sandbox do Mercado Pago. Comprador, vendedor e a aplicação da plataforma precisam estar todos do mesmo lado: uma aplicação real com vendedor de teste é recusada com "uma das partes é de teste". Mudar isto não converte vínculos existentes — os vendedores precisam vincular de novo. Nunca deixe ligado em produção: os pagamentos reais parariam de funcionar.';
$string['unlinkaccount'] = 'Desvincular conta';
$string['unlinkconfirm'] = 'Desvincular o usuário {$a} do Mercado Pago desta conta de pagamento? O gateway será desabilitado e esta empresa deixa de vender até que uma conta seja vinculada de novo. Cursos já comprados mantêm o acesso. Isto não revoga a autorização dentro do Mercado Pago — o vendedor pode removê-la nas configurações da conta dele.';
$string['unlinkdone'] = 'Conta desvinculada. O gateway foi desabilitado.';
$string['webhooksecret'] = 'Assinatura secreta do webhook';
$string['webhooksecret_desc'] = 'A "assinatura secreta" que esta aplicação mostra no painel do Mercado Pago. Cada aplicação tem a sua, e as notificações são recusadas quando a assinatura não confere. Em branco, a assinatura não é conferida — aceitável só durante a configuração.';
