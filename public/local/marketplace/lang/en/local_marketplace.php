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
 * Strings for local_marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdays'] = 'Access for {$a} days';
$string['accessgranted'] = 'Access granted. Enjoy your course!';
$string['accesslifetime'] = 'Lifetime access';
$string['accessrecurring'] = 'Subscription, renewed every {$a} days';
$string['accessrecurringlimited'] = 'Subscription: {$a->billing} days per payment, up to {$a->cycles} payments';
$string['accessrecurringopen'] = 'Subscription: {$a->billing} days per payment, no end date';
$string['accessuntil'] = 'Access until {$a}';
$string['addmember'] = 'Add seller';
$string['addmember_help'] = 'Links an existing platform user to this company and grants them the seller role in the company category. The person must already have an account.';
$string['addplan'] = 'Add a plan';
$string['alreadyowned'] = 'You already have access to this offer.';
$string['bunnyaccountapikey'] = 'Bunny account API key';
$string['bunnyaccountapikey_desc'] = 'The platform\'s single Bunny Stream account key, used only to create a new video library for each company as it is created. It is not a library key: each company\'s own library key is stored separately, encrypted, once the library is provisioned. Leave empty to leave provisioning on hold — companies keep being created normally, just without a library yet.';
$string['buynow'] = 'Buy now';
$string['byosapikey'] = 'Library API key';
$string['byosapikey_help'] = 'Stream → Library → API in your Bunny.net dashboard.';
$string['byoscdnhostname'] = 'CDN hostname (optional)';
$string['byosconnect'] = 'Connect';
$string['byosconnected'] = 'Connected to your Bunny library #{$a}.';
$string['byosintro'] = 'This plan hosts video on your own Bunny.net Stream account — the platform never sees or pays for your bandwidth. Create a library in your Bunny dashboard first, then paste its details below.';
$string['byoslibraryid'] = 'Library ID';
$string['byosnotconnected'] = 'No Bunny library connected yet — video upload will not work until you connect one.';
$string['byossection'] = 'Video (your own Bunny account)';
$string['byossecuritykey'] = 'Token authentication key (optional)';
$string['cancelconfirm'] = 'Cancel <strong>{$a->offer}</strong>? You keep access until {$a->date} — you paid for that period and it is not taken away. After that the access simply ends, and we stop reminding you to renew.';
$string['cancelconfirmlifetime'] = 'Stop renewal reminders for <strong>{$a}</strong>? Your access does not expire, so nothing changes except the reminders.';
$string['canceldone'] = 'Subscription cancelled. Your access runs until {$a}.';
$string['cancelledbut'] = 'Cancelled. Your access runs until {$a} — the period you paid for is not taken away.';
$string['cancelledlifetime'] = 'Renewal reminders are off. Your access does not expire.';
$string['cancelsubscription'] = 'Cancel subscription';
$string['cancelundo'] = 'Reactivate';
$string['cancelundone'] = 'Subscription reactivated. You will be reminded before it expires.';
$string['cannotsell'] = 'Free courses only';
$string['cansell'] = 'Selling';
$string['cansellyes'] = 'Ready to sell. Active gateway: {$a}';
$string['commissionbase'] = 'Commission base';
$string['commissionbase_desc'] = 'What the commission percentage is applied to, for any plan or company that does not state its own. <b>Gross</b> means the platform receives the agreed percentage of the sale price and the seller absorbs the gateway fee. <b>Net</b> means the gateway fee comes out first and both sides share it.<br><br>Net is not available on every gateway: Mercado Pago charges an absolute fee and its own rate is only known afterwards, so sales there are always charged on the gross. Each sale records the base that was actually applied.';
$string['commissionbasegross'] = 'gross';
$string['commissionbaseinherit'] = 'Inherit the site base';
$string['commissionbasenet'] = 'net';
$string['commissioneffective'] = 'Effective commission';
$string['commissionfromcompany'] = 'negotiated with the company';
$string['commissionfromplan'] = 'from the {$a} plan';
$string['commissionfromsite'] = 'site default';
$string['commissionpct'] = 'Platform commission (%)';
$string['commissionsourcecompany'] = 'negotiated';
$string['commissionsourceplan'] = 'plan';
$string['commissionsourcepolicy'] = 'course policy';
$string['commissionsourcesite'] = 'site default';
$string['companies'] = 'Companies';
$string['company'] = 'Company';
$string['companycnpj'] = 'CNPJ';
$string['companycnpj_help'] = 'Optional. An individual may sell without a CNPJ.';
$string['companycommission'] = 'Commission (%)';
$string['companycommission_help'] = 'Percentage the platform keeps on sales by this company, from 0 to 100. Leave empty to use the site default — empty and zero are different: empty means nothing was negotiated, zero means the partner is exempt.';
$string['companycommissionbase'] = 'Commission base';
$string['companycommissionbase_help'] = 'Only read when a negotiated commission is set above. Leave it inheriting so the company follows the site policy.';
$string['companycreated'] = 'Company {$a} created. Add the other sellers below.';
$string['companyhostname'] = 'Custom domain';
$string['companyhostname_help'] = 'Optional. The company own domain, without the scheme, for example <b>courses.partner.com</b>. It has to point to this server, and the certificate is the responsibility of whoever operates the DNS. Leaving it empty keeps the company on the platform domain.';
$string['companyname'] = 'Name';
$string['companyowner'] = 'Owner';
$string['companyowner_help'] = 'The person who manages the company and links its Mercado Pago account. Must already have an account on the platform.';
$string['companypanel'] = 'Marketplace: company panel';
$string['companyshortname'] = 'Short name';
$string['companyshortname_help'] = 'Used in the company URL. Letters, numbers and hyphens only.';
$string['companystatus'] = 'Status';
$string['companytheme'] = 'Theme';
$string['companyupdated'] = 'Company {$a} updated.';
$string['configurepayment'] = 'Configure Mercado Pago';
$string['createcompany'] = 'Create company';
$string['createcompanyintro'] = 'Creating a company provisions a course category, assigns the seller role to the owner in that category and creates a payment account. Close the partnership first — this screen only carries it out.';
$string['currentplan'] = 'Current plan: {$a}';
$string['defaultcountry'] = 'Default country';
$string['defaultcountry_desc'] = 'Country where the payment account of a new company is provisioned. It is not a limit: a company can be given accounts in other countries later, and it is the offer that says where each plan sells.';
$string['defaultfeepercent'] = 'Default commission (%)';
$string['defaultfeepercent_desc'] = 'Percentage the platform keeps when neither the course nor the company has a negotiated rate. A company set to 0% stays at 0%: an empty field means "inherit this default", which is not the same as "no commission".';
$string['defaultthemename'] = 'Site default theme';
$string['domainsuspendedbody'] = 'The company behind this address is not selling at the moment. If you already bought a course, you can still reach it through the main platform.';
$string['domainsuspendedtitle'] = 'This store is unavailable';
$string['editcompanyintro'] = 'The short name cannot be changed: it is the category ID number and appears in storefront and dashboard links the seller may already have shared. The owner is changed on the sellers screen, where you can promote someone else first.';
$string['editplan'] = 'Edit plan';
$string['erroraccessdays'] = 'Enter at least one day of access.';
$string['erroraccounttaken'] = 'This payment account is already linked to another company or country.';
$string['erroralreadymember'] = 'This person is already a seller of this company.';
$string['errorbillingdays'] = 'Enter the billing interval in days.';
$string['errorbunnyapi'] = 'The Bunny API returned an error: {$a}';
$string['errorcannotremoveowner'] = 'The owner cannot be removed. Make someone else the owner first — a company without an owner has nobody responsible for its payment account.';
$string['errorcannotsell'] = 'This company cannot sell yet: configure a payment method first.';
$string['errorcnpjinvalid'] = 'This is not a valid company tax ID.';
$string['errorcommissionrange'] = 'Use a number from 0 to 100, or leave it empty to inherit the site default.';
$string['errorcountryunsupported'] = 'The marketplace does not operate in country {$a}.';
$string['errorcoursenotowned'] = 'This course does not belong to this company.';
$string['errorcurl'] = 'Could not reach the Bunny API: {$a}';
$string['errordomainmap'] = 'Could not write the seller domain map. Check that the Moodle data directory is writable.';
$string['errorhostnametaken'] = 'This domain is already linked to another company.';
$string['errorinvalidresolution'] = '"{$a}" is not a resolution the platform recognises.';
$string['errorinvalidresponse'] = 'The Bunny API returned a response that could not be understood.';
$string['errorlibrarytaken'] = 'This company already has a Bunny library.';
$string['errormaxcycles'] = 'Use zero for no limit, or a positive number.';
$string['errornoaccount'] = 'This company has no payment account. Reinstall or recreate the company.';
$string['errornocourses'] = 'Choose at least one course, or use the Whole catalogue type.';
$string['errornoencryptionkey'] = 'This installation has no encryption key configured, so the Bunny key cannot be stored.';
$string['errornotbyos'] = 'This company is not on a bring-your-own-streaming plan. Switch the plan first.';
$string['errorpageaccent'] = 'Use a hexadecimal colour such as #B85410, or leave it empty.';
$string['errorplanarchived'] = 'This plan is archived and cannot be assigned to a company.';
$string['errorplanfeenegative'] = 'The monthly fee cannot be negative.';
$string['errorplannotbillable'] = 'This company has no plan with a monthly fee to charge.';
$string['errorplannotfound'] = 'The selected plan does not exist.';
$string['errorplanshortnametaken'] = 'Another plan already uses this short name.';
$string['errorplantiernegative'] = 'The price cap cannot be negative.';
$string['errorplatformhostingunavailable'] = 'Hosting video on the platform is not available yet.';
$string['errorrecurringfree'] = 'A subscription needs a price. A free one would expire with no way to renew.';
$string['errorsellerrolemissing'] = 'The seller role is missing. Reinstall the Marketplace plugin.';
$string['errorshortnametaken'] = 'This short name is already in use.';
$string['errorsinglemanycourses'] = 'A single course offer releases one course. Use Bundle for more than one.';
$string['expiringbody'] = 'Hello,

