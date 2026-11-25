> **Situação:** descartado · **Início:** 2026-08-24
> **Origem:** `leodg-academy/docs/superpowers/plans/2026-08-24-leodg-academy-moodle-theme-implementation.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** **46 passos, nenhum executado.** O caminho escolhido foi outro: o `theme_ldg` nasceu como filho do `theme_moove` e depois **deixou de depender dele** (PR #64), enquanto este plano desenhava um tema filho do Moove com dark mode por *toggle*. Fica registrado pelo que ele descartou, e não pelo que entregou. A spec que o acompanha é a [2026-08-24-leodg-academy-tema-spec.md](2026-08-24-leodg-academy-tema-spec.md).

# LeoDG Academy Moodle Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a custom Moodle child theme of Moove with LeoDG Academy branding, dark mode support, and modern visual design.

**Architecture:** Child theme approach extending Moove, with SCSS variable overrides, custom templates for login/frontpage/header/footer, and CSS custom properties for dark mode toggle.

**Tech Stack:** PHP (Moodle theme system), SCSS/CSS, JavaScript (dark mode toggle), Google Stitch (mockups)

## Global Constraints

- Moodle version: 5.2.2+ (Build: 20260818)
- Parent theme: Moove (must be installed first)
- Visual identity: From leodg.dev portfolio (blue #3490dc primary)
- Dark mode: Toggle in navbar, persisted via Moodle preference
- Worktree: `feature/leodg-academy-theme` at `/home/leodg/localhost/gitworktree-bare-moodle/leodg-academy`

---

## Phase 1: Google Stitch Mockups

### Task 1: Create Stitch Project and Design Login Page

**Files:**
- Stitch project: "LeoDG Academy Moodle"
- Screen: Login page (desktop + mobile)

**Interfaces:**
- Consumes: Visual identity from portfolio (colors, fonts)
- Produces: Login page mockup for implementation reference

- [ ] **Step 1: Create Stitch project**

Navigate to https://stitch.withgoogle.com and create a new project named "LeoDG Academy Moodle".

- [ ] **Step 2: Design login page - desktop variant**

Create a login screen with:
- Full viewport height
- Background: space/astronaut theme image (similar to portfolio hero)
- Centered card with glass-morphism effect (semi-transparent white, backdrop blur)
- LeoDG logo at top of card
- Username/password fields
- Blue (#3490dc) login button
- "Don't have an account?" link below

- [ ] **Step 3: Design login page - mobile variant**

Create responsive version:
- Same layout, optimized for mobile
- Card takes full width with padding
- Smaller logo

- [ ] **Step 4: Extract design tokens**

Document colors, spacing, typography from the Stitch design:
- Primary: #3490dc
- Background: #111827 (dark) / #f3f4f6 (light)
- Card: rgba(255,255,255,0.1) with backdrop-blur
- Font: system-ui stack

---

### Task 2: Design Frontpage in Stitch

**Files:**
- Screen: Frontpage/dashboard (desktop + mobile)

**Interfaces:**
- Consumes: Login page design tokens
- Produces: Frontpage mockup for implementation reference

- [ ] **Step 1: Design frontpage - desktop variant**

Create a dashboard screen with:
- Header: Logo left, navigation right, dark mode toggle
- Hero section: Welcome message with gradient overlay
- Marketing boxes: 3 cards for course categories
- Numbers section: Users and courses count
- Footer: Social links, copyright

- [ ] **Step 2: Design frontpage - mobile variant**

Create responsive version:
- Stacked layout
- Hamburger menu for navigation
- Cards full width

---

### Task 3: Design Course Pages in Stitch

**Files:**
- Screen: Course listing (desktop)
- Screen: Course content (desktop + mobile)

**Interfaces:**
- Consumes: Frontpage design tokens
- Produces: Course page mockups for implementation reference

- [ ] **Step 1: Design course listing page**

Create a course listing screen with:
- Sidebar navigation (blue-tinted)
- Course cards in grid layout
- Each card: thumbnail, title, description, progress indicator

- [ ] **Step 2: Design course content page**

Create a course content screen with:
- Sidebar with section navigation
- Main content area with activities
- Breadcrumb navigation
- Clean, readable layout

---

## Phase 2: Theme Scaffolding

### Task 4: Download and Install Moove Theme

**Files:**
- Download: Moove theme from Moodle plugins
- Install: `theme/moove/` directory

**Interfaces:**
- Consumes: Moodle 5.2.2+ core
- Produces: Working Moove theme as base

- [ ] **Step 1: Download Moove theme**

```bash
cd /home/leodg/localhost/gitworktree-bare-moodle/leodg-academy
# Download Moove from Moodle plugins directory
# Or clone from GitHub if available
```

- [ ] **Step 2: Extract to theme directory**

```bash
# Extract moove to theme/moove/
ls theme/moove/config.php  # Verify installation
```

- [ ] **Step 3: Verify Moove works**

Check that `theme/moove/config.php` exists and has proper Moodle theme structure.

---

### Task 5: Create Child Theme Directory Structure

**Files:**
- Create: `theme/leodg-academy/` directory
- Create: `theme/leodg-academy/config.php`
- Create: `theme/leodg-academy/lang/en/theme_leodg-academy.php`

**Interfaces:**
- Consumes: Moove parent theme
- Produces: Working child theme skeleton

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p theme/leodg-academy/{lang/en,scss/moove,scss/preset,style,templates/core,templates/theme/moove,pix,db}
```

