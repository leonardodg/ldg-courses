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
 * Strings do local_partners.
 *
 * ORDEM ALFABETICA OBRIGATORIA, e paridade exata de chaves entre os idiomas.
 *
 * @package    local_partners
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alreadyapproved'] = 'This application was already approved and the company {$a} exists.';
$string['alreadydecided'] = 'This application was already decided.';
$string['analyticsid'] = 'Google Analytics measurement ID';
$string['analyticsid_desc'] = 'Loaded only on the public partner pages, and never inside the LMS: sending the activity of a learner to a third party is a different decision, with a different weight. This switch is technical, not legal. The tag sets a cookie and sends data abroad, which under GDPR and the Brazilian LGPD needs a lawful basis and, in practice, a consent banner.';
$string['applicationmessage'] = 'Anything else we should know';
$string['applications'] = 'Partner applications';
$string['applicationstatus'] = 'Status';
$string['applybadge'] = 'A person reads every application';
$string['applyformlead'] = 'Tell us about your operation. Nothing here is published, and we answer by email.';
$string['applylead'] = 'Tell us about your operation. We answer every application.';
$string['applymetadescription'] = 'Apply to publish and sell your courses on the platform. No monthly fee to start, and a person reads every application.';
$string['applyreviewed'] = 'Reviewed by a human';
$string['applysecure'] = 'Encrypted connection';
$string['applytitle'] = 'Apply to sell on the platform';
$string['approvalpending'] = 'Approving an application provisions a company and a course category. That step is not built yet: for now, get in touch with the applicant directly.';
$string['approvedbody'] = 'Your application for {$a->company} was approved.

You can now sign in and set up your courses:
{$a->url}

{$a->note}';
$string['approvedmessage'] = 'Application approved. The company {$a} was created.';
$string['approvedsubject'] = 'Your application for {$a} was approved';
$string['backtohome'] = 'Back to the home page';
$string['backtolanding'] = 'Back to the partner page';
$string['brandname'] = 'LDG Technology';
$string['catalogmetadescription'] = 'Browse the courses published on the platform. Each course is sold by the person or company that created it.';
$string['checklandingreachable'] = 'Partner landing reachable by visitors';
$string['checklandingreachableaction'] = 'Navigation settings';
$string['checklandingreachablefail'] = 'The landing page is the site home, but visitors who are not logged in are sent to the login page';
$string['checklandingreachablefaildetail'] = 'The Dashboard is disabled, and with it disabled Moodle redirects every logged-out visitor away from the site home before any theme code runs. This is not the "force users to log in" setting. Beyond the landing page never being shown, the canonical URL, every hreflang alternate and the main sitemap entry all point at the site home, so search engines follow each one into a redirect to a page marked noindex. Enable the Dashboard in Navigation settings.';
$string['checklandingreachablena'] = 'The landing page is not the site home, so the site home is Moodle\'s own';
$string['checklandingreachableok'] = 'Visitors who are not logged in reach the landing page at the site home';
$string['cnpj'] = 'Company tax ID (CNPJ)';
$string['cnpj_help'] = 'Optional. Individuals sell without one. If you enter it, it must be a valid number.';
$string['colormodetoggle'] = 'Switch between light and dark';
$string['companyname'] = 'Company name';
$string['companyowner'] = 'Company owner';
$string['companyowner_help'] = 'The site user who will administer the company and connect the payment account. If the applicant has no account yet, create one first: creating users from a public form is a spam risk and this screen does not do it.';
$string['companyshortname'] = 'Short name';
$string['companyshortname_help'] = 'Goes into the company URL and into the course category name. It is suggested from the company name, and you confirm it: the category is a global object of the site.';
$string['confirmbody'] = 'Confirm your email to send the application for {$a->company}.

Open this link:
{$a->url}

