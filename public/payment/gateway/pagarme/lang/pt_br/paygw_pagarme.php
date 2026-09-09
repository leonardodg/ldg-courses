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
 * Strings do paygw_pagarme.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'Chave secreta';
$string['apikey_help'] = 'A chave secreta do vendedor, começando com sk_. Ela é guardada cifrada e não é mostrada de novo.';
$string['boletoinstructions'] = 'Pagamento de acesso a curso';
$string['cardcvv'] = 'Código de segurança';
$string['cardexpiry'] = 'Validade (MM/AA)';
$string['cardheading'] = 'Dados do cartão';
$string['cardholder'] = 'Nome impresso no cartão';
$string['cardintro'] = 'Os dados do seu cartão vão direto para o Pagar.me e não passam por este site.';
$string['cardnumber'] = 'Número do cartão';
$string['cardsubmit'] = 'Pagar';
$string['chargeheading'] = 'Cobranças';
$string['defaultdescription'] = 'Acesso a curso';
$string['documentfield'] = 'Campo de perfil com o CPF';
$string['documentfield_desc'] = 'Nome curto do campo de perfil que guarda o CPF ou CNPJ do comprador. O Pagar.me exige.';
$string['duedays'] = 'Dias até o boleto vencer';
$string['duedays_desc'] = 'Prazo que o comprador tem para pagar um boleto.';
$string['environment'] = 'Ambiente';
$string['environment_desc'] = 'Em qual ambiente a plataforma está cobrando.';
$string['environmentheading'] = 'Ambiente e webhook';
$string['environmentheading_desc'] = 'Cadastre este endereço no painel do Pagar.me: <code>{$a}</code>';
$string['environmentproduction'] = 'Produção';
$string['environmentsandbox'] = 'Homologação';
$string['errorapi'] = 'O Pagar.me recusou a requisição: {$a}';
$string['errorchargefailed'] = 'A cobrança foi criada mas falhou no adquirente: {$a}';
$string['errorcreatingcharge'] = 'Não foi possível criar a cobrança. Tente de novo em instantes.';
$string['errorcurl'] = 'Não foi possível falar com o Pagar.me: {$a}';
$string['errorinvalidresponse'] = 'O Pagar.me devolveu algo que não é uma resposta válida.';
$string['errorkeyenvironment'] = 'Essa chave é do outro ambiente. Chave que começa com sk_test_ é de homologação.';
$string['errorkeyrejected'] = 'O Pagar.me recusou esta chave.';
$string['errornodocument'] = 'Seu perfil está sem CPF. Preencha antes de comprar.';
$string['errornoencryptionkey'] = 'Este site não tem chave de cifragem. Rode admin/cli/generate_key.php antes de vincular uma conta.';
$string['errornotlinked'] = 'Nenhuma conta Pagar.me vinculada em {$a}.';
$string['errorrecipientrejected'] = 'O Pagar.me não reconhece esse recebedor nesta conta.';
$string['errorrefundalready'] = 'Esta cobrança já foi estornada.';
$string['errorrefundmethod'] = 'O Pagar.me não estorna esta forma de pagamento.';
$string['errorrefundnotfirstcycle'] = 'Só o primeiro ciclo de uma assinatura pode ser estornado.';
$string['errorrefundnotpaid'] = 'Esta cobrança nunca foi paga, então não há o que estornar.';
$string['errorrefundunknown'] = 'Esta venda não pode ser estornada automaticamente.';
$string['errorsamerecipient'] = 'O recebedor da plataforma e o do vendedor são o mesmo. Nada seria dividido.';
$string['gatewaydescription'] = 'Pague com Pix, boleto ou cartão.';
$string['gatewayname'] = 'Pagar.me';
$string['link'] = 'Vincular conta';
$string['linkdone'] = 'Conta vinculada.';
$string['linkedas'] = 'Vinculada a {$a->account}, chave terminando em {$a->tail}.';
$string['linkheading'] = 'Vincular uma conta Pagar.me';
$string['linkintro'] = 'Cole a chave secreta do vendedor e o id do recebedor da plataforma dentro daquela conta. Os dois são conferidos na API antes de o gateway ser habilitado.';
$string['linkstatus'] = 'Situação do vínculo';
$string['methodboleto'] = 'Boleto';
$string['methodcreditcard'] = 'Cartão de crédito';
$string['methodpix'] = 'Pix';
$string['notlinked'] = 'Nenhuma conta vinculada em {$a}.';
$string['paymentmethod'] = 'Forma de pagamento';
$string['paymentmethod_desc'] = 'Com qual forma o comprador é cobrado.';
$string['pixcopied'] = 'Copiado.';
$string['pixcopy'] = 'Copiar o código Pix';
$string['pixexpiresin'] = 'Validade do QR Code, em minutos';
$string['pixexpiresin_desc'] = 'O Pagar.me só aceita valor entre 15 e 60 minutos.';
$string['pixheading'] = 'Pagar com Pix';
$string['pixinstructions'] = 'Aponte a câmera do app do seu banco para o QR Code, ou copie o código abaixo. O acesso é liberado assim que o pagamento cair.';
$string['pixpaid'] = 'Pagamento confirmado. Levando você para o curso.';
$string['pixwaiting'] = 'Aguardando o pagamento...';
$string['platformrecipient'] = 'Id do recebedor da plataforma';
$string['platformrecipient_help'] = 'O id rp_ da plataforma dentro da conta Pagar.me deste vendedor. Ele é diferente em cada vendedor, porque um recebedor pertence a uma conta só.';
$string['pluginname'] = 'Pagar.me';
$string['pluginname_desc'] = 'Cobra pelo Pagar.me, com a comissão indo por split para a plataforma.';
$string['privacy:metadata:pagarme'] = 'Dados do comprador enviados ao Pagar.me para a cobrança ser emitida.';
$string['privacy:metadata:pagarme:document'] = 'O CPF ou CNPJ do comprador, exigido pelo Pagar.me.';
$string['privacy:metadata:pagarme:email'] = 'O e-mail do comprador.';
$string['privacy:metadata:pagarme:name'] = 'O nome completo do comprador.';
$string['privacy:metadata:pagarme:value'] = 'O valor cobrado.';
$string['privacy:metadata:paygw_pagarme'] = 'Cobranças do Pagar.me emitidas por este site.';
$string['privacy:metadata:paygw_pagarme:amount'] = 'O valor da cobrança.';
$string['privacy:metadata:paygw_pagarme:chargeid'] = 'O identificador da cobrança no Pagar.me.';
$string['privacy:metadata:paygw_pagarme:currency'] = 'A moeda da cobrança.';
$string['privacy:metadata:paygw_pagarme:customerid'] = 'O identificador do comprador na conta Pagar.me do vendedor.';
$string['privacy:metadata:paygw_pagarme:status'] = 'A situação da cobrança.';
$string['privacy:metadata:paygw_pagarme:timecreated'] = 'Quando a cobrança foi criada.';
$string['privacy:metadata:paygw_pagarme:userid'] = 'O usuário que fez a compra.';
$string['publickey'] = 'Chave pública';
$string['publickey_desc'] = 'A chave pk_, usada pelo navegador para transformar os dados do cartão em token. Não é segredo.';
$string['relink'] = 'Trocar a chave';
$string['returnheading'] = 'Seu pagamento';
$string['returnpending'] = 'Ainda não fomos avisados deste pagamento. Assim que ele cair, seu acesso é liberado — não precisa pagar de novo.';
$string['returnrefunded'] = 'Este pagamento foi estornado.';
$string['savebeforelinking'] = 'Salve a conta de pagamento antes de vincular uma conta Pagar.me a ela.';
$string['sellerrecipient'] = 'Id do recebedor do vendedor';
$string['taskreconcile'] = 'Conferir cobranças pendentes no Pagar.me';
$string['unlink'] = 'Desvincular';
$string['unlinkconfirm'] = 'Desvincular a conta Pagar.me de {$a}? As cobranças já feitas continuam como estão.';
$string['unlinkdone'] = 'Conta desvinculada.';
$string['unlinknotice'] = 'O outro ambiente continua vinculado.';
$string['webhookpassword'] = 'Senha do webhook';
$string['webhookpassword_desc'] = 'A senha que o Pagar.me manda no HTTP Basic ao chamar o webhook. Defina no painel do Pagar.me.';
$string['webhookuser'] = 'Usuário do webhook';
$string['webhookuser_desc'] = 'O usuário que o Pagar.me manda no HTTP Basic ao chamar o webhook.';