- [ ] **Step 2: Create config.php**

```php
<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'leodg-academy';
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];
$THEME->usefallback = false;
$THEME->scss = function($theme) {
    return theme_leodg_academy_get_main_scss_content($theme);
};

$THEME->layouts = [
    'base' => ['file' => 'drawers.php', 'regions' => []],
    'standard' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'course' => ['file' => 'course.php', 'regions' => ['side-pre', 'content'], 'defaultregion' => 'side-pre', 'options' => ['langmenu' => true]],
    'frontpage' => ['file' => 'frontpage.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre', 'options' => ['nonavbar' => true]],
    'login' => ['file' => 'login.php', 'regions' => [], 'options' => ['langmenu' => true]],
    // ... other layouts from Moove
];

$THEME->parents = ['moove'];
$THEME->enable_dock = false;
$THEME->extrascsscallback = 'theme_leodg_academy_get_extra_scss';
$THEME->prescsscallback = 'theme_leodg_academy_get_pre_scss';
$THEME->precompiledcsscallback = 'theme_leodg_academy_get_precompiled_css';
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = ['notitle' => true];
```

- [ ] **Step 3: Create language file**

```php
<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LeoDG Academy';
$string['configtitle'] = 'LeoDG Academy';
$string['choosereadme'] = 'LeoDG Academy theme for Moodle - Custom theme based on Moove with dark mode support.';
```

- [ ] **Step 4: Create lib.php skeleton**

```php
<?php
defined('MOODLE_INTERNAL') || die();

function theme_leodg_academy_get_main_scss_content($theme) {
    // Will be implemented in Task 7
}

function theme_leodg_academy_get_extra_scss($theme) {
    // Will be implemented in Task 8
}

function theme_leodg_academy_get_pre_scss($theme) {
    // Will be implemented in Task 9
}

function theme_leodg_academy_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/leodg-academy/style/leodg-academy.css');
}
```

---

## Phase 3: SCSS and Styling

### Task 6: Create SCSS Variable Overrides

**Files:**
- Create: `theme/leodg-academy/scss/moove/_variables.scss`

**Interfaces:**
- Consumes: Moove SCSS variables
- Produces: Overridden variables for LeoDG branding

- [ ] **Step 1: Create _variables.scss**

```scss
// LeoDG Academy Color Palette
$brand-primary: #3490dc;
$brand-secondary: #ffed4a;
$brand-danger: #e3342f;
$brand-success: #38c172;

// Background colors
$body-bg: #f3f4f6;
$card-bg: #ffffff;
$sidebar-bg: #1f2937;

// Text colors
$text-primary: #111827;
$text-secondary: #6b7280;

// Dark mode variables
$dark-bg-primary: #111827;
$dark-bg-secondary: #1f2937;
$dark-text-primary: #f9fafb;
$dark-text-secondary: #9ca3af;

// Typography
$font-family-sans-serif: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

// Spacing
$border-radius: 0.5rem;
$spacing-unit: 8px;
```