If it was not you, ignore this message and nothing happens.';
$string['confirmdone'] = 'Email confirmed. Your application is in the queue and we answer by email.';
$string['confirminvalid'] = 'This link is not valid, or it has already been used.';
$string['confirmsubject'] = 'Confirm your email to apply as a partner';
$string['confirmtitle'] = 'Email confirmation';
$string['contactemail'] = 'Email';
$string['contactname'] = 'Contact name';
$string['contactphone'] = 'Phone';
$string['cookiesurl'] = 'Cookie policy URL';
$string['cookiesurl_desc'] = 'Where the "{$a}" link in the public footer points. Leave empty and the link is not shown. If you enable Google Analytics, this document stops being optional.';
$string['country'] = 'Country or region of operation';
$string['ctalead'] = 'No monthly fee to start, and no lock-in. You only pay when you sell.';
$string['ctanote'] = 'No card needed to start on the entry plan.';
$string['ctasecondary'] = 'Read the questions first';
$string['ctatitle'] = 'Ready to publish your first course?';
$string['decision'] = 'Decision';
$string['decisionapprove'] = 'Approve and create the company';
$string['decisionreject'] = 'Reject';
$string['enablelanding'] = 'Show the partner landing page';
$string['enablelanding_desc'] = 'When on, the landing page is served and the theme may use it as the site home for anonymous visitors. When off, the page returns an error and the theme falls back to the standard front page.';
$string['enablerecaptcha'] = 'Use reCAPTCHA on the application form';
$string['enablerecaptcha_desc'] = 'Adds the reCAPTCHA that Moodle already provides to the public form. The site keys are set, so turning this on takes effect immediately. The hidden honeypot field and the per-IP rate limit stay on either way: they cost nothing and never inconvenience a human.';
$string['enablerecaptcha_nokeys'] = 'Adds the reCAPTCHA that Moodle already provides to the public form. The site has no reCAPTCHA keys set, so this has no effect yet. The hidden honeypot field and the per-IP rate limit stay on either way.';
$string['erroralreadyconfirmed'] = 'This application has already been confirmed.';
$string['erroralreadydecided'] = 'This application has already been decided.';
$string['errorapplicationnotfound'] = 'Application not found.';
$string['errorcnpjinvalid'] = 'This is not a valid company tax ID.';
$string['errorcountryinvalid'] = 'Choose a country from the list.';
$string['errorduplicatepending'] = 'There is already an open application for this email or tax ID. We will be in touch.';
$string['erroremailinvalid'] = 'Enter a valid email address.';
$string['errorlearnersbandinvalid'] = 'Choose one of the listed learner volumes.';
$string['errorownerrequired'] = 'Choose the site user who will own the company.';
$string['errorplannotfound'] = 'The selected plan does not exist.';
$string['errorshortnamerequired'] = 'Enter a short name for the company.';
$string['errorshortnametaken'] = 'Another company already uses this short name.';
$string['errortermsrequired'] = 'Accept the partnership terms and the privacy policy to continue.';
$string['errortoolong'] = 'Use at most {$a} characters.';
$string['errortoomany'] = 'Too many applications from this connection. Try again in an hour.';
$string['faq1answer'] = 'You keep the sale. The platform takes only its commission, and the money goes into your own payment account.';
$string['faq1question'] = 'Who receives the money from the sale?';
$string['faq2answer'] = 'You do. The charge is created in your account, so the invoice is yours to issue. The platform never issues an invoice on your behalf.';
$string['faq2question'] = 'Who issues the invoice?';
$string['faq3answer'] = 'Yes. Free courses cost nothing and do not need a plan. The commission only applies to paid sales.';
$string['faq3question'] = 'Can I publish free courses?';
$string['faq4answer'] = 'On the Starter plan the platform pays for the video bandwidth, so the maximum resolution follows the course price. On the plans where you connect your own storage there is no cap.';
$string['faq4question'] = 'Why does video quality depend on the course price?';
$string['faqeyebrow'] = 'Straight answers';
$string['faqlead'] = 'What people ask before applying, about money, invoicing and delivery.';
$string['faqtitle'] = 'Questions we get a lot';
$string['footerbuilton'] = 'Built on Moodle 5.2';
$string['footercookies'] = 'Cookie policy';
$string['footercredit'] = 'Built by {$a}';
$string['footerlegal'] = 'Legal';
$string['footernavlabel'] = 'Footer links';
$string['footerplatform'] = 'Platform';
$string['footerprivacy'] = 'Privacy policy';
$string['footertagline'] = 'Publish and sell your own online courses on standard Moodle 5.2, charging in your own account.';
$string['footerterms'] = 'Terms of use';
$string['frontpagemode'] = 'Site home for visitors who are not logged in';
$string['frontpagemode_desc'] = 'Choose what an anonymous visitor sees at the site address. The partner landing page replaces the whole front page for them; logged in users keep the normal site home either way. It never replaces the home of a seller domain.';
$string['frontpagemodedefault'] = 'Moodle site home';
$string['frontpagemodelanding'] = 'Partner landing page';
$string['heroctasecondary'] = 'See the plans';
$string['heroctatext'] = 'Apply to be a partner';
$string['herolead'] = 'Publish your courses, charge in your own name and pay only when you sell.';
$string['heropill'] = 'Moodle 5.2 core, multi-company';
$string['herostat1'] = 'No monthly fee on the entry plan';
$string['herostat2'] = 'The charge is created in your account';
$string['herostat3'] = 'Standard Moodle 5.2, no fork';
$string['herotitle'] = 'Sell your courses';
$string['herotitleaccent'] = 'without building a platform';
$string['honeypotlabel'] = 'Leave this field empty';
$string['howeyebrow'] = 'From application to first sale';
$string['howlead'] = 'Four steps, and a person reads every application.';
$string['howtitle'] = 'How it works';
$string['landingdisabled'] = 'The partner landing page is turned off.';
$string['landingtitle'] = 'Become a partner';
$string['languageselector'] = 'Language';
$string['learnersband'] = 'Expected active learners per month';
$string['learnersbandlarge'] = '1,000 to 5,000 learners';
$string['learnersbandmedium'] = '100 to 1,000 learners';
$string['learnersbandsmall'] = 'Up to 100 learners';
$string['learnersbandundecided'] = 'Not sure yet';
$string['learnersbandxlarge'] = 'More than 5,000 learners';
$string['maxperhour'] = 'Applications per hour, per connection';
$string['maxperhour_desc'] = 'Rate limit for the public form. This is the anti-spam layer that is always on: unlike the captcha, it does not depend on any key being configured.';
$string['metadescription'] = 'Publish and sell your online courses. No monthly fee to start, payment in your own account and commission only on what you sell.';
$string['newapplicationbody'] = 'A new partner application arrived from {$a->company}, sent by {$a->contact}.

