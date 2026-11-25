---
name: LeoDG Portfolio
colors:
  surface: '#131315'
  surface-dim: '#131315'
  surface-bright: '#39393b'
  surface-container-lowest: '#0e0e10'
  surface-container-low: '#1b1b1d'
  surface-container: '#1f1f21'
  surface-container-high: '#2a2a2b'
  surface-container-highest: '#353436'
  on-surface: '#e4e2e4'
  on-surface-variant: '#c6c6cd'
  inverse-surface: '#e4e2e4'
  inverse-on-surface: '#303032'
  outline: '#909097'
  outline-variant: '#45464d'
  surface-tint: '#bec6e0'
  primary: '#bec6e0'
  on-primary: '#283044'
  primary-container: '#0f172a'
  on-primary-container: '#798098'
  inverse-primary: '#565e74'
  secondary: '#adc6ff'
  on-secondary: '#002e6a'
  secondary-container: '#0566d9'
  on-secondary-container: '#e6ecff'
  tertiary: '#dec29a'
  on-tertiary: '#3e2d11'
  tertiary-container: '#231500'
  on-tertiary-container: '#957d5a'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#d8e2ff'
  secondary-fixed-dim: '#adc6ff'
  on-secondary-fixed: '#001a42'
  on-secondary-fixed-variant: '#004395'
  tertiary-fixed: '#fcdeb5'
  tertiary-fixed-dim: '#dec29a'
  on-tertiary-fixed: '#271901'
  on-tertiary-fixed-variant: '#574425'
  background: '#131315'
  on-background: '#e4e2e4'
  surface-variant: '#353436'
  text-main: '#f8fafc'
  text-muted: '#94a3b8'
  card-surface: '#1e293b'
  accent-indigo: '#6366f1'
typography:
  headline-xl:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.1'
  headline-xl-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 36px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.625'
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.025em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 1.5rem
  margin-mobile: 1rem
  section-gap: 5rem
---

# LeoDG Portfolio - Design System
Version: 1.0.0
Website: https://www.leodg.dev/
## 1. Introduction & Overview
This Design System defines the core visual and architectural building blocks for LeoDG's personal portfolio. It mirrors the exact production components deployed on GitHub Pages, ensuring consistency across typography, color theory, layout components, and brand assets.
---
## 2. Brand Identity & Assets
The brand identity relies on a clean, developer-focused aesthetic blending dark/space-themed elements with high-contrast structural typography.
- **Logo Brand (Black Variant - Primary):** `https://github.com/leonardodg/website/blob/main/src/images/brand-black.svg`
- **Logo Brand (White Variant):** `https://github.com/leonardodg/website/blob/main/src/images/brand.svg`
- **Avatar Profile:** `https://github.com/leonardodg/website/blob/main/src/images/avatar.png`
- **Hero Backgrounds:** 
  - Large: `https://github.com/leonardodg/website/blob/main/src/images/bg-hero-large.jpg`
  - Extra Large: `https://github.com/leonardodg/website/blob/main/src/images/bg-hero-xlarge.png`
---
## 3. Color Palette
The portfolio implements a clean, modern color scheme optimized for readability and dark-mode elements typical of modern developer portfolios.
| Role | Color Hex / Description | Usage |
| :--- | :--- | :--- |
| **Primary Base** | `#0f172a` (Slate 900 equivalent) | Background surfaces, structural layouts |
| **Text Main** | `#f8fafc` (Slate 50 equivalent) | High-emphasis body and header text |
| **Text Muted** | `#94a3b8` (Slate 400 equivalent) | Secondary paragraphs, timelines, metadata |
| **Accent / Brand** | Tailwind Blue / Indigo scale | Interactive elements, links, tech badges |
| **Card Surface** | `#1e293b` (Slate 800 equivalent) | Project cards, profile containers |
---
## 4. Typography
Powered by clean sans-serif system fonts optimized via Tailwind CSS.
- **Headings (H1, H2, H3, H4, H5):** Bold, high weight hierarchy (`font-bold`), crisp tracking.
- **Body Copy:** Fluid, high readability line-height (`leading-relaxed`), optimized contrast against background cards.
- **Tags & Badges:** Uppercase or regular small caps used for technology chips (e.g., *Tailwindcss, DevOps, AWS, Python*).
---
## 5. UI Components
### 5.1 Hero Section
- **Visual Structure:** Immersive space/astronaut themed background (`bg-hero-large.jpg` / `bg-hero-xlarge.png`) with responsive `srcset` scaling (480w, 800w, 1200w, 1600w).
- **Copy Content:** "Welcome to My Portfolio" with subtitle description.
- **Call to Action (CTA):** Primary anchor link directing users to `#projects`.
### 5.2 Profile Card Component
- **Avatar:** Circular/Rounded presentation using `avatar.png`.
- **Bio Details:** Name, Title ("Fullstack Developer"), Location data (Brazil 🇧🇷, Italian Citizenship 10, Living in Dublin 11, Education (Information System 12), and language proficiency.
- **Social Links:** Direct bindings to GitHub (`leonardodg`), LinkedIn (`le0dg`), Email, and personal site.
### 5.3 Project Cards Grid
- **Card Elements:** Thumbnail preview (`leodg_dev.png`, `ivana_academy.png`, `qisat.jpg`), Project title, narrative description, technology chips, and action links (GitHub, Gh-Pages, Live Website).
### 5.4 Timeline (Professional Journey)
- **Structure:** Chronological ordered list (`<ol>`) tracking experience from 2022 to 2025 (including roles at Moodle/Ivana Academy, Keycloak integrations, FastAPI/Python backends, and AWS DevOps migrations).