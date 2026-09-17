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
 * Cadenas de paygw_mercadopago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['appheading'] = 'Aplicaciones de la plataforma';
$string['appheading_desc'] = 'Aplicaciones de Mercado Pago que pertenecen a la plataforma, no al vendedor. Cada tipo de integracion es una aplicacion aparte, con credenciales propias, y cada una necesita su propia autorizacion de cada vendedor.<br><br>Registre estas URL exactas en todas ellas:<br>Redirect URI: <code>{$a->callback}</code><br>Webhook: <code>{$a->webhook}</code>';
$string['apptype_bricks'] = 'Checkout Bricks';
$string['apptype_bricks_desc'] = 'Declare el modelo de integracion como <strong>Checkout Bricks</strong> y suscriba el evento <code>payment</code>. Es esta aplicacion la que cobra los ciclos de suscripcion: la comision viaja como <code>application_fee</code> en el pago, y la tarjeta la tokeniza el componente del propio Mercado Pago, asi que ningun numero de tarjeta llega a este servidor.';
$string['apptype_preferences'] = 'API de Preferencias (Checkout Pro)';
$string['apptype_preferences_desc'] = 'Declare el modelo de integracion como <strong>API de Preferencias</strong> y suscriba el evento <code>payment</code>. Sirve para la venta suelta: la comision viaja como <code>marketplace_fee</code> en la preferencia. Declarar el modelo equivocado hace que ese campo se ignore sin ningun error.';
$string['apptype_subscriptions'] = 'Suscripciones';
$string['apptype_subscriptions_desc'] = 'Declare el modelo de integracion como <strong>Suscripciones</strong> y suscriba los eventos <code>subscription_preapproval</code> y <code>subscription_authorized_payment</code>. Uselo solo para suscripcion sin comision: medido el 15/09/2026, <code>POST /preapproval</code> acepta todos los campos de comision conocidos, responde 201 y los descarta todos en silencio.';
$string['cardcapture'] = 'Donde se escribe la tarjeta';
$string['cardcapture_desc'] = 'La pagina de pago es nuestra en los tres modos, con nuestro propio diseno. Lo que cambia es el camino que recorre el numero de la tarjeta, y con el donde encaja este proyecto en PCI DSS. Detalle en docs/legal/pci-dss-captura-de-cartao.md.';
$string['cardcaptureblocked'] = 'Este sitio no se sirve por HTTPS, asi que la pagina que recoge la tarjeta podria reescribirse en transito. La opcion de abajo se esta IGNORANDO y se usan los campos de Mercado Pago. Ninguna configuracion compensa la falta de TLS.';
$string['cardcapturebrick'] = 'Campos de Mercado Pago dentro de nuestra pagina: el numero nunca llega hasta nosotros (SAQ {$a->scope})';
$string['cardcapturedirect'] = 'Formulario propio, el navegador envia la tarjeta directo a Mercado Pago, nunca por nuestro backend (SAQ {$a->scope})';
$string['cardcapturenative'] = 'Formulario propio, numero de tarjeta por nuestro backend (SAQ {$a->scope})';
$string['cardexpiration'] = 'Vencimiento';
$string['cardholder'] = 'Nombre en la tarjeta';
$string['cardholderdoc'] = 'Documento del titular (CPF)';
$string['cardmonth'] = 'Mes';
$string['cardnumber'] = 'Numero de tarjeta';
$string['cardsecuritycode'] = 'Codigo de seguridad';
$string['cardyear'] = 'Ano';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Número de la aplicación que aparece en el panel de desarrolladores de Mercado Pago.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Se usa solo para intercambiar el código de autorización por el token del vendedor. Nunca se envía al navegador.';
$string['commonheading'] = 'Ajustes comunes a todas las aplicaciones';
$string['commonheading_desc'] = 'Son propiedades del marketplace en conjunto, no de una integracion. Tenerlas por aplicacion permitiria justo las mezclas que Mercado Pago rechaza.';
$string['errorapi'] = 'Mercado Pago rechazó la solicitud. {$a}';
$string['errorapistep'] = 'Mercado Pago rechazo la solicitud en el paso "{$a->step}". {$a->message}';
$string['errorauthorisationrefused'] = 'La autorizacion no se completo en Mercado Pago. No se vinculo nada; puede intentarlo de nuevo.';
$string['errorcardtokenmissing'] = 'No se pudo leer la tarjeta. Revise los datos e intentelo de nuevo.';
$string['errorcreatingpreference'] = 'No se pudo iniciar el pago. Probá de nuevo en unos instantes.';
$string['errorcurl'] = 'No se pudo llegar a Mercado Pago: {$a}';
$string['errorcustomer'] = 'Mercado Pago no pudo crear el cliente de esta suscripcion.';
$string['errorinvalidresponse'] = 'Mercado Pago devolvió una respuesta inesperada para {$a}';
$string['errormissingappconfig'] = 'La aplicacion {$a} no esta configurada. Indique su client ID y su secret en los ajustes del plugin.';
$string['errormissingpublickey'] = 'La aplicacion {$a} no tiene public key configurada, asi que no se pueden construir los campos de la tarjeta. Indiquela en los ajustes del plugin.';
$string['errornopendingflow'] = 'No hay ninguna autorizacion en curso en este navegador. Ocurre cuando esta pagina se abre directamente - por ejemplo para comprobar el redirect URI recien registrado - o cuando la sesion termino mientras usted autorizaba en Mercado Pago. Empiece de nuevo desde la cuenta de pago.';
$string['errornotlinked'] = 'Vinculá la cuenta de Mercado Pago antes de habilitar esta pasarela.';
$string['errorrefundalready'] = 'Esta venta ya fue reembolsada.';
$string['errorrefundnotfirstcycle'] = 'Solo se puede reembolsar el primer ciclo de una suscripcion. El reembolso parcial no reduce la comision ya transferida a la plataforma, asi que el vendedor la absorberia.';
$string['errorrefundnotpaid'] = 'Esta venta no fue pagada, asi que no hay nada que reembolsar.';
$string['errorrefundunknown'] = 'No se encontro esta venta en la pasarela de Mercado Pago.';
$string['errorsitemismatch'] = 'Este marketplace opera en {$a->platform} y la cuenta que autorizaste es de {$a->seller}. Mercado Pago solo divide pagos entre cuentas del mismo país, así que esta cuenta no se puede vincular. Usá una cuenta de {$a->platform}.';
$string['errorstaleflow'] = 'Esta autorizacion empezo antes de actualizar el plugin, asi que no hay forma de saber a que aplicacion de Mercado Pago pertenece. No se vinculo nada. Empiece de nuevo.';
$string['errorstatemismatch'] = 'No se pudo verificar la autorización. Empezá el proceso de nuevo.';
$string['errorsubscriptionnotfound'] = 'No se encontro esta suscripcion, o no le pertenece.';
$string['errorunknownapptype'] = 'Tipo de aplicacion de Mercado Pago desconocido.';
$string['errorverifyaccount'] = 'La cuenta fue autorizada pero no se pudo verificar con Mercado Pago, así que no se vinculó. Probá de nuevo. ({$a})';
$string['gatewaydescription'] = 'Pagá con Pix, tarjeta o efectivo mediante Checkout Pro de Mercado Pago. El monto se divide automáticamente entre el vendedor y la plataforma.';
$string['gatewayname'] = 'Mercado Pago';
$string['invoicedue_body'] = 'Tu suscripción tiene un cobro pendiente de {$a->amount}. Pagalo acá: {$a->url}';
$string['invoicedue_subject'] = 'Cobro pendiente de la suscripción';
$string['linkaccount'] = 'Vincular cuenta de Mercado Pago';
$string['linkaccounttype'] = 'Vincular cuenta para {$a}';
$string['messageprovider:invoicedue'] = 'Factura de suscripción pendiente (Pix o boleto)';
$string['oauthcurrency'] = 'Cobros en {$a}.';
$string['oauthexpired'] = 'La autorización venció. Vinculá la cuenta de nuevo.';
$string['oauthlinked'] = 'Vinculado al usuario {$a->mpuserid} de Mercado Pago. Autorización válida hasta {$a->expires}.';
$string['oauthnotlinked'] = 'Aun no vinculada.';
$string['oauthstatus'] = 'Cuentas de Mercado Pago';
$string['payerboletoaddress'] = 'Dirección de facturación (obligatoria para boleto)';
$string['payercity'] = 'Ciudad';
$string['payerdoc'] = 'CPF';
$string['payername'] = 'Nombre completo';
$string['payerneighborhood'] = 'Barrio';
$string['payerstate'] = 'Estado (UF)';
$string['payerstreet'] = 'Calle';
$string['payerstreetnumber'] = 'Número';
$string['payerzipcode'] = 'Código postal (CEP)';
$string['paymentapproved'] = 'Pago aprobado. ¡Disfrutá el curso!';
$string['paymentpending'] = 'Estamos esperando que Mercado Pago confirme tu pago. Con Pix suele tardar unos segundos. El acceso se habilita automáticamente en cuanto se acredite — no hace falta que pagues de nuevo.';
$string['paymentrejected'] = 'El pago no se completó. No se cobró nada.';
$string['platformsite'] = 'País del marketplace';
$string['platformsite_desc'] = 'País de la cuenta de Mercado Pago que recibe la comisión. Define dónde autorizan los vendedores y qué cuentas se pueden vincular: el split solo funciona entre cuentas del mismo país, porque la comisión cae en la cuenta de la plataforma y una cuenta solo guarda la moneda de su propio país. Los vendedores de otros países necesitan un marketplace aparte, con su propia aplicación.';
$string['pluginname'] = 'Mercado Pago';
$string['privacy:metadata'] = 'El plugin Mercado Pago guarda el token de autorización del vendedor en la cuenta de pago y envía el monto del pago y el correo del comprador a Mercado Pago.';
$string['privacy:metadata:mercadopago'] = 'Datos enviados a Mercado Pago para poder cobrar el pago. Mercado Pago es el responsable de lo que recibe.';
$string['privacy:metadata:mercadopago:amount'] = 'El monto a cobrar.';
$string['privacy:metadata:mercadopago:currency'] = 'La moneda del cobro.';
$string['privacy:metadata:mercadopago:itemname'] = 'Una descripción de lo que se está comprando.';
$string['privacy:metadata:paygw_mercadopago'] = 'Transacciones de pago gestionadas por esta pasarela.';
$string['privacy:metadata:paygw_mercadopago:amount'] = 'El monto cobrado.';
$string['privacy:metadata:paygw_mercadopago:currency'] = 'La moneda cobrada.';
$string['privacy:metadata:paygw_mercadopago:cycles'] = 'De que ciclo de la suscripcion es este cobro.';
$string['privacy:metadata:paygw_mercadopago:mpcardid'] = 'El identificador de la tarjeta guardada por Mercado Pago. El numero de la tarjeta nunca lo guarda este sitio.';
$string['privacy:metadata:paygw_mercadopago:mpcustomerid'] = 'El identificador del cliente que Mercado Pago mantiene para esta persona. No es dato de tarjeta.';
$string['privacy:metadata:paygw_mercadopago:mppaymentid'] = 'El identificador del pago en Mercado Pago.';
$string['privacy:metadata:paygw_mercadopago:status'] = 'Si el pago fue aprobado, rechazado o está pendiente.';
$string['privacy:metadata:paygw_mercadopago:subscriptionid'] = 'A que suscripcion pertenece este cobro.';
$string['privacy:metadata:paygw_mercadopago:timecreated'] = 'Cuándo se inició el pago.';
$string['privacy:metadata:paygw_mercadopago:userid'] = 'La persona que pagó.';
$string['publickey'] = 'Public key (produccion)';
$string['publickey_desc'] = 'Se muestra en el panel de Mercado Pago junto al access token de produccion. Solo hace falta para construir los campos de la tarjeta en el navegador: es publica por naturaleza y aparece en el fuente de la pagina.';
$string['publickeytest'] = 'Public key (prueba)';
$string['publickeytest_desc'] = 'La clave con prefijo TEST- de la misma aplicacion. Cual de las dos vale sigue la opcion "Modo de prueba" de abajo, para que comprador, vendedor y aplicacion queden del mismo lado. El client ID y el secret son los mismos en ambos entornos: solo cambian las claves.';
$string['relinkaccount'] = 'Vincular otra cuenta';
$string['savebeforelinking'] = 'Guardá esta pasarela primero, después volvé para vincular la cuenta de Mercado Pago.';
$string['settingforapp'] = '{$a->setting} · {$a->app}';
$string['subscribeamount'] = 'Se esta suscribiendo por {$a} en cada ciclo.';
$string['subscribeboleto'] = 'Boleto';
$string['subscribeboletonotautomatic'] = 'El boleto no se cobra solo. Se emite uno nuevo en cada ciclo, y te avisamos cuando esté listo para pagar.';
$string['subscribecard'] = 'Tarjeta';
$string['subscribecardnotstored'] = 'Quien guarda la tarjeta es Mercado Pago, nunca este sitio.';
$string['subscribecardonly'] = 'Solo tarjeta: Pix y boleto no se pueden cobrar automaticamente.';
$string['subscribeconfirm'] = 'Confirmar suscripcion';
$string['subscribecycles'] = 'Como maximo {$a} cobros en total.';
$string['subscribeevery'] = 'Cobro cada {$a} dias.';
$string['subscribeinvoicewaiting'] = 'Esperando el pago. Esta página se actualiza sola en cuanto Mercado Pago lo confirme.';
$string['subscribepaymentmethod'] = 'Forma de pago';
$string['subscribepix'] = 'Pix';
$string['subscribepixnotautomatic'] = 'El Pix no se cobra solo. Se emite un QR nuevo en cada ciclo, y te avisamos cuando esté listo para pagar.';
$string['subscribetitle'] = 'Datos de la tarjeta';
$string['subscribeuntilcancelled'] = 'Cobro hasta que usted cancele.';
$string['subscribeviewboleto'] = 'Abrir boleto';
$string['subscribewheretocancel'] = 'Puede cancelar cuando quiera, en Mis suscripciones.';
$string['subscriptioncycle'] = 'Suscripcion - ciclo {$a}';
$string['taskchargeduecycles'] = 'Cobrar ciclos vencidos de las suscripciones de Mercado Pago';
$string['taskreconcile'] = 'Conciliar transacciones pendientes en Mercado Pago';
$string['taskrefreshtokens'] = 'Renovar tokens de los vendedores en Mercado Pago';
$string['testmode'] = 'Modo de prueba';
$string['testmode_desc'] = 'Emite tokens de prueba cuando los vendedores vinculan su cuenta, para que todo el flujo corra en el sandbox de Mercado Pago. Comprador, vendedor y la aplicación de la plataforma tienen que estar todos del mismo lado: una aplicación real con un vendedor de prueba se rechaza con "una de las partes es de prueba". Cambiar esto no convierte los vínculos existentes — los vendedores tienen que vincular de nuevo. Nunca lo dejes activado en producción: los pagos reales dejarían de funcionar.';
$string['unlinkaccount'] = 'Desvincular cuenta';
$string['unlinkconfirm'] = '¿Desvincular al usuario {$a} de Mercado Pago de esta cuenta de pago? La pasarela se deshabilitará y esta empresa dejará de vender hasta que se vincule una cuenta de nuevo. Los cursos ya comprados conservan su acceso. Esto no revoca la autorización dentro de Mercado Pago — el vendedor puede quitarla desde la configuración de su cuenta.';
$string['unlinkdone'] = 'Cuenta desvinculada. La pasarela quedó deshabilitada.';
$string['webhooksecret'] = 'Firma secreta del webhook';
$string['webhooksecret_desc'] = 'La "firma secreta" que esta aplicacion muestra en el panel de Mercado Pago. Cada aplicacion tiene la suya, y las notificaciones se rechazan cuando la firma no coincide. Vacia, la firma no se comprueba: aceptable solo durante la configuracion.';
