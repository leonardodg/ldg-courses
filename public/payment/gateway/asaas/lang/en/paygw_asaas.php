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


$string['apikey'] = 'Asaas API key';
$string['apikey_help'] = 'Generate a key in your own Asaas account (Settings > Integrations > API key) and paste it here. The key is checked against Asaas before being saved, and it is stored encrypted: it never appears on screen again. It is your account that issues the charge, keeps the net amount and issues the invoice — the platform only receives its commission through the split.';
$string['billingboleto'] = 'Bank slip only';
$string['billingcreditcard'] = 'Credit card only';
$string['billingpix'] = 'Pix only';
$string['billingtype'] = 'Payment method';
$string['billingtype_desc'] = '"Let the buyer choose" opens the Asaas invoice with every method enabled on the seller\'s account. Forcing one method is useful while testing a single flow.';
$string['billingundefined'] = 'Let the buyer choose';
$string['chargeheading'] = 'Charge';
$string['defaultdescription'] = 'Course purchase';
$string['documentfield'] = 'Profile field with the buyer\'s document';
$string['documentfield_desc'] = 'Short name of a custom profile field holding the buyer\'s CPF or CNPJ. <strong>Required:</strong> Asaas will create the customer without a document but refuses to issue the charge, so leaving this empty makes every purchase fail at the last step. Make the field required at signup too.';
$string['duedays'] = 'Days until due';
$string['duedays_desc'] = 'How long the charge stays payable. A Pix charge is usually paid in minutes; the window exists for bank slips.';
$string['environment'] = 'Environment';
$string['environment_desc'] = 'Which environment the platform is using right now. Sandbox and production credentials are stored side by side, so switching does not require retyping anything — and a sandbox key can never be used in production by accident.';
$string['environmentactive'] = 'in use';
$string['environmentheading'] = 'Asaas application';
$string['environmentheading_desc'] = 'Register this URL under <strong>Integrations &gt; Webhooks</strong> at Asaas — the charge webhook — with API version 3, sequential delivery, and only the events <code>PAYMENT_RECEIVED</code> and <code>PAYMENT_CONFIRMED</code>: <code>{$a}</code><br>Put the token below in the webhook\'s authentication field; an empty token there means every delivery is refused. Not to be confused with <em>Integrations &gt; Security Mechanisms</em>, which validates money <em>leaving</em> the account: pointing that one here would leave the seller\'s own withdrawals hostage to this site being up.';
$string['environmentproduction'] = 'Production';
$string['environmentsandbox'] = 'Sandbox';
$string['errorapi'] = 'Asaas refused the request: {$a}';
$string['errorcreatingcharge'] = 'The charge could not be created. Try again in a moment.';
$string['errorcurl'] = 'Could not reach Asaas: {$a}';
$string['errorinvalidresponse'] = 'Asaas returned an unexpected response.';
$string['errorkeyenvironment'] = 'This key belongs to {$a->key}, but you are linking {$a->chosen}.';
$string['errorkeyformat'] = 'This does not look like a valid Asaas API key. Check for a copy-paste mistake.';
$string['errorkeyrejected'] = 'Asaas rejected this key: {$a}';
$string['errornodocument'] = 'This purchase needs the buyer\'s CPF or CNPJ: Asaas refuses to issue a charge for a customer without one. Set the profile field that holds it in the gateway settings, and make that field required at signup.';
$string['errornoencryptionkey'] = 'This site has no encryption key, so a seller\'s API key cannot be stored safely. Create one with admin/cli/generate_key.php before linking any account.';
$string['errornosite'] = 'Asaas requires the return URL to use <strong>the same domain registered in the account that issues the charge</strong>. This site is <code>{$a->expected}</code>, and the account has <code>{$a->found}</code>. Register <code>{$a->expected}</code> under My Account &gt; Information at Asaas — not the seller\'s own site, which is what one would register by instinct. Or turn off "Send the buyer back" in the gateway settings.';
$string['errornotlinked'] = 'No Asaas account is linked for {$a}.';
$string['errornowallet'] = 'The key is valid but the account has no wallet, so it cannot take part in a split.';
$string['errorrefundalready'] = 'This sale has already been refunded.';
$string['errorrefundbillingtype'] = 'Asaas only refunds card and Pix charges. A bank slip cannot be refunded — not even after a manual settlement. Return the money outside the platform and cancel the subscription here.';
$string['errorrefundnotfirstcycle'] = 'Only the first payment of a subscription can be refunded. From the second cycle on, cancel the subscription instead — the learner used the previous months.';
$string['errorrefundnotpaid'] = 'Only a confirmed or received charge can be refunded. Right after payment Asaas needs about half a minute before it allows a refund.';
$string['errorrefundunknown'] = 'No Asaas charge was found for this payment.';
$string['errorsamewallet'] = 'This is the platform\'s own wallet. Asaas refuses a split to the wallet that created the charge, so the seller needs a different account.';
$string['errorunknownenvironment'] = 'Unknown environment.';
$string['gatewaydescription'] = 'Charge by Pix, bank slip or card, with the commission split automatically.';
$string['gatewayname'] = 'Asaas';
$string['link'] = 'Link account';
$string['linkdone'] = 'Asaas account linked for {$a}.';
$string['linkedas'] = '{$a->name} · wallet {$a->wallet} · key ending in {$a->tail}';
$string['linkheading'] = 'Link an Asaas account';
$string['linkintro'] = 'The charge is created with this account\'s key, so the money lands in it and it is the one that issues the invoice. The platform receives only its commission, through the split.';
$string['linkstatus'] = 'Asaas account';
$string['notlinked'] = 'Not linked.';
$string['platformwalletid'] = 'Platform wallet ID';
$string['platformwalletid_desc'] = 'The wallet that receives the commission — the platform\'s, not the seller\'s. Find it in the Asaas panel or through GET /wallets.';
$string['pluginname'] = 'Asaas';
$string['pluginname_desc'] = 'Receive through Asaas with payment split: the seller\'s account issues the charge and keeps the net amount, and the platform\'s wallet receives the commission.';
$string['privacy:metadata:asaas'] = 'Buyer data sent to Asaas so the charge can be issued.';
$string['privacy:metadata:asaas:cpfcnpj'] = 'Buyer document, when a profile field is configured.';
$string['privacy:metadata:asaas:email'] = 'Buyer e-mail.';
$string['privacy:metadata:asaas:name'] = 'Buyer full name.';
$string['privacy:metadata:asaas:value'] = 'Charge amount.';
$string['privacy:metadata:paygw_asaas'] = 'Asaas transactions.';
$string['privacy:metadata:paygw_asaas:amount'] = 'Amount charged.';
$string['privacy:metadata:paygw_asaas:asaaspaymentid'] = 'Charge identifier at Asaas.';
$string['privacy:metadata:paygw_asaas:currency'] = 'Currency.';
$string['privacy:metadata:paygw_asaas:customerid'] = 'Customer identifier at Asaas.';
$string['privacy:metadata:paygw_asaas:status'] = 'Charge status.';
$string['privacy:metadata:paygw_asaas:timecreated'] = 'When the charge was created.';
$string['privacy:metadata:paygw_asaas:userid'] = 'The user who made the purchase.';
$string['relink'] = 'Replace key';
$string['returnheading'] = 'Payment';
$string['returnpending'] = 'We have not been told yet that the payment cleared. With Pix this takes a few seconds; with a bank slip it can take until the next business day. As soon as it clears, your access is released automatically — you do not need to stay on this page.';
$string['returnrefunded'] = 'This payment was refunded, so access was not released.';
$string['savebeforelinking'] = 'Save this account first, then link the Asaas account.';
$string['taskreconcile'] = 'Check pending Asaas charges';
$string['unlink'] = 'Unlink';
$string['unlinkconfirm'] = 'Unlink the Asaas account for {$a}? Charges already issued keep working, and the other environment is untouched.';
$string['unlinkdone'] = 'Asaas account unlinked for {$a}.';
$string['unlinknotice'] = 'This only removes the link here. The key stays valid in the Asaas panel — revoke it there if that is what you want.';
$string['usecallback'] = 'Send the buyer back';
$string['usecallback_desc'] = 'After paying, bring the buyer back to Moodle automatically. Asaas only accepts a return URL from an account that has a website registered — without one it rejects the whole charge, not just the return. Turn this off if a seller cannot register a domain: they keep selling, and the buyer returns through the invoice instead.';
$string['webhooktoken'] = 'Webhook token';
$string['webhooktoken_desc'] = 'A secret you invent and paste into the authentication field when registering the webhook at Asaas. It is checked on every notification. While it is empty the webhook refuses everything: an endpoint left open "until someone configures it" is exactly the window an attacker wants.';
