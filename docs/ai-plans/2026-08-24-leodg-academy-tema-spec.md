> **Situação:** descartado · **Início:** 2026-08-24
> **Origem:** `leodg-academy/docs/superpowers/specs/2026-08-24-leodg-academy-moodle-theme-design.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** a spec de design do plano [2026-08-24-leodg-academy-tema-filho-do-moove.md](2026-08-24-leodg-academy-tema-filho-do-moove.md), que não foi executado. A paleta daqui (`#3490dc`) **não é** a que valeu: o design system em vigor é o [`docs/brand/DESIGN.md`](../brand/DESIGN.md), com `#007AFF`.

# LeoDG Academy - Moodle Theme Design Spec

## Overview

Custom Moodle theme for "LeoDG Academy" platform, based on Moove child theme with full visual overhaul and dark mode support. Visual identity derived from [leodg.dev](https://leodg.dev) portfolio.

**Moodle Version:** 5.2.2+ (Build: 20260818)
**Base Theme:** Moove (child theme approach)
**Worktree:** `feature/leodg-academy-theme` at `/home/leodg/localhost/gitworktree-bare-moodle/leodg-academy`
**Design Tool:** Google Stitch for mockups, then implementation

---

## 1. Visual Identity

### 1.1 Color Palette

| Token | Light Mode | Dark Mode | Usage |
|-------|-----------|-----------|-------|
| Primary | `#3490dc` | `#3490dc` | Links, buttons, accents |
| Secondary | `#ffed4a` | `#ffed4a` | Highlights, badges |
| Danger | `#e3342f` | `#e3342f` | Errors, warnings |
| Success | `#38c172` | `#38c172` | Success messages |
| Background | `#f3f4f6` (gray-100) | `#111827` (gray-900) | Page background |
| Surface | `#ffffff` | `#1f2937` (gray-800) | Cards, panels |
| Text Primary | `#111827` (gray-900) | `#f9fafb` (gray-50) | Main text |
| Text Secondary | `#6b7280` (gray-500) | `#9ca3af` (gray-400) | Subtitles, hints |

### 1.2 Typography

- **Font Family:** System font stack (matching portfolio)
  ```
  system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif
  ```
- **Headings:** Bold, slightly tighter letter-spacing
- **Body:** Regular weight, comfortable line-height (1.6)

### 1.3 Dark Mode

- Toggle button in navbar (sun/moon icon)
- Persisted via Moodle user preference
- CSS custom properties for theme switching
- Smooth transitions between modes

---

## 2. Key Pages

### 2.1 Login Page

- **Background:** Custom image (space/astronaut theme from portfolio)
- **Card:** Centered, glass-morphism effect (`bg-white/10`, `backdrop-blur`)
- **Logo:** LeoDG logo prominently displayed
- **Buttons:** Blue primary (`#3490dc`)
- **Layout:** Full viewport height, centered content

### 2.2 Frontpage / Dashboard

- **Hero Section:** Welcome message with gradient overlay
- **Marketing Boxes:** 3-4 boxes for course categories (Moove built-in)
- **Numbers Section:** Active users and courses count
- **Layout:** Clean card-based, responsive grid

### 2.3 Course Pages

- **Sidebar:** Blue-tinted navigation panel
- **Course Cards:** Hover effects, clean design
- **Breadcrumb:** Clear navigation path
- **Content Area:** Wide, readable layout

### 2.4 Footer

- **Social Links:** GitHub, LinkedIn, Email icons
- **Contact Info:** Brief contact details
- **Copyright:** Platform branding

---

## 3. Google Stitch Integration

### 3.1 Workflow

1. Create Stitch project "LeoDG Academy Moodle"
2. Design screens: Login, Frontpage, Course Page (desktop + mobile)
3. Extract design tokens (colors, spacing, typography)
4. Use as reference for theme implementation

### 3.2 Screens to Design

| Screen | Variants | Priority |
|--------|----------|----------|
| Login | Desktop, Mobile | High |
| Frontpage | Desktop, Mobile | High |
| Course Listing | Desktop | Medium |
| Course Content | Desktop, Mobile | Medium |

---

## 4. Implementation Architecture

### 4.1 Theme Structure

```
theme/leodg-academy/
├── config.php              # Theme configuration (parent: moove)
├── lang/
│   └── en/
│       └── theme_leodg-academy.php  # Language strings
├── lib.php                 # Theme functions
├── scss/
│   ├── default.scss        # Main SCSS entry
│   ├── moove/
│   │   ├── _variables.scss # Override Moove variables
│   │   └── _dark-mode.scss # Dark mode styles
│   └── preset/
│       └── leodg-academy.scss  # Custom preset
├── style/
│   └── leodg-academy.css   # Compiled CSS
├── templates/
│   ├── core/
│   │   ├── login.php       # Custom login page
│   │   └── frontpage.php   # Custom frontpage
│   └── theme/
│       └── moove/
│           ├── header.php  # Custom header with logo
│           └── footer.php  # Custom footer with social links
├── pix/
│   ├── logo.svg            # LeoDG logo
│   └── favicon.ico         # Custom favicon
└── db/
    └── install.xml         # Database install (if needed)
```

### 4.2 SCSS Variable Overrides

```scss
// Override Moove variables
$brand-primary: #3490dc;
$secondary-menu-color: #1f2937;
$font-family-sans-serif: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

// Dark mode variables
$dark-bg-primary: #111827;
$dark-bg-secondary: #1f2937;
$dark-text-primary: #f9fafb;
$dark-text-secondary: #9ca3af;
```

### 4.3 Dark Mode Implementation

- CSS custom properties for theme tokens
- `[data-theme="dark"]` selector for dark mode
- Moodle preference storage via `theme_moove` settings
- JavaScript toggle in navbar

---

## 5. Maintenance

- **Upstream Updates:** Child theme approach allows pulling Moove updates
- **Version Control:** All customizations in `feature/leodg-academy-theme` branch
- **Testing:** Verify each Moodle upgrade against child theme

---

## 6. Success Criteria

- [ ] Login page matches portfolio aesthetic
- [ ] Dark mode toggle works correctly
- [ ] All pages responsive (mobile + desktop)
- [ ] Performance not degraded vs base Moove
- [ ] Compatible with Moodle 5.2.2+
- [ ] Brand identity consistent across platform
