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


$string['apikey'] = 'Clave de API de Asaas';
$string['apikey_help'] = 'Generá una clave en tu propia cuenta de Asaas (Configuración > Integraciones > Clave de API) y pegala acá. La clave se verifica contra Asaas antes de guardarse y queda cifrada: no vuelve a aparecer en pantalla. Es tu cuenta la que emite el cobro, se queda con el neto y emite la factura — la plataforma solo recibe su comisión, por el split.';
$string['billingboleto'] = 'Solo boleto';
$string['billingcreditcard'] = 'Solo tarjeta de crédito';
$string['billingpix'] = 'Solo Pix';
$string['billingtype'] = 'Forma de pago';
$string['billingtype_desc'] = '"Que elija el comprador" abre la factura de Asaas con todas las formas habilitadas en la cuenta del vendedor. Forzar una forma sirve mientras se prueba un único flujo.';
$string['billingundefined'] = 'Que elija el comprador';
$string['chargeheading'] = 'Cobro';
$string['defaultdescription'] = 'Compra de curso';
$string['documentfield'] = 'Campo de perfil con el documento del comprador';
$string['documentfield_desc'] = 'Nombre corto de un campo de perfil personalizado con el CPF o CNPJ del comprador. <strong>Obligatorio:</strong> Asaas crea el cliente sin documento pero se niega a emitir el cobro, así que dejarlo vacío hace que toda compra falle en el último paso. Hacé que el campo sea obligatorio al registrarse también.';
$string['duedays'] = 'Días hasta el vencimiento';
$string['duedays_desc'] = 'Cuánto tiempo sigue pagable el cobro. Un Pix se paga en minutos; la ventana existe por el boleto.';
$string['environment'] = 'Entorno';
$string['environment_desc'] = 'Qué entorno está usando la plataforma ahora. Las credenciales de sandbox y de producción se guardan lado a lado, así que cambiar no obliga a reescribir nada — y una clave de sandbox nunca puede usarse en producción por error.';
$string['environmentactive'] = 'en uso';
$string['environmentheading'] = 'Aplicación de Asaas';
$string['environmentheading_desc'] = 'Registrá esta URL en <strong>Integraciones &gt; Webhooks</strong> en Asaas — el webhook de cobros — con versión de API 3, envío secuencial, y solo los eventos <code>PAYMENT_RECEIVED</code> y <code>PAYMENT_CONFIRMED</code>: <code>{$a}</code><br>Poné el token de abajo en el campo de autenticación del webhook; un token vacío allí hace que toda entrega sea rechazada. No lo confundas con <em>Integraciones &gt; Mecanismos de Seguridad</em>, que valida dinero <em>saliendo</em> de la cuenta: apuntar ese acá dejaría los retiros del propio vendedor rehenes de que este sitio esté en línea.';
$string['environmentproduction'] = 'Producción';
$string['environmentsandbox'] = 'Sandbox';
$string['errorapi'] = 'Asaas rechazó la solicitud: {$a}';
$string['errorcreatingcharge'] = 'No se pudo crear el cobro. Probá de nuevo en un momento.';
$string['errorcurl'] = 'No se pudo contactar a Asaas: {$a}';
$string['errorinvalidresponse'] = 'Asaas devolvió una respuesta inesperada.';
$string['errorkeyenvironment'] = 'Esta clave es de {$a->key}, pero estás vinculando {$a->chosen}.';
$string['errorkeyformat'] = 'Esto no parece una clave de API de Asaas válida. Revisá si hubo un error al copiar y pegar.';
$string['errorkeyrejected'] = 'Asaas rechazó esta clave: {$a}';
$string['errornodocument'] = 'Esta compra necesita el CPF o CNPJ del comprador: Asaas se niega a emitir un cobro para un cliente sin documento. Indicá el campo de perfil que lo guarda en la configuración de la pasarela, y hacé que ese campo sea obligatorio al registrarse.';
$string['errornoencryptionkey'] = 'Este sitio no tiene clave de cifrado, así que no hay forma segura de guardar la clave de un vendedor. Creá una con admin/cli/generate_key.php antes de vincular cualquier cuenta.';
$string['errornosite'] = 'Asaas exige que la URL de retorno use <strong>el mismo dominio registrado en la cuenta que emite el cobro</strong>. Este sitio es <code>{$a->expected}</code>, y la cuenta tiene <code>{$a->found}</code>. Registrá <code>{$a->expected}</code> en Mi Cuenta &gt; Información en Asaas — y no el sitio propio del vendedor, que es lo que se registraría por instinto. O desactivá "Traer al comprador de vuelta" en la configuración de la pasarela.';
$string['errornotlinked'] = 'No hay cuenta de Asaas vinculada en {$a}.';
$string['errornowallet'] = 'La clave es válida, pero la cuenta no tiene billetera y por eso no puede participar de un split.';
$string['errorrefundalready'] = 'Esta venta ya fue reembolsada.';
$string['errorrefundbillingtype'] = 'Asaas solo reembolsa cobros de tarjeta y Pix. El boleto no admite reembolso — ni después de una baja manual. Devolvé el dinero fuera de la plataforma y cancelá la suscripción acá.';
$string['errorrefundnotfirstcycle'] = 'Solo el primer cobro de una suscripción puede reembolsarse. Del segundo ciclo en adelante, cancelá la suscripción — el estudiante usó los meses anteriores.';
$string['errorrefundnotpaid'] = 'Solo un cobro confirmado o recibido puede reembolsarse. Justo después del pago, Asaas tarda cerca de medio minuto en habilitarlo.';
$string['errorrefundunknown'] = 'No se encontró un cobro de Asaas para este pago.';
$string['errorsamewallet'] = 'Esta es la billetera de la propia plataforma. Asaas rechaza el split hacia la billetera que creó el cobro, así que el vendedor necesita otra cuenta.';
$string['errorunknownenvironment'] = 'Entorno desconocido.';
$string['gatewaydescription'] = 'Cobrá por Pix, boleto o tarjeta, con la comisión dividida automáticamente.';
$string['gatewayname'] = 'Asaas';
$string['link'] = 'Vincular cuenta';
$string['linkdone'] = 'Cuenta de Asaas vinculada en {$a}.';
$string['linkedas'] = '{$a->name} · billetera {$a->wallet} · clave terminada en {$a->tail}';
$string['linkheading'] = 'Vincular una cuenta de Asaas';
$string['linkintro'] = 'El cobro se crea con la clave de esta cuenta, así que el dinero cae en ella y es ella la que emite la factura. La plataforma recibe solamente su comisión, por el split.';
$string['linkstatus'] = 'Cuenta de Asaas';
$string['notlinked'] = 'No vinculada.';
$string['platformwalletid'] = 'Wallet ID de la plataforma';
$string['platformwalletid_desc'] = 'La billetera que recibe la comisión — la de la plataforma, no la del vendedor. La encontrás en el panel de Asaas o con GET /wallets.';
$string['pluginname'] = 'Asaas';
$string['pluginname_desc'] = 'Cobrá por Asaas con split de pagos: la cuenta del vendedor emite el cobro y se queda con el neto, y la billetera de la plataforma recibe la comisión.';
$string['privacy:metadata:asaas'] = 'Datos del comprador enviados a Asaas para emitir el cobro.';
$string['privacy:metadata:asaas:cpfcnpj'] = 'Documento del comprador, cuando hay un campo de perfil configurado.';
$string['privacy:metadata:asaas:email'] = 'Correo del comprador.';
$string['privacy:metadata:asaas:name'] = 'Nombre completo del comprador.';
$string['privacy:metadata:asaas:value'] = 'Importe del cobro.';
$string['privacy:metadata:paygw_asaas'] = 'Transacciones de Asaas.';
$string['privacy:metadata:paygw_asaas:amount'] = 'Importe cobrado.';
$string['privacy:metadata:paygw_asaas:asaaspaymentid'] = 'Identificador del cobro en Asaas.';
$string['privacy:metadata:paygw_asaas:currency'] = 'Moneda.';
$string['privacy:metadata:paygw_asaas:customerid'] = 'Identificador del cliente en Asaas.';
$string['privacy:metadata:paygw_asaas:status'] = 'Estado del cobro.';
$string['privacy:metadata:paygw_asaas:timecreated'] = 'Cuándo se creó el cobro.';
$string['privacy:metadata:paygw_asaas:userid'] = 'El usuario que hizo la compra.';
$string['relink'] = 'Cambiar la clave';
$string['returnheading'] = 'Pago';
$string['returnpending'] = 'Todavía no nos avisaron de que el pago se acreditó. Con Pix esto tarda unos segundos; con boleto puede tardar hasta el próximo día hábil. Apenas se acredite, tu acceso se libera automáticamente — no hace falta que te quedes en esta página.';
$string['returnrefunded'] = 'Este pago fue reembolsado, así que el acceso no se liberó.';
$string['savebeforelinking'] = 'Guardá esta cuenta primero y después vinculá la cuenta de Asaas.';
$string['taskreconcile'] = 'Verificar cobros pendientes en Asaas';
$string['unlink'] = 'Desvincular';
$string['unlinkconfirm'] = '¿Desvincular la cuenta de Asaas de {$a}? Los cobros ya emitidos siguen funcionando, y el otro entorno no se toca.';
$string['unlinkdone'] = 'Cuenta de Asaas desvinculada en {$a}.';
$string['unlinknotice'] = 'Esto solo quita el vínculo acá. La clave sigue válida en el panel de Asaas — revocala allí si esa es la intención.';
$string['usecallback'] = 'Traer al comprador de vuelta';
$string['usecallback_desc'] = 'Después de pagar, trae al comprador de vuelta a Moodle automáticamente. Asaas solo acepta una URL de retorno de una cuenta que tenga sitio registrado — sin eso rechaza el cobro entero, y no solo el retorno. Desactivalo si algún vendedor no puede registrar el dominio: sigue vendiendo, y el comprador vuelve por la factura.';
$string['webhooktoken'] = 'Token del webhook';
$string['webhooktoken_desc'] = 'Un secreto que inventás y pegás en el campo de autenticación al registrar el webhook en Asaas. Se verifica en cada notificación. Mientras esté vacío el webhook rechaza todo: un endpoint abierto "hasta que alguien lo configure" es justamente la ventana que le interesa a quien ataca.';
