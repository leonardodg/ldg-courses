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
 * Strings do tema LDG.
 *
 * ORDEM ALFABETICA OBRIGATORIA. Inserir por ancora quebra o phpcs; depois de
 * acrescentar uma chave, reordene o arquivo inteiro e confira a paridade com
 * pt_br e es.
 *
 * @package    theme_ldg
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessibility'] = 'Accessibility';
$string['accessibility:decreasefont'] = 'Decrease font size';
$string['accessibility:fontsize'] = 'Font size';
$string['accessibility:increasefont'] = 'Increase font size';
$string['accessibility:resetfont'] = 'Reset font size';
$string['accessibility:resetsitecolor'] = 'Reset site color';
$string['accessibility:sitecolor'] = 'Site color';
$string['accessibility:sitecolor2'] = 'Low contrast 1';
$string['accessibility:sitecolor3'] = 'Low contrast 2';
$string['accessibility:sitecolor4'] = 'High contrast';
$string['accessibilitybar'] = 'Accessibility toolbar';
$string['accessibilitybar_help'] = 'The toolbar adds controls to increase or decrease the font size and to switch the site contrast. It stays hidden until you turn it on here, so it does not take space from people who do not use it. Your font size and contrast choices are kept even if you turn the toolbar off later.';
$string['accessibilitybardesc'] = 'Show the font size and contrast controls at the top of every page';
$string['advancedsettings'] = 'Advanced';
$string['brandcolor'] = 'Brand colour';
$string['brandcolordesc'] = 'The accent colour used by buttons, links, focus rings and progress bars.';
$string['brandsettings'] = 'Brand';
$string['choosereadme'] = 'LDG is the platform theme, developed by LeoDG. It builds directly on Boost, the Moodle core theme, and applies the LDG design system: dark by default with a light mode where deep blue predominates, electric blue accent and the Inter typeface. It adds a collapsible side navigation menu, an accessibility bar and the partner landing page as the site home.';
$string['closedrawer'] = 'Close the navigation menu';
$string['colormodedark'] = 'Dark';
$string['colormodelight'] = 'Light';
$string['colormodetoggle'] = 'Switch between light and dark';
$string['configtitle'] = 'LDG';
$string['contactus'] = 'Contact us';
$string['defaultcolormode'] = 'Default colour mode';
$string['defaultcolormodedesc'] = 'The mode used by anyone who has not chosen one yet, including anonymous visitors. The brand design system is dark first, so dark is the default. This does not disable the light mode: it only says where everyone starts.';
$string['enablecolormodetoggle'] = 'Show the colour mode switch';
$string['enablecolormodetoggledesc'] = 'Lets a logged in user switch between light and dark from the navbar. The choice is stored as a user preference, so anonymous visitors always get the default mode above.';
$string['facebook'] = 'Facebook URL';
$string['facebookdesc'] = 'The full URL of the platform Facebook page.';
$string['favicon'] = 'Favicon';
$string['favicondesc'] = 'The icon shown in the browser tab.';
$string['followus'] = 'Follow us';
$string['fontsite'] = 'Site font';
$string['fontsitedesc'] = 'The font loaded from Google Fonts. Choose Moodle to keep the default font and load nothing from outside.';
$string['footercredit'] = 'Built by {$a}';
$string['footerlegal'] = 'Legal';
$string['footerlinks'] = 'Footer links';
$string['footerpartner'] = 'Become a partner';
$string['footerplatform'] = 'Platform';
$string['footersettings'] = 'Footer';
$string['footersupport'] = 'Help and support';
$string['googleanalytics'] = 'Google Analytics measurement ID';
$string['googleanalyticsdesc'] = 'A GA4 measurement ID such as G-XXXXXXXXXX. Leave empty to load no tracking script at all.';
$string['instagram'] = 'Instagram URL';
$string['instagramdesc'] = 'The full URL of the platform Instagram profile.';
$string['linkedin'] = 'LinkedIn URL';
$string['linkedindesc'] = 'The full URL of the platform LinkedIn page.';
$string['loginbg'] = 'Login background image';
$string['loginbgdesc'] = 'The image behind the login screen. A dark gradient is applied on top so the form stays readable. Leave empty to use the bundled hero image.';
$string['loginheadline'] = 'Sell your courses. We take care of the platform.';
$string['loginlead'] = 'Publish, sell and deliver your courses with your own payment account. You keep the revenue; we only take the agreed commission.';
$string['logo'] = 'Logo';
$string['logodark'] = 'Dark mode logo';
$string['logodarkdesc'] = 'The logo used when the site renders in dark mode. Falls back to the main logo when empty.';
$string['logodesc'] = 'The logo shown in the navbar.';
$string['mail'] = 'Contact email';
$string['maildesc'] = 'The email address shown in the footer.';
$string['mobile'] = 'Contact phone';
$string['mobiledesc'] = 'The phone number shown in the footer.';
$string['navmenugroupaccount'] = 'Account';
$string['navmenugroupnavigation'] = 'Navigation';
$string['navmenulabel'] = 'Side navigation';
$string['navmenutoggle'] = 'Collapse or expand the side menu';
$string['pinterest'] = 'Pinterest URL';
$string['pinterestdesc'] = 'The full URL of the platform Pinterest profile.';
$string['pluginname'] = 'LDG';
$string['preset'] = 'Theme preset';
$string['presetdesc'] = 'The base preset compiled before the LDG tokens. Changing it repaints the whole site.';
$string['presetfiles'] = 'Additional theme presets';
$string['presetfilesdesc'] = 'Upload your own SCSS preset files to make them available in the list above.';
$string['privacy:metadata:preference:accessibilitybar'] = 'Whether the user chose to show the accessibility bar.';
$string['privacy:metadata:preference:darkmode'] = 'Whether the user chose dark mode.';
$string['privacy:metadata:preference:fontsizeclass'] = 'The font size the user chose in the accessibility bar.';
$string['privacy:metadata:preference:navmenucollapsed'] = 'Whether the user left the side menu collapsed.';
$string['privacy:metadata:preference:sitecolorclass'] = 'The contrast the user chose in the accessibility bar.';
$string['privacy:preference:accessibilitybar'] = 'You chose to show the accessibility bar: {$a}';
$string['privacy:preference:darkmode'] = 'You chose dark mode: {$a}';
$string['privacy:preference:fontsizeclass'] = 'The font size you chose in the accessibility bar: {$a}';
$string['privacy:preference:navmenucollapsed'] = 'You left the side menu collapsed: {$a}';
$string['privacy:preference:sitecolorclass'] = 'The contrast you chose in the accessibility bar: {$a}';
$string['rawscss'] = 'Raw SCSS';
$string['rawscssdesc'] = 'SCSS appended at the very end of the compilation. It wins over everything else, which is what makes it the escape hatch when something needs fixing before the next deploy.';
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspredesc'] = 'SCSS prepended before the compilation. Use it to redefine variables; use Raw SCSS for rules.';
$string['secondarymenucolor'] = 'Secondary menu colour';
$string['secondarymenucolordesc'] = 'The background colour of the secondary navigation.';
$string['telegram'] = 'Telegram';
$string['telegramdesc'] = 'The Telegram username or phone number, without the at sign.';
$string['themedevelopedby'] = 'Theme developed by';
$string['tiktok'] = 'TikTok URL';
$string['tiktokdesc'] = 'The full URL of the platform TikTok profile.';
$string['twitter'] = 'X (Twitter) URL';
$string['twitterdesc'] = 'The full URL of the platform X profile.';
$string['website'] = 'Website';
$string['websitedesc'] = 'The institutional website shown in the footer.';
$string['whatsapp'] = 'WhatsApp';
$string['whatsappdesc'] = 'The WhatsApp number with country and area code, digits only.';
$string['youtube'] = 'YouTube URL';
$string['youtubedesc'] = 'The full URL of the platform YouTube channel.';