---

### Task 7: Create Main SCSS Entry Point

**Files:**
- Create: `theme/leodg-academy/scss/default.scss`

**Interfaces:**
- Consumes: _variables.scss overrides
- Produces: Compiled CSS for theme

- [ ] **Step 1: Create default.scss**

```scss
// Import Moove base styles
@import '../../moove/scss/default';

// Import LeoDG overrides
@import 'moove/variables';

// Import dark mode
@import 'moove/dark-mode';

// Custom styles for LeoDG Academy
body {
    font-family: $font-family-sans-serif;
    background-color: $body-bg;
    color: $text-primary;
}

// Glass morphism card effect
.glass-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: $border-radius;
}
```

---

### Task 8: Implement Dark Mode CSS

**Files:**
- Create: `theme/leodg-academy/scss/moove/_dark-mode.scss`

**Interfaces:**
- Consumes: Dark mode variables
- Produces: CSS custom properties and dark mode styles

- [ ] **Step 1: Create _dark-mode.scss**

```scss
// CSS Custom Properties for theme switching
:root {
    --bg-primary: #{$body-bg};
    --bg-secondary: #{$card-bg};
    --text-primary: #{$text-primary};
    --text-secondary: #{$text-secondary};
    --brand-primary: #{$brand-primary};
}

[data-theme="dark"] {
    --bg-primary: #{$dark-bg-primary};
    --bg-secondary: #{$dark-bg-secondary};
    --text-primary: #{$dark-text-primary};
    --text-secondary: #{$dark-text-secondary};
}

// Apply CSS variables
body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
}

.card, .panel, .well {
    background-color: var(--bg-secondary);
}

// Dark mode toggle button styles
.theme-toggle {
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
    
    &:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    svg {
        width: 24px;
        height: 24px;
    }
}
```

---

### Task 9: Update lib.php SCSS Functions

**Files:**
- Modify: `theme/leodg-academy/lib.php`

**Interfaces:**
- Consumes: SCSS files from Tasks 6-8
- Produces: Working SCSS compilation

- [ ] **Step 1: Implement get_main_scss_content**

```php
function theme_leodg_academy_get_main_scss_content($theme) {
    global $CFG;
    
    $scss = '';
    
    // Load Moove base SCSS
    $moovevariables = file_get_contents($CFG->dirroot . '/theme/moove/scss/moove/_variables.scss');
    $moove = file_get_contents($CFG->dirroot . '/theme/moove/scss/default.scss');
    $security = file_get_contents($CFG->dirroot . '/theme/moove/scss/moove/_security.scss');
    
    // Load LeoDG overrides
    $leodgvariables = file_get_contents($CFG->dirroot . '/theme/leodg-academy/scss/moove/_variables.scss');
    $leodgdark = file_get_contents($CFG->dirroot . '/theme/leodg-academy/scss/moove/_dark-mode.scss');
    $leodgdefault = file_get_contents($CFG->dirroot . '/theme/leodg-academy/scss/default.scss');
    
    // Combine: LeoDG overrides first, then Moove base, then LeoDG custom
    $allscss = $leodgvariables . "\n" . $moovevariables . "\n" . $scss . "\n" . $moove . "\n" . $leodgdark . "\n" . $leodgdefault . "\n" . $security;
    
    return $allscss;
}
```

- [ ] **Step 2: Implement get_pre_scss**

```php
function theme_leodg_academy_get_pre_scss($theme) {
    $scss = '';
    $configurable = [
        'brandcolor' => ['brand-primary'],
        'fontsite' => 'font-family-sans-serif',
    ];
    
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) continue;
        
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }
    
    return $scss;
}
```

- [ ] **Step 3: Implement get_extra_scss**