Review it here:
{$a->url}';
$string['newapplicationsubject'] = 'New partner application: {$a}';
$string['noapplications'] = 'No applications yet.';
$string['opencompany'] = 'Open the company';
$string['ownermatched'] = 'An account already exists for {$a} and is selected above.';
$string['ownerwillbecreated'] = 'No account exists for {$a}. Leave the owner empty and one will be created on approval, with an email inviting them to set a password. Choose a different user only if the company should belong to someone else.';
$string['permonth'] = '/month';
$string['plan'] = 'Plan';
$string['planbadgepopular'] = 'Most chosen';
$string['plancommission'] = '{$a}% commission per sale';
$string['planctatext'] = 'Start with this plan';
$string['planfree'] = 'No monthly fee';
$string['planhostingbyos'] = 'You connect your own video storage';
$string['planhostingnative'] = 'Video hosting included';
$string['planofinterest'] = 'Plan of interest';
$string['planseyebrow'] = 'Transparent pricing';
$string['planslead'] = 'Start with no monthly fee and move up when it pays off.';
$string['plansnote'] = 'Commission applies to the gross sale amount. Free courses have no commission and need no plan.';
$string['planstitle'] = 'Plans';
$string['planundecided'] = 'Not sure yet';
$string['pluginname'] = 'Partner acquisition';
$string['privacy:metadata:application'] = 'Applications from companies that want to sell on the platform. The applicant is usually not a registered user.';
$string['privacy:metadata:application:cnpj'] = 'The company tax ID, when given.';
$string['privacy:metadata:application:companyname'] = 'The company name as entered.';
$string['privacy:metadata:application:contactemail'] = 'The contact email address.';
$string['privacy:metadata:application:contactname'] = 'The name of the person applying.';
$string['privacy:metadata:application:contactphone'] = 'The contact phone number, when given.';
$string['privacy:metadata:application:country'] = 'The country or region the company operates in.';
$string['privacy:metadata:application:learnersband'] = 'The expected number of active learners per month, as a range.';
$string['privacy:metadata:application:message'] = 'The free-text message sent with the application.';
$string['privacy:metadata:application:reviewerid'] = 'The site user who reviewed the application.';
$string['privacy:metadata:application:submitterip'] = 'The IP address the application came from, used only for rate limiting.';
$string['privacy:metadata:application:termsaccepted'] = 'When the partnership terms and the privacy policy were accepted.';
$string['privacy:metadata:application:timecreated'] = 'When the application was sent.';
$string['privacy:metadata:application:userid'] = 'The site user who sent the application, when it was sent by someone signed in.';
$string['privacy:path:applications'] = 'Partner applications sent';
$string['privacy:path:reviews'] = 'Partner applications reviewed';
$string['privacyurl'] = 'Privacy policy URL';
$string['privacyurl_desc'] = 'Where the "{$a}" link in the public footer points. Leave empty and the link is not shown.';
$string['rejectedbody'] = 'Your application for {$a->company} was not approved this time.

