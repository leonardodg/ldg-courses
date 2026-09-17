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
 * Cadenas de local_marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdays'] = 'Acceso por {$a} días';
$string['accessgranted'] = 'Acceso habilitado. ¡Disfrutá el curso!';
$string['accesslifetime'] = 'Acceso de por vida';
$string['accessrecurring'] = 'Suscripción, se renueva cada {$a} días';
$string['accessrecurringlimited'] = 'Suscripción: {$a->billing} días por pago, hasta {$a->cycles} pagos';
$string['accessrecurringopen'] = 'Suscripción: {$a->billing} días por pago, sin fecha de fin';
$string['accessuntil'] = 'Acceso hasta {$a}';
$string['addmember'] = 'Agregar vendedor';
$string['addmember_help'] = 'Vincula a esta empresa un usuario que ya existe en la plataforma y le otorga el rol de vendedor en la categoría de la empresa. La persona debe tener cuenta.';
$string['addplan'] = 'Añadir plan';
$string['alreadyowned'] = 'Ya tenés acceso a esta oferta.';
$string['buynow'] = 'Comprar ahora';
$string['cancelconfirm'] = '¿Cancelar <strong>{$a->offer}</strong>? Conservás el acceso hasta {$a->date} — pagaste por ese período y no se te quita. Después el acceso simplemente termina, y dejamos de recordarte que renueves.';
$string['cancelconfirmlifetime'] = '¿Detener los avisos de renovación de <strong>{$a}</strong>? Tu acceso no vence, así que no cambia nada salvo los avisos.';
$string['canceldone'] = 'Suscripción cancelada. Tu acceso vale hasta {$a}.';
$string['cancelledbut'] = 'Cancelada. Tu acceso vale hasta {$a} — el período que pagaste no se te quita.';
$string['cancelledlifetime'] = 'Avisos de renovación desactivados. Tu acceso no vence.';
$string['cancelsubscription'] = 'Cancelar suscripción';
$string['cancelundo'] = 'Reactivar';
$string['cancelundone'] = 'Suscripción reactivada. Te avisaremos antes del vencimiento.';
$string['cannotsell'] = 'Solo cursos gratuitos';
$string['cansell'] = 'Vendiendo';
$string['cansellyes'] = 'Lista para vender. Pasarela activa: {$a}';
$string['commissionbase'] = 'Base de la comisión';
$string['commissionbase_desc'] = 'Sobre qué se aplica el porcentaje de comisión, para el plan o la empresa que no declare su propia base. <b>Bruto</b> significa que la plataforma recibe el porcentaje acordado sobre el precio de venta y el vendedor absorbe la tarifa de la pasarela. <b>Neto</b> significa que la tarifa sale primero y ambas partes la comparten.<br><br>Neto no existe en todas las pasarelas: Mercado Pago cobra por valor absoluto y su tarifa solo se conoce después, así que allí las ventas se cobran siempre sobre el bruto. Cada venta registra la base realmente aplicada.';
$string['commissionbasegross'] = 'sobre el bruto';
$string['commissionbaseinherit'] = 'Heredar la base del sitio';
$string['commissionbasenet'] = 'sobre el neto';
$string['commissioneffective'] = 'Comisión efectiva';
$string['commissionfromcompany'] = 'negociada con la empresa';
$string['commissionfromplan'] = 'del plan {$a}';
$string['commissionfromsite'] = 'predeterminada del sitio';
$string['commissionpct'] = 'Comisión de la plataforma (%)';
$string['commissionsourcecompany'] = 'negociada';
$string['commissionsourceplan'] = 'plan';
$string['commissionsourcepolicy'] = 'política del curso';
$string['commissionsourcesite'] = 'predeterminado del sitio';
$string['companies'] = 'Empresas';
$string['company'] = 'Empresa';
$string['companycnpj'] = 'CUIT/CNPJ';
$string['companycnpj_help'] = 'Opcional. Una persona física puede vender sin identificación fiscal.';
$string['companycommission'] = 'Comisión (%)';
$string['companycommission_help'] = 'Porcentaje que la plataforma retiene sobre las ventas de esta empresa, de 0 a 100. Dejalo vacío para usar el valor por defecto del sitio — vacío y cero son distintos: vacío significa que no se negoció nada, cero significa que el socio está exento.';
$string['companycommissionbase'] = 'Base de la comisión';
$string['companycommissionbase_help'] = 'Solo se lee cuando hay una comisión negociada arriba. Déjelo heredando para que la empresa siga la política del sitio.';
$string['companycreated'] = 'Empresa {$a} creada. Agregá abajo los demás vendedores.';
$string['companyhostname'] = 'Dominio propio';
$string['companyhostname_help'] = 'Opcional. El dominio propio de la empresa, sin el esquema, por ejemplo <b>cursos.socio.com</b>. Tiene que apuntar a este servidor, y el certificado es responsabilidad de quien opera el DNS. Dejarlo vacío mantiene la empresa en el dominio de la plataforma.';
$string['companyname'] = 'Nombre';
$string['companyowner'] = 'Responsable';
$string['companyowner_help'] = 'Quien administra la empresa y vincula su cuenta de Mercado Pago. Debe tener cuenta en la plataforma.';
$string['companypanel'] = 'Marketplace: panel de la empresa';
$string['companyshortname'] = 'Nombre corto';
$string['companyshortname_help'] = 'Se usa en la URL de la empresa. Solo letras, números y guiones.';
$string['companystatus'] = 'Estado';
$string['companytheme'] = 'Tema';
$string['companyupdated'] = 'Empresa {$a} actualizada.';
$string['configurepayment'] = 'Configurar Mercado Pago';
$string['createcompany'] = 'Crear empresa';
$string['createcompanyintro'] = 'Crear una empresa aprovisiona una categoría de cursos, asigna el rol de vendedor al responsable en esa categoría y crea una cuenta de pago. Cerrá la alianza antes — esta pantalla solo la ejecuta.';
$string['currentplan'] = 'Plan actual: {$a}';
$string['defaultcountry'] = 'País por defecto';
$string['defaultcountry_desc'] = 'País donde se aprovisiona la cuenta de pago de una empresa nueva. No es un límite: la empresa puede recibir cuentas en otros países después, y es la oferta la que dice dónde vende cada plan.';
$string['defaultfeepercent'] = 'Comisión por defecto (%)';
$string['defaultfeepercent_desc'] = 'Porcentaje que retiene la plataforma cuando ni el curso ni la empresa tienen una tarifa negociada. Una empresa en 0% sigue en 0%: un campo vacío significa "hereda este valor", que no es lo mismo que "sin comisión".';
$string['defaultthemename'] = 'Tema por defecto del sitio';
$string['domainsuspendedbody'] = 'La empresa responsable de esta dirección no está vendiendo en este momento. Si ya compraste un curso, podés seguir accediendo desde la plataforma principal.';
$string['domainsuspendedtitle'] = 'Esta tienda no está disponible';
$string['editcompanyintro'] = 'El nombre corto no se puede cambiar: es el número de identificación de la categoría y aparece en enlaces de la vidriera y del panel que el vendedor puede haber compartido. El responsable se cambia en la pantalla de vendedores, donde podés promover a otra persona primero.';
$string['editplan'] = 'Editar plan';
$string['erroraccessdays'] = 'Ingresá al menos un día de acceso.';
$string['erroraccounttaken'] = 'Esta cuenta de pago ya está vinculada a otra empresa o a otro país.';
$string['erroralreadymember'] = 'Esta persona ya es vendedora de esta empresa.';
$string['errorbillingdays'] = 'Ingresá el intervalo de cobro en días.';
$string['errorcannotremoveowner'] = 'El responsable no se puede quitar. Promové a otra persona primero — una empresa sin responsable queda sin nadie a cargo de su cuenta de pago.';
$string['errorcannotsell'] = 'Esta empresa todavía no puede vender: configurá primero un medio de pago.';
$string['errorcnpjinvalid'] = 'Este CNPJ no es válido.';
$string['errorcommissionrange'] = 'Usá un número de 0 a 100, o dejalo vacío para heredar el valor del sitio.';
$string['errorcountryunsupported'] = 'El marketplace no opera en el país {$a}.';
$string['errordomainmap'] = 'No se pudo escribir el mapa de dominios de los vendedores. Verifica que el directorio de datos de Moodle tenga permiso de escritura.';
$string['errorhostnametaken'] = 'Este dominio ya está vinculado a otra empresa.';
$string['errormaxcycles'] = 'Usá cero para no poner límite, o un número positivo.';
$string['errornoaccount'] = 'Esta empresa no tiene cuenta de pago. Reinstalá o volvé a crear la empresa.';
$string['errornocourses'] = 'Elegí al menos un curso, o usá el tipo Catálogo completo.';
$string['errorpageaccent'] = 'Usá un color hexadecimal como #B85410, o dejalo vacío.';
$string['errorplanarchived'] = 'Este plan está archivado y no puede asignarse a una empresa.';
$string['errorplanfeenegative'] = 'La cuota mensual no puede ser negativa.';
$string['errorplannotbillable'] = 'Esta empresa no tiene un plan con cuota mensual para cobrar.';
$string['errorplannotfound'] = 'El plan seleccionado no existe.';
$string['errorplanshortnametaken'] = 'Otro plan ya usa este nombre corto.';
$string['errorplantiernegative'] = 'El tope de precio no puede ser negativo.';
$string['errorplatformhostingunavailable'] = 'Alojar video en la plataforma todavía no está disponible.';
$string['errorrecurringfree'] = 'Una suscripción necesita precio. Gratuita, vencería sin forma de renovar.';
$string['errorsellerrolemissing'] = 'Falta el rol de vendedor. Reinstalá el plugin Marketplace.';
$string['errorshortnametaken'] = 'Este nombre corto ya está en uso.';
$string['errorsinglemanycourses'] = 'Una oferta de curso único libera un curso. Usá Combo para más de uno.';
$string['expiringbody'] = 'Hola:

Tu acceso a {$a->offer}, de {$a->company}, termina el {$a->date}.

No hay cobro automático — para mantener el acceso, pagá de nuevo acá:
{$a->url}

Si preferís dejarlo, no hagas nada y el acceso simplemente termina.';
$string['expiringbodyhtml'] = '<p>Hola:</p><p>Tu acceso a <strong>{$a->offer}</strong>, de {$a->company}, termina el <strong>{$a->date}</strong>.</p><p>No hay cobro automático — para mantener el acceso, <a href="{$a->url}">pagá de nuevo acá</a>.</p><p>Si preferís dejarlo, no hagas nada y el acceso simplemente termina.</p>';
$string['expiringlastbody'] = 'Hola:

Este es el último aviso: tu acceso a {$a->offer}, de {$a->company}, termina el {$a->date}.

Después de eso los cursos quedan bloqueados hasta que pagues de nuevo. No se pierde nada — tus notas y tu progreso quedan, y el acceso vuelve apenas entre el pago:
{$a->url}

Si preferís dejarlo, no hagas nada.';
$string['expiringlastbodyhtml'] = '<p>Hola:</p><p>Este es el <strong>último aviso</strong>: tu acceso a <strong>{$a->offer}</strong>, de {$a->company}, termina el <strong>{$a->date}</strong>.</p><p>Después de eso los cursos quedan bloqueados hasta que pagues de nuevo. No se pierde nada — tus notas y tu progreso quedan, y el acceso vuelve apenas entre el pago: <a href="{$a->url}">pagar ahora</a>.</p><p>Si preferís dejarlo, no hagas nada.</p>';
$string['expiringlastsubject'] = 'Último aviso: {$a->offer} se bloquea en {$a->days} día(s)';
$string['expiringline'] = 'Línea digitable del boleto, para copiar: {$a}';
$string['expiringsubject'] = 'Tu acceso a {$a->offer} termina en {$a->days} día(s)';
$string['filterallcategories'] = 'Todas las categorías';
$string['filteralltypes'] = 'Todos los tipos';
$string['filtercategory'] = 'Categoría';
$string['filterclear'] = 'Limpiar filtros';
$string['filtertype'] = 'Tipo';
$string['free'] = 'Gratuito';
$string['getfree'] = 'Obtener acceso gratuito';
$string['hostingbyos'] = 'BYOS: el productor conecta su propio almacenamiento';
$string['hostingexternal'] = 'Fuera de la plataforma';
$string['hostingnative'] = 'Nativa: la plataforma aloja el vídeo';
$string['hostingplatform'] = 'En la plataforma';
$string['hostingtype'] = 'Alojamiento del video';
$string['linkednotenabled'] = 'La cuenta de Mercado Pago está vinculada, pero la pasarela está apagada, así que todavía no se puede vender nada. Abrí la configuración de pago y habilitala.';
$string['makeowner'] = 'Hacer responsable';
$string['makeseller'] = 'Hacer vendedor';
$string['managecourses'] = 'Gestionar cursos';
$string['managemembers'] = 'Vendedores';
$string['managerrole'] = 'Responsable de la empresa';
$string['managerroledesc'] = 'Arma cursos para una empresa y responde por su cuenta de pago y por sus miembros. Igual que el vendedor, no puede subir archivos: los videos del curso deben alojarse fuera de la plataforma.';
$string['marketplace:createcompany'] = 'Crear una empresa';
$string['marketplace:manageall'] = 'Administrar todas las empresas de la plataforma';
$string['marketplace:managecompany'] = 'Administrar la empresa';
$string['marketplace:managepayment'] = 'Administrar la cuenta de pago de la empresa';
$string['marketplace:managesales'] = 'Gestionar las ventas y suscripciones de la empresa';
$string['marketplace:publishcourse'] = 'Publicar cursos para la empresa';
$string['marketplace:refundsale'] = 'Reembolsar una venta, devolviendo el dinero y revocando el acceso';
$string['marketplace:viewreport'] = 'Ver el informe financiero de la empresa';
$string['memberadded'] = 'Vendedor agregado.';
$string['memberowner'] = 'Responsable';
$string['memberremoved'] = 'Vendedor quitado.';
$string['memberrolechanged'] = 'Rol cambiado.';
$string['members'] = 'Vendedores';
$string['memberseller'] = 'Vendedor';
$string['membersof'] = 'Vendedores de {$a}';
$string['messageprovider:expiring'] = 'Acceso a punto de vencer';
$string['modedays'] = 'Plazo fijo';
$string['modelifetime'] = 'De por vida';
$string['moderecurring'] = 'Suscripción';
$string['mysubsactive'] = 'Suscripciones';
$string['mysubscriptions'] = 'Mis suscripciones';
$string['mysubspayments'] = 'Pagos';
$string['nocompanies'] = 'Todavía no hay empresas.';
$string['nocompany'] = 'No pertenecés a ninguna empresa.';
$string['nogatewayforcountry'] = 'Ninguna pasarela de pago instalada puede recibir dinero en este país.';
$string['nomembers'] = 'Esta empresa no tiene vendedores.';
$string['nooffers'] = 'Esta empresa todavía no tiene ofertas publicadas.';
$string['nooffersfiltered'] = 'Ninguna oferta coincide con estos filtros.';
$string['nopaymentaccount'] = 'Esta empresa no tiene medio de pago configurado, así que solo puede publicar cursos gratuitos.';
$string['nopayments'] = 'Todavía no hay pagos.';
$string['noplan'] = 'Sin plan';
$string['noplans'] = 'Todavía no hay planes.';
$string['nosubscriptions'] = 'Todavía no compraste nada.';
$string['offeraccess'] = 'Acceso y cobro';
$string['offeraccessdays'] = 'Días de acceso por pago';
$string['offeraccessdays_help'] = 'Cuánto acceso habilita cada pago. En una suscripción puede superar el intervalo de cobro para dar un período de gracia: cobrar cada 30 días habilitando 35 deja pasar un pago atrasado sin cortarle el acceso al estudiante.';
$string['offeraccessmode'] = 'Modelo de acceso';
$string['offeraccessmode_help'] = 'De por vida nunca vence. Plazo fijo da una cantidad de días por compra. Suscripción da un período y espera renovación.';
$string['offerbillingdays'] = 'Intervalo de cobro (días)';
$string['offerbillingdays_help'] = 'Cada cuánto se espera el próximo pago. Se usa en el aviso de vencimiento.';
$string['offercountry'] = 'País';
$string['offercountry_help'] = 'Dónde vende esta oferta. Decide qué cuenta recibe el dinero, la moneda y qué pasarelas aparecen en el checkout. Vender en otro país es otra oferta, y no otro precio en esta: el subsistema de pago resuelve importe, moneda y cuenta a partir de la oferta, sin saber quién está comprando.';
$string['offercourses'] = 'Cursos que libera';
$string['offercourses_help'] = 'Qué cursos libera esta oferta. No hace falta en Catálogo completo, que sigue la categoría de la empresa.';
$string['offercreate'] = 'Nueva oferta';
$string['offeredit'] = 'Oferta';
$string['offerincludes'] = 'Incluye {$a} curso(s)';
$string['offermaxcycles'] = 'Máximo de pagos';
$string['offermaxcycles_help'] = 'Cuántas veces se puede cobrar esta suscripción en total. Cero significa sin límite. Usá 12 para un plan mensual que dura un año, o 3 para un plan anual que dura tres años.';
$string['offername'] = 'Oferta';
$string['offerprice'] = 'Precio';
$string['offerprice_help'] = 'Cero hace la oferta gratuita. Las ofertas gratuitas no pasan por Mercado Pago.';
$string['offerpublication'] = 'Publicación';
$string['offerrecurringwarning'] = 'Mercado Pago no tiene cobro recurrente con split, así que no se debita nada automáticamente. El estudiante recibe un aviso antes del vencimiento, con enlace para pagar de nuevo.';
$string['offersaved'] = 'Oferta guardada.';
$string['offersortorder'] = 'Orden de exhibición';
$string['offerssection'] = 'Ofertas';
$string['offerstatus_help'] = 'Solo las ofertas publicadas aparecen en la vidriera. Archivar no revoca el acceso ya comprado.';
$string['offertype'] = 'Tipo';
$string['offertype_help'] = 'Curso único vende un curso. Combo vende un conjunto elegido — así se arman planes por nivel como Básico, Intermedio y Completo para la misma empresa. Catálogo completo sigue la categoría de la empresa, así que los cursos nuevos entran solos.';
$string['offerunlocks'] = 'Esta es la oferta que libera el contenido que estabas viendo.';
$string['pageaccent'] = 'Color de acento';
$string['pageaccent_help'] = 'Hexadecimal, como #B85410. Colorea los botones de compra y queda disponible para tu CSS en la variable --mp-accent.';
$string['pagecss'] = 'Hoja de estilos propia';
$string['pagecss_help'] = 'Un archivo .css que se carga después del tema, así que puede sobrescribirlo. Se sirve como hoja de estilos, nunca embebido — el navegador nunca lo trata como script. Para una página totalmente propia, construila donde quieras y leé las ofertas por la API.';
$string['pageintro'] = 'Texto de apertura';
$string['pageintro_help'] = 'Aparece arriba de las ofertas. Se escribe como texto enriquecido y Moodle lo filtra — es texto de venta, no lugar para scripts.';
$string['pagelogo'] = 'Logo de la marca';
$string['pagelogo_help'] = 'Aparece arriba de tu vidriera. Una imagen web — PNG o SVG con fondo transparente funciona mejor. Se muestra con hasta 96px de alto.';
$string['pagesection'] = 'Vidriera';
$string['pagetitle'] = 'Título de la vidriera';
$string['pagetitle_help'] = 'Aparece como título de la página. Vacío usa el nombre de la empresa.';
$string['payinvoice'] = 'Pagar este ciclo';
$string['paymentsection'] = 'Medio de pago';
$string['paysubscription'] = 'Pagar suscripción';
$string['plan'] = 'Plan';
$string['plancommissionbase'] = 'Base de la comisión';
$string['plancommissionbase_help'] = 'Sobre qué se aplica el porcentaje de este plan. Déjelo heredando, salvo que el plan venda un acuerdo distinto del resto de la plataforma. La base es parte de lo que el socio contrató, y cambiarla después no afecta las ventas ya realizadas.';
$string['plancommissionpct'] = 'Comisión (%)';
$string['plancommissionpct_help'] = 'Se aplica a las empresas de este plan que no tienen una comisión negociada de forma individual. Un valor negociado en la empresa siempre gana sobre el del plan.';
$string['plancountry'] = 'País';
$string['plandescription'] = 'Descripción';
$string['planexpiryon'] = 'Suscripción pagada hasta {$a}';
$string['planhostingmodel'] = 'Modelo de alojamiento';
$string['planispublic'] = 'Mostrar en la comparación pública de planes';
$string['planmonthlyfee'] = 'Cuota mensual';
$string['planmonthlyfee_help'] = 'Se cobra automáticamente, cada 30 días, desde el panel de la empresa — vea "Pagar suscripción" en la página de la empresa.';
$string['planname'] = 'Nombre';
$string['plannotpaidyet'] = 'Todavía no pagada.';
$string['planprodesc'] = 'Para quien ya vende, aporta su propio almacenamiento y paga menos comisión.';
$string['planproname'] = 'Pro';
$string['plans'] = 'Planes';
$string['planscaledesc'] = 'Para operación a escala: sin comisión, almacenamiento propio y soporte prioritario.';
$string['planscalename'] = 'Scale';
$string['planshortname'] = 'Nombre corto';
$string['planshortname_help'] = 'Clave estable usada en el código y por la semilla de instalación. A diferencia del nombre, no está pensada para cambiar con el marketing.';
$string['plansortorder'] = 'Orden de visualización';
$string['planssection'] = 'Plan';
$string['planstart100desc'] = 'Alojamiento en la plataforma, calidad de vídeo máxima (4K), 10% de comisión por venta.';
$string['planstart100name'] = 'Start — R$100/mes';
$string['planstart50desc'] = 'Alojamiento en la plataforma, calidad de vídeo mayor (1080p), 10% de comisión por venta.';
$string['planstart50name'] = 'Start — R$50/mes';
$string['planstarterdesc'] = 'Cuota mensual cero: solo paga cuando vende. Alojamiento de vídeo incluido, con tope de resolución según el precio del curso.';
$string['planstartername'] = 'Starter';
$string['planstartfreedesc'] = 'Cuota mensual cero, alojamiento en la plataforma a 720p, 10% de comisión por venta. La puerta de entrada — se puede subir de nivel en cualquier momento para mejorar la calidad del vídeo.';
$string['planstartfreename'] = 'Start — Gratis';
$string['planstatus'] = 'Estado';
$string['planstatusactive'] = 'Activo';
$string['planstatusarchived'] = 'Archivado';
$string['plantiermaxprice'] = 'Tope de precio';
$string['plantiermaxresolution'] = 'Resolución máxima';
$string['plantiernolimit'] = 'Sin tope';
$string['plantiers'] = 'Topes de resolución por precio del curso';
$string['plantiers_help'] = 'Cada fila limita la resolución del vídeo para cursos hasta el precio indicado. La última fila, con el precio vacío, cubre todo lo que esté por encima. Solo tiene sentido cuando el ancho de banda lo paga la plataforma.';
$string['platformaccountname'] = 'Plataforma ({$a})';
$string['pluginname'] = 'Marketplace';
$string['privacy:metadata'] = 'El plugin Marketplace guarda empresas, sus vendedores y credenciales de pago.';
$string['privacy:metadata:entitlement'] = 'Qué compró el estudiante y por cuánto tiempo vale su acceso.';
$string['privacy:metadata:entitlement:companyid'] = 'La empresa que vendió.';
$string['privacy:metadata:entitlement:cycles'] = 'Cuántos pagos se hicieron.';
$string['privacy:metadata:entitlement:offerid'] = 'La oferta comprada.';
$string['privacy:metadata:entitlement:status'] = 'Si el acceso está vigente, vencido o revocado.';
$string['privacy:metadata:entitlement:timeend'] = 'Cuándo termina el acceso. Cero significa que no vence.';
$string['privacy:metadata:entitlement:timestart'] = 'Cuándo empezó el acceso.';
$string['privacy:metadata:entitlement:userid'] = 'El estudiante.';
$string['privacy:metadata:member'] = 'Para qué empresas vende la persona.';
$string['privacy:metadata:member:companyid'] = 'La empresa.';
$string['privacy:metadata:member:memberrole'] = 'Si responde por la empresa o vende para ella.';
$string['privacy:metadata:member:timecreated'] = 'Cuándo se hizo el vínculo.';
$string['privacy:metadata:member:userid'] = 'La persona vinculada a la empresa.';
$string['refundconfirm'] = '¿Reembolsar {$a->amount} a {$a->user}, por {$a->offer}?<br><br>El dinero vuelve por la pasarela, la comisión se cancela con él, y el estudiante <strong>pierde el acceso de inmediato</strong>. Si este es el primer cobro de una suscripción, la suscripción también se cancela. No hay forma de deshacer.';
$string['refunddone'] = 'Venta reembolsada y acceso revocado.';
$string['refundfailed'] = 'La pasarela no aceptó el reembolso. Nada fue cambiado acá.';
$string['refundsale'] = 'Reembolsar';
$string['renewnotice'] = 'Tu acceso termina el {$a}. Renovalo para mantenerlo.';
$string['renewnow'] = 'Renovar ahora';
$string['reportaccessuntil'] = 'Acceso hasta';
$string['reportall'] = 'Todo el período';
$string['reportcommission'] = 'Comisión de la plataforma';
$string['reportcommissionterms'] = 'Términos aplicados';
$string['reportcoursesnotice'] = 'Un combo cuenta entero para cada curso que libera — nadie compra un tercio de un combo. Por eso esta columna suma más que tu facturación total, y sirve para comparar cursos entre sí, no para sumar.';
$string['reportdays'] = 'Últimos {$a} días';
$string['reportentries'] = 'Ventas';
$string['reportexternalid'] = 'Transacción en la pasarela';
$string['reportgateway'] = 'Pasarela de pago';
$string['reportgross'] = 'Bruto';
$string['reportlastpayment'] = 'Último pago';
$string['reportnetnotice'] = 'La comisión de Mercado Pago no aparece acá porque no se nos informa: varía según el medio de pago y el plazo de acreditación, y se descuenta de su lado antes de la comisión de la plataforma. Tu monto neto es el del resumen de Mercado Pago.';
$string['reportnocourses'] = 'Todavía no se vendió ningún curso.';
$string['reportnosales'] = 'No hay ventas aprobadas en este período.';
$string['reportnostudents'] = 'Todavía nadie tiene acceso a las ofertas de esta empresa.';
$string['reportnosubs'] = 'No hay ofertas de suscripción, o todavía nadie se suscribió.';
$string['reportpaymentmethod'] = 'Forma de pago';
$string['reportpayments'] = 'Pagos';
$string['reportsales'] = 'Ventas aprobadas';
$string['reportsaleswith'] = 'Ventas que lo incluyen';
$string['reportsection'] = 'Ventas';
$string['reportstudentsactive'] = 'Estudiantes con acceso vigente';
$string['reportstudentsince'] = 'Desde';
$string['reportstudentsnotice'] = 'Una línea por derecho de acceso, así que un estudiante que compró tres ofertas aparece tres veces. El conteo de arriba es de personas distintas. La lista sale de los derechos de acceso y no de las ventas, así que quien tomó una oferta gratuita también cuenta como estudiante.';
$string['reportstudentsrows'] = 'Derechos de acceso';
$string['reportsubactive'] = 'Vigente';
$string['reportsubcancelled'] = 'Cancelada';
$string['reportsubduesoon'] = 'Vence en {$a} d';
$string['reportsubexpired'] = 'Vencida';
$string['reportsubnorenew'] = 'renovación cancelada';
$string['reportsubsnotice'] = 'No hay cronograma de cobro para mostrar: Mercado Pago no tiene pagos recurrentes con split, así que cada renovación es una compra separada que extiende el acceso. Esta pantalla muestra cuántas veces pagó cada estudiante y por cuánto tiempo vale todavía su acceso.';
$string['reportviewcourses'] = 'Cursos vendidos';
$string['reportviewstudents'] = 'Estudiantes';
$string['reportviewsubscriptions'] = 'Suscripciones';
$string['reportviewtransactions'] = 'Transacciones';
$string['resenddone'] = 'El cobro fue reenviado al estudiante.';
$string['resendfailed'] = 'No se envió nada — el estudiante puede estar suspendido, o la oferta salió de venta.';
$string['resendinvoice'] = 'Reenviar cobro';
$string['selectplan'] = 'Seleccionar plan';
$string['sellerrole'] = 'Vendedor de la empresa';
$string['sellerroledesc'] = 'Arma y publica cursos para una empresa. No alcanza la cuenta de pago ni la lista de miembros, y no puede subir archivos: los videos del curso deben alojarse fuera de la plataforma.';
$string['settings'] = 'Configuración';
$string['sortby'] = 'Ordenar por';
$string['sortmanual'] = 'Destacados';
$string['sortname'] = 'Nombre';
$string['sortnewest'] = 'Lanzamientos';
$string['sortprice'] = 'Precio: de menor a mayor';
$string['sortpricedesc'] = 'Precio: de mayor a menor';
$string['statusactive'] = 'Activa';
$string['statusarchived'] = 'Archivada';
$string['statusdraft'] = 'Borrador';
$string['statuspublished'] = 'Publicada';
$string['statussuspended'] = 'Suspendida';
$string['switchtocard'] = 'Cambiar a tarjeta';
$string['tasknotifyexpiring'] = 'Avisar a los estudiantes sobre accesos por vencer';
$string['typebundle'] = 'Combo';
$string['typecatalog'] = 'Catálogo completo';
$string['typesingle'] = 'Curso único';
$string['unavailable'] = 'Todavía no está disponible para comprar.';
$string['viewstorefront'] = 'Ver vidriera';
