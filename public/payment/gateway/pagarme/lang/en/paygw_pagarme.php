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
 * Strings for paygw_pagarme.
 *
 * @package    paygw_pagarme
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'Secret key';
$string['apikey_help'] = 'The seller\'s secret key, starting with sk_. It is stored encrypted and never shown again.';
$string['billingcity'] = 'City';
$string['billingline1'] = 'Street, number and district';
$string['billingstate'] = 'State (two letters)';
$string['billingzipcode'] = 'Postcode';
$string['boletoinstructions'] = 'Payment for course access';
$string['cardcvv'] = 'Security code';
$string['cardexpiry'] = 'Expiry date (MM/YY)';
$string['cardheading'] = 'Card details';
$string['cardholder'] = 'Name on card';
$string['cardintro'] = 'Your card details are sent straight to Pagar.me and never reach this site.';
$string['cardnumber'] = 'Card number';
$string['cardsubmit'] = 'Pay';
$string['chargeheading'] = 'Charges';
$string['defaultdescription'] = 'Course access';
$string['documentfield'] = 'Profile field with the CPF';
$string['documentfield_desc'] = 'Short name of the user profile field holding the buyer\'s CPF or CNPJ. Pagar.me requires it.';
$string['duedays'] = 'Days until the boleto expires';
$string['duedays_desc'] = 'How long the buyer has to pay a boleto.';
$string['environment'] = 'Environment';
$string['environment_desc'] = 'Which environment the platform is charging in.';
$string['environmentheading'] = 'Environment and webhook';
$string['environmentheading_desc'] = 'Register this address in the Pagar.me panel: <code>{$a}</code>';
$string['environmentproduction'] = 'Production';
$string['environmentsandbox'] = 'Sandbox';
$string['errorapi'] = 'Pagar.me refused the request: {$a}';
$string['errorchargefailed'] = 'The charge was created but failed at the acquirer: {$a}';
$string['errorchargemissing'] = 'Pagar.me confirmed the order but returned no charge to collect.';
$string['errorcreatingcharge'] = 'The charge could not be created. Please try again in a moment.';
$string['errorcurl'] = 'Could not reach Pagar.me: {$a}';
$string['errorinvalidresponse'] = 'Pagar.me returned something that is not a valid response.';
$string['errorkeyenvironment'] = 'That key belongs to the other environment. Keys starting with sk_test_ are sandbox.';
$string['errorkeyrejected'] = 'Pagar.me rejected this key.';
$string['errornodocument'] = 'Your profile has no CPF. Add it before buying.';
$string['errornoencryptionkey'] = 'This site has no encryption key. Run admin/cli/generate_key.php before linking an account.';
$string['errornophone'] = 'Your profile has no phone number. Add one before buying — Pagar.me requires it.';
$string['errornotlinked'] = 'No Pagar.me account is linked for {$a}.';
$string['errorrecipientrejected'] = 'Pagar.me does not recognise that recipient in this account.';
$string['errorrecurringunsupported'] = 'Pagar.me cannot split a subscription yet, so a recurring offer would earn no commission. Use a one-off offer, or ask Pagar.me to enable split on subscriptions for this account.';
$string['errorrefundalready'] = 'This charge has already been refunded.';
$string['errorrefundmethod'] = 'Pagar.me does not refund this payment method.';
$string['errorrefundnotfirstcycle'] = 'Only the first cycle of a subscription can be refunded.';
$string['errorrefundnotpaid'] = 'This charge was never paid, so there is nothing to refund.';
$string['errorrefundunknown'] = 'This sale cannot be refunded automatically.';
$string['errorsamerecipient'] = 'The platform recipient and the seller recipient are the same. Nothing would be split.';
$string['gatewaydescription'] = 'Pay with Pix, boleto or card.';
$string['gatewayname'] = 'Pagar.me';
$string['link'] = 'Link account';
$string['linkdone'] = 'Account linked.';
$string['linkdonenosplit'] = 'Account linked, but Pagar.me has no default recipient for it yet - charges will not split commission until the seller finishes their Pagar.me registration and you link again.';
$string['linkedas'] = 'Linked to {$a->account}, key ending in {$a->tail}.';
$string['linkheading'] = 'Link a Pagar.me account';
$string['linkintro'] = 'Paste the seller\'s secret key and the platform\'s recipient id inside that account. Both are checked against the API before the gateway is enabled.';
$string['linkstatus'] = 'Link status';
$string['methodboleto'] = 'Boleto';
$string['methodcreditcard'] = 'Credit card';
$string['methodpix'] = 'Pix';
$string['notlinked'] = 'No account linked for {$a}.';
$string['paymentmethod'] = 'Payment method';
$string['paymentmethod_desc'] = 'Which method the buyer is charged with.';
$string['pixcopied'] = 'Copied.';
$string['pixcopy'] = 'Copy the Pix code';
$string['pixexpiresin'] = 'QR code validity, in minutes';
$string['pixexpiresin_desc'] = 'Pagar.me only accepts a value between 15 and 60 minutes.';
$string['pixheading'] = 'Pay with Pix';
$string['pixinstructions'] = 'Scan the QR code with your bank app, or copy the code below. Access is released as soon as the payment clears.';
$string['pixpaid'] = 'Payment confirmed. Taking you to the course.';
$string['pixwaiting'] = 'Waiting for the payment...';
$string['platformrecipient'] = 'Platform recipient id';
$string['platformrecipient_help'] = 'The rp_ id of the platform inside this seller\'s Pagar.me account. It is different for every seller, because a recipient belongs to one account.';
$string['pluginname'] = 'Pagar.me';
$string['pluginname_desc'] = 'Charges through Pagar.me, with the commission split to the platform.';
$string['privacy:metadata:pagarme'] = 'Buyer data sent to Pagar.me so the charge can be issued.';
$string['privacy:metadata:pagarme:document'] = 'The buyer\'s CPF or CNPJ, required by Pagar.me.';
$string['privacy:metadata:pagarme:email'] = 'The buyer\'s email address.';
$string['privacy:metadata:pagarme:name'] = 'The buyer\'s full name.';
$string['privacy:metadata:pagarme:value'] = 'The amount charged.';
$string['privacy:metadata:paygw_pagarme'] = 'Pagar.me charges issued by this site.';
$string['privacy:metadata:paygw_pagarme:amount'] = 'The amount of the charge.';
$string['privacy:metadata:paygw_pagarme:chargeid'] = 'The charge identifier at Pagar.me.';
$string['privacy:metadata:paygw_pagarme:currency'] = 'The currency of the charge.';
$string['privacy:metadata:paygw_pagarme:customerid'] = 'The buyer identifier in the seller\'s Pagar.me account.';
$string['privacy:metadata:paygw_pagarme:status'] = 'The status of the charge.';
$string['privacy:metadata:paygw_pagarme:timecreated'] = 'When the charge was created.';
$string['privacy:metadata:paygw_pagarme:userid'] = 'The user who made the purchase.';
$string['publickey'] = 'Public key';
$string['publickey_desc'] = 'The pk_ key, used by the browser to turn card details into a token. It is not a secret.';
$string['relink'] = 'Replace key';
$string['returnheading'] = 'Your payment';
$string['returnpending'] = 'We have not been told about this payment yet. As soon as it clears, your access is released — you do not need to pay again.';
$string['returnrefunded'] = 'This payment was refunded.';
$string['savebeforelinking'] = 'Save the payment account before linking a Pagar.me account to it.';
$string['sellerrecipient'] = 'Seller recipient id';
$string['taskreconcile'] = 'Check pending Pagar.me charges';
$string['unlink'] = 'Unlink';
$string['unlinkconfirm'] = 'Unlink the Pagar.me account for {$a}? Charges already made stay as they are.';
$string['unlinkdone'] = 'Account unlinked.';
$string['unlinknotice'] = 'The other environment stays linked.';
$string['webhookpassword'] = 'Webhook password';
$string['webhookpassword_desc'] = 'The password Pagar.me sends in HTTP Basic when it calls the webhook. Set it in the Pagar.me panel.';
$string['webhookuser'] = 'Webhook user';
$string['webhookuser_desc'] = 'The user Pagar.me sends in HTTP Basic when it calls the webhook.';