```php
function theme_leodg_academy_get_extra_scss($theme) {
    $content = '';
    
    // Login background
    $loginbgimgurl = $theme->setting_file_url('loginbgimg', 'loginbgimg');
    if (empty($loginbgimgurl)) {
        $loginbgimgurl = new \moodle_url('/theme/leodg-academy/pix/loginbg.png');
    }
    
    $content .= 'body.pagelayout-login #page { ';
    $content .= "background-image: url('$loginbgimgurl'); background-size: cover;";
    $content .= ' }';
    
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}
```

---

## Phase 4: Template Overrides

### Task 10: Create Custom Login Template

**Files:**
- Create: `theme/leodg-academy/templates/login.php`

**Interfaces:**
- Consumes: Login page design from Stitch
- Produces: Custom login page with glass-morphism effect

- [ ] **Step 1: Create login.php template**

```php
<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$site = get_site();
$context = context_system::instance();
$signup = $CFG->enablesignupes;

$BODYCLASS = 'pagelayout-login';

$ kêncode = '';
if (!empty($SESSION->wantsurl)) {
    $kêncode = new \moodle_url($SESSION->wantsurl);
}

$PAGE->set_url(new \moodle_url('/login/index.php'));
$PAGE->set_context($context);
$PAGE->set_title($site->fullname);
$PAGE->set_body_class('pagelayout-login');
$PAGE->add_body_class('loginpage');
$PAGE->set_heading($site->fullname);
$PAGE->set_headingmenu('');
$PAGE->set_primary_active_tab_node(null);

echo $OUTPUT->header();
?>

<div class="login-container">
    <div class="glass-card login-card">
        <div class="login-logo">
            <?php echo $OUTPUT->pix.logo, theme_leodg-academy'); ?>
        </div>
        
        <h2><?php echo get_string('login'); ?></h2>
        
        <?php
        $form = new \auth_login_form();
        $form->set_target($kêncode ? $kêncode->out() : new \moodle_url('/'));
        $form->display();
        ?>
        
        <?php if ($signup) { ?>
            <div class="login-signup">
                <a href="<?php echo new \moodle_url('/login/signup.php'); ?>">
                    <?php echo get_string('donthaveanaccount'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
?>
```

- [ ] **Step 2: Create login.css styles**

Add to default.scss:

```scss
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 2rem;
}

.login-card {
    max-width: 400px;
    width: 100%;
    padding: 2rem;
    text-align: center;
}

.login-logo {
    margin-bottom: 1.5rem;
    
    img {
        max-width: 200px;
        height: auto;
    }
}

.login-signup {
    margin-top: 1.5rem;
    
    a {
        color: $brand-primary;
        text-decoration: none;
        
        &:hover {
            text-decoration: underline;
        }
    }
}
```

---

### Task 11: Create Custom Header Template

**Files:**
- Create: `theme/leodg-academy/templates/theme/moove/header.php`

**Interfaces:**
- Consumes: Header design from Stitch
- Produces: Custom header with logo and dark mode toggle

- [ ] **Step 1: Create header.php template**

```php
<?php
// Custom header for LeoDG Academy
$theme = theme_config::load('leodg-academy');
$logo = $theme->setting_file_url('logo', 'logo');
?>

<header class="leodg-header">
    <div class="header-container">
        <div class="header-logo">
            <a href="<?php echo new \moodle_url('/'); ?>">
                <?php if ($logo) { ?>
                    <img src="<?php echo $logo; ?>" alt="LeoDG Academy" />
                <?php } else { ?>
                    <span class="logo-text">LeoDG Academy</span>
                <?php } ?>
            </a>
        </div>
        
        <nav class="header-nav">
            <?php echo $OUTPUT->navigation_menu(); ?>
        </nav>
        
        <div class="header-actions">
            <!-- Dark mode toggle -->
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
            
            <?php echo $OUTPUT->user_menu(); ?>
        </div>
    </div>
</header>
```

- [ ] **Step 2: Create header.css styles**

Add to default.scss:

