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
 * Strings for paygw_mercadopago.
 *
 * @package    paygw_mercadopago
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['appheading'] = 'Platform applications';
$string['appheading_desc'] = 'Mercado Pago applications that belong to the platform, not to the seller. Each integration type is a separate application with its own credentials, and each one needs its own authorisation from every seller.<br><br>Register these exact URLs in all of them:<br>Redirect URI: <code>{$a->callback}</code><br>Webhook: <code>{$a->webhook}</code>';
$string['apptype_bricks'] = 'Checkout Bricks';
$string['apptype_bricks_desc'] = 'Declare the integration model as <strong>Checkout Bricks</strong> and subscribe to the <code>payment</code> event. This is the application that charges subscription cycles: the commission travels as <code>application_fee</code> on the payment, and the card is tokenised by Mercado Pago\'s own component, so card numbers never reach this server.';
$string['apptype_preferences'] = 'Preferences API (Checkout Pro)';
$string['apptype_preferences_desc'] = 'Declare the integration model as <strong>Preferences API</strong> and subscribe to the <code>payment</code> event. Used for one-off sales: the commission travels as <code>marketplace_fee</code> on the preference. Declaring the wrong model makes that field be ignored with no error at all.';
$string['apptype_subscriptions'] = 'Subscriptions';
$string['apptype_subscriptions_desc'] = 'Declare the integration model as <strong>Subscriptions</strong> and subscribe to the <code>subscription_preapproval</code> and <code>subscription_authorized_payment</code> events. Use it only for subscriptions with no commission: measured on 2026-09-15, <code>POST /preapproval</code> accepts every known fee field, answers 201, and silently discards all of them.';
$string['cardcapture'] = 'Where the card is typed';
$string['cardcapture_desc'] = 'The payment page is ours in all three modes, with our own layout. What changes is the path the card number takes — and with it where this project falls under PCI DSS. Full detail in docs/legal/pci-dss-captura-de-cartao.md.';
$string['cardcaptureblocked'] = 'This site is not served over HTTPS, so the page collecting the card could be rewritten in transit. The option below is being IGNORED and the Mercado Pago fields are in use. No configuration compensates for missing TLS.';
$string['cardcapturebrick'] = 'Mercado Pago fields inside our page — the number never reaches us (SAQ {$a->scope})';
$string['cardcapturedirect'] = 'Our own form, browser sends the card straight to Mercado Pago — never through our backend (SAQ {$a->scope})';
$string['cardcapturenative'] = 'Our own form, card number through our backend (SAQ {$a->scope})';
$string['cardexpiration'] = 'Expiry date';
$string['cardholder'] = 'Name on the card';
$string['cardholderdoc'] = 'Cardholder ID number (CPF)';
$string['cardmonth'] = 'Month';
$string['cardnumber'] = 'Card number';
$string['cardsecuritycode'] = 'Security code';
$string['cardyear'] = 'Year';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Application number shown in the Mercado Pago developer panel.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Used only to exchange the authorisation code for the seller token. Never sent to the browser.';
$string['commonheading'] = 'Settings shared by every application';
$string['commonheading_desc'] = 'These are properties of the marketplace as a whole, not of one integration. Having them per application would allow exactly the mixes Mercado Pago refuses.';
$string['confirmcycleamount'] = 'Confirm the {$a} charge for this cycle.';
$string['confirmcyclecancel'] = 'Not now — go back to my subscriptions';
$string['confirmcycleexplain'] = 'This account cannot charge your card automatically. Enter the security code to confirm — it is sent straight to Mercado Pago and never stored.';
$string['confirmcycletitle'] = 'Confirm subscription cycle';
$string['errorapi'] = 'Mercado Pago rejected the request. {$a}';
$string['errorapistep'] = 'Mercado Pago rejected the request at step "{$a->step}". {$a->message}';
$string['errorauthorisationrefused'] = 'The authorisation was not completed at Mercado Pago. Nothing was linked; you can try again.';
$string['errorcardtokenmissing'] = 'The card could not be read. Check the details and try again.';
$string['errorcreatingpreference'] = 'Could not start the payment. Try again in a moment.';
$string['errorcurl'] = 'Could not reach Mercado Pago: {$a}';
$string['errorcustomer'] = 'Mercado Pago could not create the customer for this subscription.';
$string['errorinvalidresponse'] = 'Mercado Pago returned an unexpected response for {$a}';
$string['errormissingappconfig'] = 'The {$a} application is not configured. Set its client ID and secret in the plugin settings.';
$string['errormissingpublickey'] = 'The {$a} application has no public key configured, so the card fields cannot be built. Set it in the plugin settings.';
$string['errornopendingflow'] = 'There is no authorisation in progress in this browser. That happens when this page is opened directly — for instance to check the redirect URI you have just registered — or when the session ended while you were authorising at Mercado Pago. Start again from the payment account.';
$string['errornotlinked'] = 'Link the Mercado Pago account before enabling this gateway.';
$string['errorrefundalready'] = 'This sale has already been refunded.';
$string['errorrefundnotfirstcycle'] = 'Only the first cycle of a subscription can be refunded. A partial refund does not reduce the commission already passed to the platform, so the seller would absorb it.';
$string['errorrefundnotpaid'] = 'This sale was not paid, so there is nothing to refund.';
$string['errorrefundunknown'] = 'This sale was not found in the Mercado Pago gateway.';
$string['errorsitemismatch'] = 'This marketplace operates in {$a->platform} and the account you authorised is from {$a->seller}. Mercado Pago only splits payments between accounts of the same country, so this account cannot be linked. Use an account from {$a->platform}.';
$string['errorstaleflow'] = 'This authorisation was started before the plugin was updated, so it cannot say which Mercado Pago application it belongs to. Nothing was linked. Start it again.';
$string['errorstatemismatch'] = 'The authorisation could not be verified. Start the process again.';
$string['errorsubscriptionnotfound'] = 'This subscription was not found, or it does not belong to you.';
$string['errorunknownapptype'] = 'Unknown Mercado Pago application type.';
$string['errorverifyaccount'] = 'The account was authorised but could not be verified with Mercado Pago, so it was not linked. Try again. ({$a})';
$string['gatewaydescription'] = 'Pay with Pix, card or bank slip through Mercado Pago Checkout Pro. The amount is split automatically between the seller and the platform.';
$string['gatewayname'] = 'Mercado Pago';
$string['invoicedue_body'] = 'Your subscription has a pending charge of {$a->amount}. Pay it here: {$a->url}';
$string['invoicedue_subject'] = 'Pending subscription charge';
$string['linkaccount'] = 'Link Mercado Pago account';
$string['linkaccounttype'] = 'Link account for {$a}';
$string['messageprovider:invoicedue'] = 'Pending subscription invoice (Pix or bank slip)';
$string['messageprovider:reminderupcoming'] = 'Upcoming subscription renewal (Pix or bank slip)';
$string['oauthcurrency'] = 'Payouts in {$a}.';
$string['oauthexpired'] = 'The authorisation expired. Link the account again.';
$string['oauthlinked'] = 'Linked to Mercado Pago user {$a->mpuserid}. Authorisation valid until {$a->expires}.';
$string['oauthnotlinked'] = 'Not linked yet.';
$string['oauthstatus'] = 'Mercado Pago accounts';
$string['payerboletoaddress'] = 'Billing address (required for bank slip)';
$string['payercity'] = 'City';
$string['payerdoc'] = 'CPF';
$string['payername'] = 'Full name';
$string['payerneighborhood'] = 'Neighbourhood';
$string['payerstate'] = 'State (UF)';
$string['payerstreet'] = 'Street';
$string['payerstreetnumber'] = 'Number';
$string['payerzipcode'] = 'Postcode (CEP)';
$string['paymentapproved'] = 'Payment approved. Enjoy your course!';
$string['paymentpending'] = 'We are waiting for Mercado Pago to confirm your payment. With Pix this usually takes a few seconds. Access is released automatically as soon as it clears — you do not need to pay again.';
$string['paymentrejected'] = 'The payment was not completed. Nothing was charged.';
$string['platformsite'] = 'Marketplace country';
$string['platformsite_desc'] = 'Country of the Mercado Pago account that receives the commission. It sets where sellers authorise and which accounts may be linked: the split only works between accounts of the same country, because the commission lands in the platform account and an account only holds its own currency. Sellers from other countries need a separate marketplace with its own application.';
$string['pluginname'] = 'Mercado Pago';
$string['privacy:metadata'] = 'The Mercado Pago plugin stores the seller authorisation token on the payment account and sends the payment amount and buyer email to Mercado Pago.';
$string['privacy:metadata:mercadopago'] = 'Data sent to Mercado Pago so the payment can be taken. Mercado Pago is the controller of what it receives.';
$string['privacy:metadata:mercadopago:amount'] = 'The amount to charge.';
$string['privacy:metadata:mercadopago:currency'] = 'The currency to charge in.';
$string['privacy:metadata:mercadopago:itemname'] = 'A description of what is being bought.';
$string['privacy:metadata:paygw_mercadopago'] = 'Payment transactions handled by this gateway.';
$string['privacy:metadata:paygw_mercadopago:amount'] = 'The amount charged.';
$string['privacy:metadata:paygw_mercadopago:currency'] = 'The currency charged.';
$string['privacy:metadata:paygw_mercadopago:cycles'] = 'Which cycle of the subscription this charge is.';
$string['privacy:metadata:paygw_mercadopago:mpcardid'] = 'The identifier of the card stored by Mercado Pago. The card number itself is never stored by this site.';
$string['privacy:metadata:paygw_mercadopago:mpcustomerid'] = 'The identifier of the customer record Mercado Pago keeps for this person. Not card data.';
$string['privacy:metadata:paygw_mercadopago:mppaymentid'] = 'The payment identifier at Mercado Pago.';
$string['privacy:metadata:paygw_mercadopago:status'] = 'Whether the payment was approved, rejected or is pending.';
$string['privacy:metadata:paygw_mercadopago:subscriptionid'] = 'Which subscription this charge belongs to.';
$string['privacy:metadata:paygw_mercadopago:timecreated'] = 'When the payment was started.';
$string['privacy:metadata:paygw_mercadopago:userid'] = 'The person who paid.';
$string['publickey'] = 'Public key (production)';
$string['publickey_desc'] = 'Shown in the Mercado Pago panel next to the production access token. Needed only to build the card fields in the browser — it is public by design and appears in the page source.';
$string['publickeytest'] = 'Public key (test)';
$string['publickeytest_desc'] = 'The TEST- prefixed key of the same application. Which of the two is used follows the "Test mode" setting below, so that buyer, seller and application stay on the same side. The client ID and secret are the same in both environments — only the keys change.';
$string['relinkaccount'] = 'Link a different account';
$string['reminderdays'] = 'Days before, to remind about Pix/bank slip';
$string['reminderdays_desc'] = 'Card charges itself, so it needs no reminder. Pix and bank slip do not: each cycle needs the student to act, and without a heads-up before the invoice exists, they only find out when it is due.';
$string['reminderupcoming_body'] = 'Your subscription will renew soon, and the next charge is {$a}. Pix and bank slip are not charged automatically — a new one will be issued when it is due, and you will be notified again.';
$string['reminderupcoming_subject'] = 'Your subscription renews soon';
$string['savebeforelinking'] = 'Save this gateway first, then come back to link the Mercado Pago account.';
$string['settingforapp'] = '{$a->setting} · {$a->app}';
$string['subscribeamount'] = 'You are subscribing for {$a} per cycle.';
$string['subscribeboleto'] = 'Bank slip (boleto)';
$string['subscribeboletonotautomatic'] = 'Bank slip is not charged automatically. A new one is issued for every cycle, and you will be notified when it is ready to pay.';
$string['subscribecard'] = 'Card';
$string['subscribecardnotstored'] = 'The card is stored by Mercado Pago, never by this site.';
$string['subscribecardonly'] = 'Card only — Pix and bank slip cannot be charged automatically.';
$string['subscribeconfirm'] = 'Confirm subscription';
$string['subscribecycles'] = 'Up to {$a} charges in total.';
$string['subscribeevery'] = 'Charged every {$a} days.';
$string['subscribeinvoicewaiting'] = 'Waiting for payment. This page updates automatically once Mercado Pago confirms it.';
$string['subscribepaymentmethod'] = 'Payment method';
$string['subscribepix'] = 'Pix';
$string['subscribepixnotautomatic'] = 'Pix is not charged automatically. A new QR code is issued for every cycle, and you will be notified when it is ready to pay.';
$string['subscribetitle'] = 'Card details';
$string['subscribeuntilcancelled'] = 'Charged until you cancel.';
$string['subscribeviewboleto'] = 'Open bank slip';
$string['subscribewheretocancel'] = 'You can cancel at any time under My subscriptions.';
$string['subscriptioncycle'] = 'Subscription — cycle {$a}';
$string['switchtocardcancel'] = 'Keep paying by Pix/bank slip';
$string['switchtocarddone'] = 'Done. From the next cycle on, this subscription charges the card automatically.';
$string['switchtocardexplain'] = 'This does not charge anything now. The card is stored for the NEXT cycles — the current one, if already paid by Pix or bank slip, stays as it was.';
$string['taskchargeduecycles'] = 'Emit due Mercado Pago subscription cycles (needs learner confirmation)';
$string['taskreconcile'] = 'Reconcile pending Mercado Pago transactions';
$string['taskrefreshtokens'] = 'Refresh Mercado Pago seller tokens';
$string['taskremindupcomingcycles'] = 'Remind about upcoming Pix/bank slip subscription cycles';
$string['testmode'] = 'Test mode';
$string['testmode_desc'] = 'Issue test tokens when sellers link their account, so the whole flow runs in the Mercado Pago sandbox. Buyer, seller and the platform application must all be on the same side: a real application with a test seller is refused with "one of the parties is a test account". Changing this does not convert existing links — sellers must link again. Never leave this on in production: real payments would stop working.';
$string['unlinkaccount'] = 'Unlink account';
$string['unlinkconfirm'] = 'Unlink Mercado Pago user {$a} from this payment account? The gateway will be disabled and this company will stop selling until an account is linked again. Courses already bought keep their access. This does not revoke the authorisation inside Mercado Pago — the seller can remove it from their own account settings.';
$string['unlinkdone'] = 'Account unlinked. The gateway was disabled.';
$string['webhooksecret'] = 'Webhook signing secret';
$string['webhooksecret_desc'] = 'The "secret signature" this application shows in the Mercado Pago panel. Each application has its own, and notifications are rejected when the signature does not match. Leave it empty and the signature is not checked — acceptable only while setting things up.';
