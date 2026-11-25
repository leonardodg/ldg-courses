---
name: Lumina Dark
colors:
  surface: '#131313'
  surface-dim: '#131313'
  surface-bright: '#393939'
  surface-container-lowest: '#0e0e0e'
  surface-container-low: '#1c1b1b'
  surface-container: '#201f1f'
  surface-container-high: '#2a2a2a'
  surface-container-highest: '#353534'
  on-surface: '#e5e2e1'
  on-surface-variant: '#c1c6d7'
  inverse-surface: '#e5e2e1'
  inverse-on-surface: '#313030'
  outline: '#8b90a0'
  outline-variant: '#414755'
  surface-tint: '#adc6ff'
  primary: '#adc6ff'
  on-primary: '#002e69'
  primary-container: '#4b8eff'
  on-primary-container: '#00285c'
  inverse-primary: '#005bc1'
  secondary: '#f3aeff'
  on-secondary: '#55006a'
  secondary-container: '#7c0599'
  on-secondary-container: '#ee99ff'
  tertiary: '#ffb595'
  on-tertiary: '#571e00'
  tertiary-container: '#ef6719'
  on-tertiary-container: '#4c1a00'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#d8e2ff'
  primary-fixed-dim: '#adc6ff'
  on-primary-fixed: '#001a41'
  on-primary-fixed-variant: '#004493'
  secondary-fixed: '#fdd6ff'
  secondary-fixed-dim: '#f3aeff'
  on-secondary-fixed: '#340042'
  on-secondary-fixed-variant: '#790096'
  tertiary-fixed: '#ffdbcc'
  tertiary-fixed-dim: '#ffb595'
  on-tertiary-fixed: '#351000'
  on-tertiary-fixed-variant: '#7c2e00'
  background: '#131313'
  on-background: '#e5e2e1'
  surface-variant: '#353534'
  surface-card: '#1E1E1E'
  text-high: '#FFFFFF'
  text-medium: '#B0B3B8'
  border-subtle: '#3A3B3C'
  moodle-blue: '#194866'
typography:
  hero-title:
    fontFamily: Inter
    fontSize: 64px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  headline-h1:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '600'
    lineHeight: '1.2'
  headline-h2:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '600'
    lineHeight: '1.3'
  body-base:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: '1.4'
  hero-title-mobile:
    fontFamily: Inter
    fontSize: 40px
    fontWeight: '700'
    lineHeight: '1.1'
  headline-h1-mobile:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
  max-width: 1440px
---

## Brand & Style

The design system is a high-performance, dark-mode-first aesthetic tailored for Moodle 5.2. It prioritizes cognitive focus and reduced eye strain for long-form learning environments. The brand personality is **Professional, Technical, and Focused**, utilizing a **Modern Minimalist** style with **Glassmorphism** accents to soften the interface.

Key visual principles include:
- **Depth through layering:** Using surface variations rather than heavy lines to define hierarchy.
- **Precision:** High-contrast typography and intentional whitespace to guide students through complex course curricula.
- **Focus:** Vibrant electric blue accents reserved strictly for primary actions and progress indicators to minimize distraction.

## Colors

The palette is anchored by a deep `#121212` background to ensure true dark-mode compatibility.

- **Primary:** Electric Blue (`#007AFF`) is the functional workhorse, used for CTAs, active states, and navigation highlights.
- **Secondary:** A deep Purple (`#8E24AA`) is used sparingly for categorization, achievement badges, or gamification elements to provide a rich visual variance.
- **Neutral/Surface:** The system uses a tiered elevation model. The base is `#121212`, while interactive or floating elements (cards, sidebars) use `#1E1E1E`.
- **Contrast:** Text is strictly hierarchical. Use High Emphasis white for readability in content areas and Medium Emphasis gray for metadata and supporting descriptions.

## Typography

This design system utilizes **Inter** exclusively to ensure a systematic, utilitarian feel that scales perfectly across Moodle’s various data-heavy blocks.

- **Display Text:** Large headings use tighter letter spacing and high weights to create a sense of authority.
- **Body Content:** The 16px base with 1.6 line height is optimized for reading long-form educational text on screens.
- **Scale:** On mobile devices, large display headers must downscale significantly to prevent excessive scrolling, while body text remains consistent for legibility.

## Layout & Spacing

The system follows a **12-column Fluid Grid** for the main content area, with a fixed sidebar for course navigation inspired by the Moove layout.

- **Grid:** Use 24px gutters to allow the dark surfaces to "breathe." 
- **Rhythm:** Spacing follows an 8px incremental scale (8, 16, 24, 32, 48, 64).
- **Responsive Behavior:** 
  - **Desktop:** Max container width of 1440px. Sidebars are expanded by default.
  - **Tablet:** Sidebars collapse into icons or a hamburger menu. Margins reduce to 24px.
  - **Mobile:** Single column layout. 16px horizontal margins.

## Elevation & Depth

Hierarchy is communicated through **Tonal Layers** supplemented by subtle ambient shadows.

- **Level 0 (Background):** `#121212` - The canvas.
- **Level 1 (Cards/Sidebar):** `#1E1E1E` - Elements that contain content. These use a very soft shadow: `0 4px 12px rgba(0, 0, 0, 0.5)`.
- **Level 2 (Modals/Popovers):** `#2A2A2A` - Highest elevation. These use a more pronounced shadow and a 1px border of `#3A3B3C` to define their edges against the dark background.
- **Glassmorphism:** For the fixed Navbar, use a background blur (`backdrop-filter: blur(12px)`) at 85% opacity of the `#1E1E1E` color to maintain context while scrolling.

## Shapes

The design system uses a **Rounded** shape language to make the LMS feel approachable and modern.

- **Standard Elements:** Buttons, cards, and input fields all utilize an 8px (0.5rem) border radius.
- **Interactive Elements:** Small chips or status tags (e.g., "In Progress") should use the `rounded-xl` setting (24px) to create a "pill" look that distinguishes them from actionable buttons.
- **Icons:** Use linear icons with slightly rounded caps to match the typography's geometric nature.

## Components

### Buttons
- **Primary:** Background `#007AFF`, Text `#FFFFFF`, Bold weight. On hover, increase brightness by 10%.
- **Secondary/Ghost:** Border `1px solid #3A3B3C`, Text `#FFFFFF`.

### Cards
- **Base:** Background `#1E1E1E`, 8px radius. 
- **Header:** Include a subtle 1px bottom border of `#3A3B3C` if the card contains complex body data (like grade tables).

### Inputs & Forms
- **Fields:** Background `#121212`, Border `1px solid #3A3B3C`. 
- **Focus State:** Border color shifts to `#007AFF` with a 2px outer glow.
- **Labels:** Use `label-sm` in Medium Emphasis text.

### Navigation (Moodle Specific)
- **Navbar:** Fixed top, `#1E1E1E` with blur.
- **Course Index (Sidebar):** Use `#1E1E1E`. Active items should have a 4px left-border accent of `#007AFF`.

### Feedback
- **Progress Bars:** Background `#3A3B3C`, Fill `#007AFF`.
- **Status Chips:** Use secondary palette colors (e.g., Purple for completed, Orange for pending) with a low-opacity background tint.