```scss
.leodg-header {
    background-color: var(--bg-secondary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1.5rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1400px;
    margin: 0 auto;
}

.header-logo {
    img {
        height: 40px;
        width: auto;
    }
    
    .logo-text {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
}

.header-nav {
    flex: 1;
    display: flex;
    justify-content: center;
    
    a {
        color: var(--text-primary);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: $border-radius;
        transition: background-color 0.2s;
        
        &:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    }
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}
```

---

### Task 12: Create Custom Footer Template

**Files:**
- Create: `theme/leodg-academy/templates/theme/moove/footer.php`

**Interfaces:**
- Consumes: Footer design from Stitch
- Produces: Custom footer with social links

- [ ] **Step 1: Create footer.php template**

```php
<?php
// Custom footer for LeoDG Academy
?>

<footer class="leodg-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-brand">
                <h3>LeoDG Academy</h3>
                <p>Empowering learners through technology</p>
            </div>
            
            <div class="footer-links">
                <h4>Connect</h4>
                <ul class="social-links">
                    <li>
                        <a href="https://github.com/leonardodg" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-github"></i> GitHub
                        </a>
                    </li>
                    <li>
                        <a href="https://www.linkedin.com/in/le0dg" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-linkedin"></i> LinkedIn
                        </a>
                    </li>
                    <li>
                        <a href="https://leodg.dev" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-globe"></i> Website
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="footer-contact">
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> contact@leodg.dev</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> LeoDG Academy. Built with Moodle.</p>
        </div>
    </div>
</footer>
```

- [ ] **Step 2: Create footer.css styles**

Add to default.scss:

```scss
.leodg-footer {
    background-color: $dark-bg-primary;
    color: $dark-text-primary;
    padding: 3rem 1.5rem 1.5rem;
    margin-top: 3rem;
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-brand {
    h3 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    p {
        color: $dark-text-secondary;
    }
}

.footer-links {
    h4 {
        margin-bottom: 1rem;
    }
    
    .social-links {
        list-style: none;
        padding: 0;
        
        li {
            margin-bottom: 0.5rem;
        }
        
        a {
            color: $dark-text-secondary;
            text-decoration: none;
            transition: color 0.2s;
            
            &:hover {
                color: $brand-primary;
            }
            
            i {
                margin-right: 0.5rem;
            }
        }
    }
}

.footer-contact {
    h4 {
        margin-bottom: 1rem;
    }
    
    p {
        color: $dark-text-secondary;
        
        i {
            margin-right: 0.5rem;
        }
    }
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1.5rem;
    text-align: center;
    color: $dark-text-secondary;
}
```

---

### Task 13: Create Custom Frontpage Template

**Files:**
- Create: `theme/leodg-academy/templates/frontpage.php`

**Interfaces:**
- Consumes: Frontpage design from Stitch
- Produces: Custom frontpage with hero section

- [ ] **Step 1: Create frontpage.php template**

```php
<?php
require_once(__DIR__ . '/../../config.php');

$site = get_site();
$context = context_system::instance();

$PAGE->set_url(new \moodle_url('/'));
$PAGE->set_context($context);
$PAGE->set_title($site->fullname);
$PAGE->set_heading($site->fullname);

echo $OUTPUT->header();
?>

<div class="frontpage-hero">
    <div class="hero-content">
        <h1>Welcome to <?php echo $site->fullname; ?></h1>
        <p>Empowering learners through technology</p>
        <?php if (!isloggedin()) { ?>
            <a href="<?php echo new \moodle_url('/login/index.php'); ?>" class="btn btn-primary">
                Get Started
            </a>
        <?php } ?>
    </div>
</div>

<div class="frontpage-content">
    <?php
    // Marketing boxes
    if (theme_leodg_academy_setting('displaymarketingboxes')) {
        echo $OUTPUT->marketing_boxes();
    }
    
    // Numbers section
    if (theme_leodg_academy_setting('numbersfrontpage')) {
        echo $OUTPUT->numbers_section();
    }
    ?>
</div>

<?php
echo $OUTPUT->footer();
?>
```

- [ ] **Step 2: Create frontpage.css styles**

Add to default.scss:

```scss
.frontpage-hero {
    background: linear-gradient(135deg, $brand-primary 0%, darken($brand-primary, 20%) 100%);
    color: white;
    padding: 6rem 2rem;
    text-align: center;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    
    h1 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    p {
        font-size: 1.25rem;
        margin-bottom: 2rem;
        opacity: 0.9;
    }
    
    .btn-primary {
        background-color: white;
        color: $brand-primary;
        padding: 0.75rem 2rem;
        border-radius: $border-radius;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s, box-shadow 0.2s;
        
        &:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    }
}

.frontpage-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 3rem 2rem;
}
```

---

## Phase 5: Dark Mode JavaScript

### Task 14: Implement Dark Mode Toggle

**Files:**
- Create: `theme/leodg-academy/javascript/darkmode.js`

**Interfaces:**
- Consumes: CSS custom properties from Task 8
- Produces: Working dark mode toggle with persistence

- [ ] **Step 1: Create darkmode.js**

```javascript
(function() {
    'use strict';
    
    const THEME_KEY = 'leodg-academy-theme';
    const DARK_THEME = 'dark';
    const LIGHT_THEME = 'light';
    
    function getPreferredTheme() {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? DARK_THEME : LIGHT_THEME;
    }
    
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        
        // Update toggle button icons
        const sunIcon = document.querySelector('.sun-icon');
        const moonIcon = document.querySelector('.moon-icon');
        
        if (sunIcon && moonIcon) {
            if (theme === DARK_THEME) {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            }
        }
    }
    
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === DARK_THEME ? LIGHT_THEME : DARK_THEME;
        setTheme(next);
    }
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        setTheme(getPreferredTheme());
        
        const toggleButton = document.getElementById('theme-toggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', toggleTheme);
        }
    });
    
    // Listen for system preference changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem(THEME_KEY)) {
            setTheme(e.matches ? DARK_THEME : LIGHT_THEME);
        }
    });
})();
```

---

## Phase 6: Logo and Assets

### Task 15: Create Logo and Favicon

**Files:**
- Create: `theme/leodg-academy/pix/logo.svg`
- Create: `theme/leodg-academy/pix/favicon.ico`
- Create: `theme/leodg-academy/pix/loginbg.png`

**Interfaces:**
- Consumes: Portfolio visual identity
- Produces: Theme assets

- [ ] **Step 1: Create logo.svg**

Create a simplified version of the LeoDG logo from the portfolio. The logo should:
- Be SVG format for scalability
- Work on both light and dark backgrounds
- Include "LeoDG Academy" text

- [ ] **Step 2: Create favicon.ico**

- Create a favicon from the logo (16x16, 32x32 sizes)
- Can use online favicon generator

- [ ] **Step 3: Create login background image**

- Use space/astronaut theme similar to portfolio hero
- Can be a dark gradient or actual image
- Recommended size: 1920x1080

---

## Phase 7: Theme Settings

### Task 16: Add Theme Settings to config.php

**Files:**
- Modify: `theme/leodg-academy/config.php`

**Interfaces:**
- Consumes: Moove settings pattern
- Produces: Theme settings page in Moodle admin

- [ ] **Step 1: Add settings to config.php**

Add to config.php:

```php
// Theme settings
$THEME->settings = new stdClass();
$THEME->settings->brandcolor = '#3490dc';
$THEME->settings->fontsite = 'Moodle';
$THEME->settings->displaymarketingboxes = true;
$THEME->settings->numbersfrontpage = true;
$THEME->settings->sliderfrontpageloggedin = false;
```

- [ ] **Step 2: Create settings form**

Create `theme/leodg-academy/settings.php` with form elements for:
- Logo upload
- Favicon upload
- Login background image
- Brand color picker
- Marketing boxes toggle
- Numbers section toggle

---

## Phase 8: Testing and Polish

### Task 17: Test Theme Installation

**Files:**
- Test: All theme files
- Verify: Moodle admin theme selector

**Interfaces:**
- Consumes: Complete theme from Tasks 4-16
- Produces: Working theme in Moodle

- [ ] **Step 1: Verify theme structure**

```bash
cd /home/leodg/localhost/gitworktree-bare-moodle/leodg-academy
ls -la theme/leodg-academy/
# Should show: config.php, lib.php, lang/, scss/, style/, templates/, pix/
```