Your access to {$a->offer}, from {$a->company}, ends on {$a->date}.

There is no automatic charge — to keep your access, pay again here:
{$a->url}

If you would rather stop, do nothing and the access simply ends.';
$string['expiringbodyhtml'] = '<p>Hello,</p><p>Your access to <strong>{$a->offer}</strong>, from {$a->company}, ends on <strong>{$a->date}</strong>.</p><p>There is no automatic charge — to keep your access, <a href="{$a->url}">pay again here</a>.</p><p>If you would rather stop, do nothing and the access simply ends.</p>';
$string['expiringlastbody'] = 'Hello,

This is the last notice: your access to {$a->offer}, from {$a->company}, ends on {$a->date}.

After that the courses are blocked until you pay again. Nothing is lost — your grades and progress stay, and access comes back as soon as the payment goes through:
{$a->url}

If you would rather stop, do nothing.';
$string['expiringlastbodyhtml'] = '<p>Hello,</p><p>This is the <strong>last notice</strong>: your access to <strong>{$a->offer}</strong>, from {$a->company}, ends on <strong>{$a->date}</strong>.</p><p>After that the courses are blocked until you pay again. Nothing is lost — your grades and progress stay, and access comes back as soon as the payment goes through: <a href="{$a->url}">pay now</a>.</p><p>If you would rather stop, do nothing.</p>';
$string['expiringlastsubject'] = 'Last notice: {$a->offer} is blocked in {$a->days} day(s)';
$string['expiringline'] = 'Bank slip line to copy: {$a}';
$string['expiringsubject'] = 'Your access to {$a->offer} ends in {$a->days} day(s)';
$string['filterallcategories'] = 'All categories';
$string['filteralltypes'] = 'All types';
$string['filtercategory'] = 'Category';
$string['filterclear'] = 'Clear filters';
$string['filtertype'] = 'Type';
$string['free'] = 'Free';
$string['getfree'] = 'Get free access';
$string['hostingbyos'] = 'BYOS: the producer connects their own storage';
$string['hostingexternal'] = 'Outside the platform';
$string['hostingnative'] = 'Native: the platform hosts the video';
$string['hostingplatform'] = 'On the platform';
$string['hostingtype'] = 'Video hosting';
$string['linkednotenabled'] = 'The Mercado Pago account is linked but the gateway is switched off, so nothing can be sold yet. Open the payment settings and enable it.';
$string['makeowner'] = 'Make owner';
$string['makeseller'] = 'Make seller';
$string['managecourses'] = 'Manage courses';
$string['managemembers'] = 'Sellers';
$string['managerrole'] = 'Company owner';
$string['managerroledesc'] = 'Builds courses for a company and answers for its payment account and members. Like the seller role, cannot upload files: course videos must be hosted outside the platform.';
$string['marketplace:createcompany'] = 'Create a company';
$string['marketplace:manageall'] = 'Manage every company on the platform';
$string['marketplace:managecompany'] = 'Manage the company';
$string['marketplace:managepayment'] = 'Manage the company payment account';
$string['marketplace:managesales'] = 'Manage the company sales and subscriptions';
$string['marketplace:publishcourse'] = 'Publish a course for the company';
$string['marketplace:refundsale'] = 'Refund a sale, returning the money and revoking access';
$string['marketplace:viewreport'] = 'View the company financial report';
$string['memberadded'] = 'Seller added.';
$string['memberowner'] = 'Owner';
$string['memberremoved'] = 'Seller removed.';
$string['memberrolechanged'] = 'Role changed.';
$string['members'] = 'Sellers';
$string['memberseller'] = 'Seller';
$string['membersof'] = 'Sellers of {$a}';
$string['messageprovider:expiring'] = 'Access about to expire';
$string['modedays'] = 'Fixed period';
$string['modelifetime'] = 'Lifetime';
$string['moderecurring'] = 'Subscription';
$string['mysubsactive'] = 'Subscriptions';
$string['mysubscriptions'] = 'My subscriptions';
$string['mysubspayments'] = 'Payments';
$string['nocompanies'] = 'No companies yet.';
$string['nocompany'] = 'You do not belong to any company.';
$string['nogatewayforcountry'] = 'No installed payment gateway can receive money in this country.';
$string['nomembers'] = 'This company has no sellers.';
$string['nooffers'] = 'This company has no published offers yet.';
$string['nooffersfiltered'] = 'No offer matches these filters.';
$string['nopaymentaccount'] = 'This company has no payment method configured, so it can only publish free courses.';
$string['nopayments'] = 'No payments yet.';
$string['noplan'] = 'No plan';
$string['noplans'] = 'No plans yet.';
$string['nosubscriptions'] = 'You have not bought anything yet.';
$string['offeraccess'] = 'Access and billing';
$string['offeraccessdays'] = 'Days of access per payment';
$string['offeraccessdays_help'] = 'How long each payment unlocks. In a subscription this can exceed the billing interval to give a grace period: billing every 30 days while granting 35 lets a late payment through without cutting the learner off.';
$string['offeraccessmode'] = 'Access model';
$string['offeraccessmode_help'] = 'Lifetime never expires. Fixed period grants a number of days per purchase. Subscription grants a period and expects renewal.';
$string['offerbillingdays'] = 'Billing interval (days)';
$string['offerbillingdays_help'] = 'How often the learner is expected to pay again. Used for the expiry reminder.';
$string['offercountry'] = 'Country';
$string['offercountry_help'] = 'Where this offer sells. It decides which account receives the money, the currency and which gateways appear at checkout. Selling in another country is a separate offer, not another price on this one: the payment subsystem resolves amount, currency and account from the offer alone, without knowing who is buying.';
$string['offercourses'] = 'Courses released';
$string['offercourses_help'] = 'Which courses this offer unlocks. Not needed for Whole catalogue, which follows the company category.';
$string['offercreate'] = 'New offer';
$string['offeredit'] = 'Offer';
$string['offerincludes'] = 'Includes {$a} course(s)';
$string['offermaxcycles'] = 'Maximum payments';
$string['offermaxcycles_help'] = 'How many times this subscription may be charged in total. Zero means no limit. Use 12 for a monthly plan that runs for a year, or 3 for a yearly plan that runs for three years.';
$string['offername'] = 'Offer';
$string['offerprice'] = 'Price';
$string['offerprice_help'] = 'Zero makes the offer free. Free offers skip Mercado Pago entirely.';
$string['offerpublication'] = 'Publication';
$string['offerrecurringwarning'] = 'Mercado Pago has no recurring charge with split payments, so nothing is debited automatically. The learner receives a reminder before expiry with a link to pay again.';
$string['offersaved'] = 'Offer saved.';
$string['offersortorder'] = 'Display order';
$string['offerssection'] = 'Offers';
$string['offerstatus_help'] = 'Only published offers appear in the storefront. Archiving does not revoke access already bought.';
$string['offertype'] = 'Type';
$string['offertype_help'] = 'Single course sells one course. Bundle sells a chosen set — this is how you build tiers such as Basic, Standard and Complete for the same company. Whole catalogue follows the company category, so new courses join automatically.';
$string['offerunlocks'] = 'This is the offer that unlocks the content you were viewing.';
$string['pageaccent'] = 'Accent colour';
$string['pageaccent_help'] = 'Hexadecimal, such as #B85410. Colours the buy buttons and is exposed to your CSS as the --mp-accent variable.';
$string['pagecss'] = 'Custom stylesheet';
$string['pagecss_help'] = 'A .css file loaded after the theme, so it can override it. Served as a stylesheet, never inline — the browser can never treat it as script. For a fully custom page, build it anywhere you like and read the offers through the API instead.';
$string['pageintro'] = 'Opening text';
$string['pageintro_help'] = 'Appears above the offers. Written as rich text and filtered by Moodle — it is sales copy, not a place for scripts.';
$string['pagelogo'] = 'Brand logo';
$string['pagelogo_help'] = 'Shown at the top of your storefront. A web image — PNG or SVG with transparent background works best. It is displayed at up to 96px tall.';
$string['pagesection'] = 'Storefront';
$string['pagetitle'] = 'Storefront title';
$string['pagetitle_help'] = 'Shown as the page heading. Leave empty to use the company name.';
$string['payinvoice'] = 'Pay this cycle';
$string['paymentsection'] = 'Payment method';
$string['paysubscription'] = 'Pay subscription';
$string['plan'] = 'Plan';
$string['plancommissionbase'] = 'Commission base';
$string['plancommissionbase_help'] = 'What the percentage of this plan is applied to. Leave it inheriting unless the plan sells a different arrangement from the rest of the platform. The base is part of what the partner signed, so changing it later does not affect sales already made.';
$string['plancommissionpct'] = 'Commission (%)';
$string['plancommissionpct_help'] = 'Applies to companies on this plan that have no individually negotiated commission. A negotiated value on the company always wins over the plan.';
$string['plancountry'] = 'Country';
$string['plandescription'] = 'Description';
$string['planexpiryon'] = 'Subscription paid until {$a}';
$string['planhostingmodel'] = 'Hosting model';
$string['planispublic'] = 'Show on the public plan comparison';
$string['planmonthlyfee'] = 'Monthly fee';
$string['planmonthlyfee_help'] = 'Charged automatically, every 30 days, from the company panel — see "Pay subscription" on the company page.';
$string['planname'] = 'Name';
$string['plannotpaidyet'] = 'Not paid yet.';
$string['planprodesc'] = 'For producers already selling, who bring their own storage and pay less commission.';
$string['planproname'] = 'Pro';
$string['plans'] = 'Plans';
$string['planshortname'] = 'Short name';
$string['planshortname_help'] = 'Stable key used in code and by the installer seed. Unlike the name, it is not meant to change with marketing.';
$string['plansortorder'] = 'Display order';
$string['planssection'] = 'Plan';
$string['planstart100desc'] = 'Platform hosting, top video quality (4K), 10% commission per sale.';
$string['planstart100name'] = 'Start — R$100/month';
$string['planstart50desc'] = 'Platform hosting, higher video quality (1080p), 10% commission per sale.';
$string['planstart50name'] = 'Start — R$50/month';
$string['planstartfreedesc'] = 'Zero monthly fee, platform hosting at 720p, 10% commission per sale. The entry point — you can upgrade any time for better video quality.';
$string['planstartfreename'] = 'Start — Free';
$string['planstatus'] = 'Status';
$string['planstatusactive'] = 'Active';
$string['planstatusarchived'] = 'Archived';
$string['plantiermaxprice'] = 'Price cap';
$string['plantiermaxresolution'] = 'Maximum resolution';
$string['plantiernolimit'] = 'No cap';
$string['plantiers'] = 'Resolution caps by course price';
$string['plantiers_help'] = 'Each row caps the video resolution for courses up to the given price. The last row, with an empty price, covers everything above. Only makes sense when the platform pays for the bandwidth.';
$string['platformaccountname'] = 'Platform ({$a})';
$string['pluginname'] = 'Marketplace';
$string['privacy:metadata'] = 'The Marketplace plugin stores companies, their sellers and payment credentials.';
$string['privacy:metadata:entitlement'] = 'What a learner bought and how long their access runs.';
$string['privacy:metadata:entitlement:companyid'] = 'The company that sold it.';
$string['privacy:metadata:entitlement:cycles'] = 'How many payments have been made.';
$string['privacy:metadata:entitlement:offerid'] = 'The offer bought.';
$string['privacy:metadata:entitlement:status'] = 'Whether the access is active, expired or revoked.';
$string['privacy:metadata:entitlement:timeend'] = 'When access ends. Zero means it does not expire.';
$string['privacy:metadata:entitlement:timestart'] = 'When access started.';
$string['privacy:metadata:entitlement:userid'] = 'The learner.';
$string['privacy:metadata:member'] = 'Which companies a person sells for.';
$string['privacy:metadata:member:companyid'] = 'The company.';
$string['privacy:metadata:member:memberrole'] = 'Whether they own the company or sell for it.';
$string['privacy:metadata:member:timecreated'] = 'When the link was made.';
$string['privacy:metadata:member:userid'] = 'The person linked to the company.';
$string['refundconfirm'] = 'Refund {$a->amount} to {$a->user} for {$a->offer}?<br><br>The money goes back through the gateway, the commission is cancelled with it, and the learner <strong>loses access immediately</strong>. If this is the first payment of a subscription, the subscription is cancelled too. There is no undo.';
$string['refunddone'] = 'Sale refunded and access revoked.';
$string['refundfailed'] = 'The gateway did not accept the refund. Nothing was changed here.';
$string['refundsale'] = 'Refund';
$string['renewnotice'] = 'Your access ends on {$a}. Renew to keep it.';
$string['renewnow'] = 'Renew now';
$string['reportaccessuntil'] = 'Access until';
$string['reportall'] = 'All time';
$string['reportcommission'] = 'Platform commission';
$string['reportcommissionterms'] = 'Terms applied';
$string['reportcoursesnotice'] = 'A bundle counts in full for every course it unlocks — nobody buys a third of a bundle. So this column adds up to more than your total revenue, and is meant for comparing courses against each other, not for summing.';
$string['reportdays'] = 'Last {$a} days';
$string['reportentries'] = 'Sales';
$string['reportexternalid'] = 'Gateway transaction';
$string['reportgateway'] = 'Gateway';
$string['reportgross'] = 'Gross';
$string['reportlastpayment'] = 'Last payment';
$string['reportnetnotice'] = 'The Mercado Pago fee is not shown here because it is not reported back to us: it varies by payment method and payout term, and is deducted on their side before the platform commission. Your net amount is the one in your Mercado Pago statement.';
$string['reportnocourses'] = 'No course has been sold yet.';
$string['reportnosales'] = 'No approved sales in this period.';
$string['reportnostudents'] = 'Nobody has access to this company\'s offers yet.';
$string['reportnosubs'] = 'No subscription offers, or nobody has subscribed yet.';
$string['reportpaymentmethod'] = 'Payment method';
$string['reportpayments'] = 'Payments';
$string['reportsales'] = 'Approved sales';
$string['reportsaleswith'] = 'Sales including it';
$string['reportsection'] = 'Sales';
$string['reportstudentsactive'] = 'Students with current access';
$string['reportstudentsince'] = 'Since';
$string['reportstudentsnotice'] = 'One line per access right, so a student who bought three offers appears three times. The count above is of distinct people. The list comes from access rights and not from sales, so somebody who claimed a free offer counts as a student too.';
$string['reportstudentsrows'] = 'Access rights';
$string['reportsubactive'] = 'Active';
$string['reportsubcancelled'] = 'Cancelled';
$string['reportsubduesoon'] = 'Ends in {$a} d';
$string['reportsubexpired'] = 'Expired';
$string['reportsubnorenew'] = 'renewal cancelled';
$string['reportsubsnotice'] = 'There is no billing schedule to show: Mercado Pago has no recurring payments with split, so each renewal is a separate purchase that extends access. This screen shows how many times each learner has paid and how long their access still runs.';
$string['reportviewcourses'] = 'Courses sold';
$string['reportviewstudents'] = 'Students';
$string['reportviewsubscriptions'] = 'Subscriptions';
$string['reportviewtransactions'] = 'Transactions';
$string['resenddone'] = 'The learner was sent the invoice again.';
$string['resendfailed'] = 'Nothing was sent — the learner may be suspended, or the offer is no longer on sale.';
$string['resendinvoice'] = 'Resend invoice';
$string['selectplan'] = 'Select plan';
$string['sellerrole'] = 'Company seller';
$string['sellerroledesc'] = 'Builds and publishes courses for a company. Does not reach the payment account or the member list, and cannot upload files: course videos must be hosted outside the platform.';
$string['settings'] = 'Settings';
$string['sortby'] = 'Sort by';
$string['sortmanual'] = 'Featured';
$string['sortname'] = 'Name';
$string['sortnewest'] = 'Newest';
$string['sortprice'] = 'Price: low to high';
$string['sortpricedesc'] = 'Price: high to low';
$string['statusactive'] = 'Active';
$string['statusarchived'] = 'Archived';
$string['statusdraft'] = 'Draft';
$string['statuspublished'] = 'Published';
$string['statussuspended'] = 'Suspended';
$string['switchtocard'] = 'Switch to card';
$string['tasknotifyexpiring'] = 'Notify learners of expiring access';
$string['typebundle'] = 'Bundle';
$string['typecatalog'] = 'Whole catalogue';
$string['typesingle'] = 'Single course';
$string['unavailable'] = 'Not available for purchase yet.';
$string['viewstorefront'] = 'View storefront';
