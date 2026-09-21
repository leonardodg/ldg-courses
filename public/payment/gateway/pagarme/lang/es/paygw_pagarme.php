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
 * Strings de paygw_pagarme.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'Clave secreta';
$string['apikey_help'] = 'La clave secreta del vendedor, que empieza con sk_. Se guarda cifrada y no se muestra de nuevo.';
$string['billingcity'] = 'Ciudad';
$string['billingline1'] = 'Calle, número y barrio';
$string['billingstate'] = 'Estado (dos letras)';
$string['billingzipcode'] = 'Código postal';
$string['boletoinstructions'] = 'Pago de acceso a curso';
$string['cardcvv'] = 'Código de seguridad';
$string['cardexpiry'] = 'Vencimiento (MM/AA)';
$string['cardheading'] = 'Datos de la tarjeta';
$string['cardholder'] = 'Nombre en la tarjeta';
$string['cardintro'] = 'Los datos de su tarjeta van directo a Pagar.me y no pasan por este sitio.';
$string['cardnumber'] = 'Número de la tarjeta';
$string['cardsubmit'] = 'Pagar';
$string['chargeheading'] = 'Cobros';
$string['defaultdescription'] = 'Acceso a curso';
$string['documentfield'] = 'Campo de perfil con el CPF';
$string['documentfield_desc'] = 'Nombre corto del campo de perfil que guarda el CPF o CNPJ del comprador. Pagar.me lo exige.';
$string['duedays'] = 'Días hasta el vencimiento del boleto';
$string['duedays_desc'] = 'Plazo que tiene el comprador para pagar un boleto.';
$string['environment'] = 'Entorno';
$string['environment_desc'] = 'En qué entorno está cobrando la plataforma.';
$string['environmentheading'] = 'Entorno y webhook';
$string['environmentheading_desc'] = 'Registre esta dirección en el panel de Pagar.me: <code>{$a}</code>';
$string['environmentproduction'] = 'Producción';
$string['environmentsandbox'] = 'Pruebas';
$string['errorapi'] = 'Pagar.me rechazó la solicitud: {$a}';
$string['errorchargefailed'] = 'El cobro se creó pero falló en el adquirente: {$a}';
$string['errorchargemissing'] = 'Pagar.me confirmó el pedido pero no devolvió ningún cobro para pagar.';
$string['errorcreatingcharge'] = 'No se pudo crear el cobro. Inténtelo de nuevo en un momento.';
$string['errorcurl'] = 'No se pudo contactar con Pagar.me: {$a}';
$string['errorinvalidresponse'] = 'Pagar.me devolvió algo que no es una respuesta válida.';
$string['errorkeyenvironment'] = 'Esa clave es del otro entorno. La clave que empieza con sk_test_ es de pruebas.';
$string['errorkeyrejected'] = 'Pagar.me rechazó esta clave.';
$string['errornodocument'] = 'Su perfil no tiene CPF. Complételo antes de comprar.';
$string['errornoencryptionkey'] = 'Este sitio no tiene clave de cifrado. Ejecute admin/cli/generate_key.php antes de vincular una cuenta.';
$string['errornophone'] = 'Su perfil no tiene teléfono. Complételo antes de comprar — Pagar.me lo exige.';
$string['errornotlinked'] = 'No hay ninguna cuenta Pagar.me vinculada en {$a}.';
$string['errorrecipientrejected'] = 'Pagar.me no reconoce ese receptor en esta cuenta.';
$string['errorrecurringunsupported'] = 'Pagar.me todavía no divide una suscripción, así que una oferta recurrente quedaría sin comisión. Use una oferta única, o pida a Pagar.me habilitar split en suscripciones para esta cuenta.';
$string['errorrefundalready'] = 'Este cobro ya fue reembolsado.';
$string['errorrefundmethod'] = 'Pagar.me no reembolsa este medio de pago.';
$string['errorrefundnotfirstcycle'] = 'Solo se puede reembolsar el primer ciclo de una suscripción.';
$string['errorrefundnotpaid'] = 'Este cobro nunca se pagó, así que no hay nada que reembolsar.';
$string['errorrefundunknown'] = 'Esta venta no se puede reembolsar automáticamente.';
$string['errorsamerecipient'] = 'El receptor de la plataforma y el del vendedor son el mismo. No se dividiría nada.';
$string['gatewaydescription'] = 'Pague con Pix, boleto o tarjeta.';
$string['gatewayname'] = 'Pagar.me';
$string['link'] = 'Vincular cuenta';
$string['linkdone'] = 'Cuenta vinculada.';
$string['linkdonenosplit'] = 'Cuenta vinculada, pero Pagar.me todavía no tiene un recibidor predeterminado para ella - los cobros no dividirán comisión hasta que el vendedor termine su registro en Pagar.me y vuelvas a vincular.';
$string['linkedas'] = 'Vinculada a {$a->account}, clave terminada en {$a->tail}.';
$string['linkheading'] = 'Vincular una cuenta Pagar.me';
$string['linkintro'] = 'Pegue la clave secreta del vendedor y el id del receptor de la plataforma dentro de esa cuenta. Ambos se verifican en la API antes de habilitar la pasarela.';
$string['linkstatus'] = 'Estado del vínculo';
$string['methodboleto'] = 'Boleto';
$string['methodcreditcard'] = 'Tarjeta de crédito';
$string['methodpix'] = 'Pix';
$string['notlinked'] = 'No hay ninguna cuenta vinculada en {$a}.';
$string['paymentmethod'] = 'Medio de pago';
$string['paymentmethod_desc'] = 'Con qué medio se cobra al comprador.';
$string['pixcopied'] = 'Copiado.';
$string['pixcopy'] = 'Copiar el código Pix';
$string['pixexpiresin'] = 'Validez del código QR, en minutos';
$string['pixexpiresin_desc'] = 'Pagar.me solo acepta un valor entre 15 y 60 minutos.';
$string['pixheading'] = 'Pagar con Pix';
$string['pixinstructions'] = 'Apunte la cámara de la app de su banco al código QR, o copie el código de abajo. El acceso se libera en cuanto el pago se acredite.';
$string['pixpaid'] = 'Pago confirmado. Llevándolo al curso.';
$string['pixwaiting'] = 'Esperando el pago...';
$string['platformrecipient'] = 'Id del receptor de la plataforma';
$string['platformrecipient_help'] = 'El id rp_ de la plataforma dentro de la cuenta Pagar.me de este vendedor. Es distinto en cada vendedor, porque un receptor pertenece a una sola cuenta.';
$string['pluginname'] = 'Pagar.me';
$string['pluginname_desc'] = 'Cobra por Pagar.me, con la comisión enviada por split a la plataforma.';
$string['privacy:metadata:pagarme'] = 'Datos del comprador enviados a Pagar.me para emitir el cobro.';
$string['privacy:metadata:pagarme:document'] = 'El CPF o CNPJ del comprador, exigido por Pagar.me.';
$string['privacy:metadata:pagarme:email'] = 'El correo del comprador.';
$string['privacy:metadata:pagarme:name'] = 'El nombre completo del comprador.';
$string['privacy:metadata:pagarme:value'] = 'El importe cobrado.';
$string['privacy:metadata:paygw_pagarme'] = 'Cobros de Pagar.me emitidos por este sitio.';
$string['privacy:metadata:paygw_pagarme:amount'] = 'El importe del cobro.';
$string['privacy:metadata:paygw_pagarme:chargeid'] = 'El identificador del cobro en Pagar.me.';
$string['privacy:metadata:paygw_pagarme:currency'] = 'La moneda del cobro.';
$string['privacy:metadata:paygw_pagarme:customerid'] = 'El identificador del comprador en la cuenta Pagar.me del vendedor.';
$string['privacy:metadata:paygw_pagarme:status'] = 'El estado del cobro.';
$string['privacy:metadata:paygw_pagarme:timecreated'] = 'Cuándo se creó el cobro.';
$string['privacy:metadata:paygw_pagarme:userid'] = 'El usuario que hizo la compra.';
$string['publickey'] = 'Clave pública';
$string['publickey_desc'] = 'La clave pk_, usada por el navegador para convertir los datos de la tarjeta en un token. No es secreta.';
$string['relink'] = 'Cambiar la clave';
$string['returnheading'] = 'Su pago';
$string['returnpending'] = 'Todavía no nos avisaron de este pago. En cuanto se acredite, su acceso se libera — no hace falta pagar de nuevo.';
$string['returnrefunded'] = 'Este pago fue reembolsado.';
$string['savebeforelinking'] = 'Guarde la cuenta de pago antes de vincularle una cuenta Pagar.me.';
$string['sellerrecipient'] = 'Id del receptor del vendedor';
$string['taskreconcile'] = 'Revisar cobros pendientes en Pagar.me';
$string['unlink'] = 'Desvincular';
$string['unlinkconfirm'] = '¿Desvincular la cuenta Pagar.me de {$a}? Los cobros ya hechos quedan como están.';
$string['unlinkdone'] = 'Cuenta desvinculada.';
$string['unlinknotice'] = 'El otro entorno sigue vinculado.';
$string['webhookpassword'] = 'Contraseña del webhook';
$string['webhookpassword_desc'] = 'La contraseña que Pagar.me envía en HTTP Basic al llamar al webhook. Defínala en el panel de Pagar.me.';
$string['webhookuser'] = 'Usuario del webhook';
$string['webhookuser_desc'] = 'El usuario que Pagar.me envía en HTTP Basic al llamar al webhook.';