- [ ] **Step 2: Check theme in Moodle admin**

1. Login to Moodle as admin
2. Go to Site administration > Appearance > Themes
3. Verify "LeoDG Academy" appears in theme list
4. Select it as current theme

- [ ] **Step 3: Test all pages**

1. Login page - verify glass-morphism effect
2. Frontpage - verify hero section and marketing boxes
3. Course pages - verify sidebar and content layout
4. Dark mode toggle - verify it works and persists

---

### Task 18: Mobile Responsive Testing

**Files:**
- Test: All templates on mobile viewport
- Verify: Responsive design

**Interfaces:**
- Consumes: Theme from Task 17
- Produces: Mobile-verified theme

- [ ] **Step 1: Test login page on mobile**

- Open browser dev tools
- Set viewport to mobile (375px width)
- Verify login card is responsive
- Verify logo and buttons are properly sized

- [ ] **Step 2: Test frontpage on mobile**

- Verify hero section stacks properly
- Verify marketing boxes are single column
- Verify navigation collapses to hamburger menu

- [ ] **Step 3: Test course pages on mobile**

- Verify sidebar collapses or becomes overlay
- Verify course cards are single column
- Verify content is readable

---

### Task 19: Performance Optimization

**Files:**
- Optimize: CSS compilation
- Optimize: Image assets

**Interfaces:**
- Consumes: Theme from Task 18
- Produces: Optimized theme

- [ ] **Step 1: Minify CSS**

```bash
# Compile and minify SCSS
cd theme/leodg-academy
# Use Moodle's SCSS compiler or external tool
```

- [ ] **Step 2: Optimize images**

- Compress logo.svg
- Optimize login background image
- Use appropriate formats (SVG for logo, PNG/JPG for background)

- [ ] **Step 3: Test performance**

- Check page load times
- Verify no render-blocking resources
- Test with browser dev tools network tab

---

### Task 20: Documentation and Commit

**Files:**
- Create: `theme/leodg-academy/README.md`
- Commit: All theme files

**Interfaces:**
- Consumes: Complete theme from Task 19
- Produces: Documented, committed theme

- [ ] **Step 1: Create README.md**

```markdown
# LeoDG Academy Moodle Theme

Custom Moodle theme based on Moove with dark mode support.

## Features

- Modern visual design inspired by leodg.dev
- Dark mode toggle with persistence
- Glass-morphism login page
- Responsive design for all devices
- Custom header and footer

## Installation

1. Download Moove theme (parent)
2. Place this theme in `theme/leodg-academy/`
3. Login to Moodle admin
4. Go to Site administration > Appearance > Themes
5. Select "LeoDG Academy" as current theme

## Customization

- Upload logo in Theme settings
- Customize colors in SCSS variables
- Modify templates in `templates/` directory

## Development

- SCSS files in `scss/` directory
- Compile with `grunt compile` or Moodle's built-in compiler
- Test changes in development environment
```

- [ ] **Step 2: Commit all files**

```bash
cd /home/leodg/localhost/gitworktree-bare-moodle/leodg-academy
git add theme/leodg-academy/
git commit -m "feat: add LeoDG Academy Moodle theme

- Child theme of Moove with full visual overhaul
- Dark mode support with toggle
- Custom login, frontpage, header, footer templates
- SCSS variable overrides for LeoDG branding
- Responsive design for mobile and desktop"
```

---

## Summary

This plan creates a complete Moodle child theme with:
- **Phase 1:** Google Stitch mockups (Tasks 1-3)
- **Phase 2:** Theme scaffolding (Tasks 4-5)
- **Phase 3:** SCSS and styling (Tasks 6-9)
- **Phase 4:** Template overrides (Tasks 10-13)
- **Phase 5:** Dark mode JavaScript (Task 14)
- **Phase 6:** Logo and assets (Task 15)
- **Phase 7:** Theme settings (Task 16)
- **Phase 8:** Testing and polish (Tasks 17-20)

Total: 20 tasks across 8 phases.