{$a->note}';
$string['rejectedmessage'] = 'Application from {$a} rejected.';
$string['rejectedsubject'] = 'About your application for {$a}';
$string['requiredfields'] = 'Required fields';
$string['requireemailconfirmation'] = 'Require email confirmation from anonymous visitors';
$string['requireemailconfirmation_desc'] = 'The application only reaches the queue after the person opens a link sent to the address they typed. This is the anti-bot layer the others do not replace: rate limiting and a captcha cost a bot time, this one costs a real, working mailbox per application. It needs working outgoing email — with SMTP broken, turning this on stalls the queue. It never applies to logged in users: the site already confirmed their address.';
$string['reviewnote'] = 'Note';
$string['reviewnote_help'] = 'Goes in the email to the applicant. On a rejection this is the only explanation they get, so write it for them to read.';
$string['savedecision'] = 'Save decision';
$string['searchconsoletoken'] = 'Google Search Console verification code';
$string['searchconsoletoken_desc'] = 'Only the content value of the meta tag Google gives you, without the tag itself. Leave empty and no tag is emitted: a verification tag with the wrong value is worse than none, because Search Console then reports "failed" instead of "missing". You can also verify by DNS, which needs nothing here.';
$string['sectionapply'] = 'Apply';
$string['sectionfaq'] = 'FAQ';
$string['sectionfeatures'] = 'Features';
$string['sectionhow'] = 'How it works';
$string['sectionnavlabel'] = 'Sections of this page';
$string['sectionpricing'] = 'Plans';
$string['seotitle'] = 'Sell your online courses';
$string['skiptomain'] = 'Skip to main content';
$string['statusapproved'] = 'Approved';
$string['statuspending'] = 'Pending';
$string['statusrejected'] = 'Rejected';
$string['statusunconfirmed'] = 'Awaiting email confirmation';
$string['step1text'] = 'Send the form. It takes a minute and asks only what we need to talk to you.';
$string['step1title'] = 'Apply';
$string['step2text'] = 'We answer, agree on the plan and set up your space on the platform.';
$string['step2title'] = 'We talk';
$string['step3text'] = 'You connect your own payment account. The money from every sale lands there.';
$string['step3title'] = 'Connect your account';
$string['step4text'] = 'Upload your courses and start selling. The commission is charged per sale, never up front.';
$string['step4title'] = 'Publish and sell';
$string['submitapplication'] = 'Send application';
$string['submittedon'] = 'Sent on';
$string['taskpurgeunconfirmed'] = 'Delete unconfirmed partnership applications';
$string['termsaccept'] = 'I accept the partnership terms and the {$a}.';
$string['termsacceptedon'] = 'Terms accepted on';
$string['termsacceptplain'] = 'I accept the partnership terms and the privacy policy.';
$string['termslinktext'] = 'site policy';
$string['termsurl'] = 'Terms of use URL';
$string['termsurl_desc'] = 'Where the "{$a}" link in the public footer points. Leave empty and the link is not shown: a legal link that leads nowhere is worse than no link, because it promises a document. Defaults to the site policy when one is set.';
$string['thanksbody'] = 'Your application is in. We read every one and answer by email.';
$string['thanksbodyunconfirmed'] = 'Check your inbox. Your application reaches us as soon as you open the link we just sent.';
$string['thankstitle'] = 'Application received';
$string['tierabove'] = 'Above {$a}';
$string['tierany'] = 'Any price';
$string['tierslabel'] = 'Video quality by course price';
$string['tierupto'] = 'Up to {$a}';
$string['unconfirmedretentiondays'] = 'Keep unconfirmed applications for (days)';
$string['unconfirmedretentiondays_desc'] = 'An application whose email is never confirmed is deleted after this many days, along with the name, phone number and IP address it carried. Set to 0 to keep them indefinitely.';
$string['value1note'] = 'No fixed cost to start';
$string['value1text'] = 'No monthly fee on the entry plan. The platform earns when you earn, and not before.';
$string['value1title'] = 'You pay when you sell';
$string['value2note'] = 'You issue the invoice';
$string['value2text'] = 'The charge is created in your own account. The money is yours from the start, and the invoice is issued by you.';
$string['value2title'] = 'The money goes to your account';
$string['value3note'] = 'Nothing to maintain';
$string['value3text'] = 'Enrolment, progress, certificates and reports come from Moodle, used by universities worldwide.';
$string['value3title'] = 'A platform you do not have to build';
$string['value4note'] = 'Separate by design';
$string['value4text'] = 'Your company gets its own course category, its own roles and its own payment account. Your students do not mix with anyone else\'s.';
$string['value4title'] = 'Your own space';
$string['valueeyebrow'] = 'How the partnership works';
$string['valuelead'] = 'The platform earns a share of what you sell, and nothing else. Everything below follows from that.';
$string['valuetitle'] = 'Why sell here';
$string['website'] = 'Website';
