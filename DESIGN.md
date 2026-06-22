---
name: PutMio Cinematic
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#c7c4d7'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#908fa0'
  outline-variant: '#464554'
  surface-tint: '#c0c1ff'
  primary: '#c0c1ff'
  on-primary: '#1000a9'
  primary-container: '#8083ff'
  on-primary-container: '#0d0096'
  inverse-primary: '#494bd6'
  secondary: '#ffe083'
  on-secondary: '#3c2f00'
  secondary-container: '#eec200'
  on-secondary-container: '#645000'
  tertiary: '#ffb783'
  on-tertiary: '#4f2500'
  tertiary-container: '#d97721'
  on-tertiary-container: '#452000'
  error: '#ef4444'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#e1e0ff'
  primary-fixed-dim: '#c0c1ff'
  on-primary-fixed: '#07006c'
  on-primary-fixed-variant: '#2f2ebe'
  secondary-fixed: '#ffe083'
  secondary-fixed-dim: '#eec200'
  on-secondary-fixed: '#231b00'
  on-secondary-fixed-variant: '#574500'
  tertiary-fixed: '#ffdcc5'
  tertiary-fixed-dim: '#ffb783'
  on-tertiary-fixed: '#301400'
  on-tertiary-fixed-variant: '#703700'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
  success: '#10b981'
  warning: '#f59e0b'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '800'
    lineHeight: 40px
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  margin-desktop: 2.5rem
  margin-mobile: 1rem
  gutter: 1.5rem
  container-max: 1440px
  section-gap: 3rem
---

## Brand & Style
The brand identity is "PutMio Cinematic," a premium personal media center aesthetic that balances high-end entertainment vibes with functional, developer-friendly clarity. It is built on a **Glassmorphic** and **Modern Corporate** hybrid style.

The interface evokes a sense of deep immersion through the use of dark backgrounds and vibrant, glowing accents. It targets tech-savvy media enthusiasts who value both visual flair (shimmer effects, hover scales) and information density. The emotional response is one of "organized discovery"—clean, structured, yet visually rich.

## Colors
The palette is rooted in a "Deep Space" navy (`#0b1326`), providing a high-contrast foundation for content. 

- **Primary:** An Indigo-based spectrum used for interactive states and brand identity.
- **Surface Strategy:** Uses a tiered approach of increasingly lighter navy shades to define hierarchy without relying solely on borders.
- **Semantic Colors:** Bright, high-saturation red, amber, and emerald are used for status feedback, often paired with low-opacity background tints (10-20%) to create "soft alerts."
- **Glass Effects:** Background blurs (12px to 20px) are applied to fixed headers and modals to maintain context while focusing attention.

## Typography
The system uses a duo-font approach. **Hanken Grotesk** serves as the workhorse for display and body text, offering a sharp, contemporary feel with high legibility. **JetBrains Mono** is used selectively for labels, metadata, and navigational items to inject a "technical" or "data-centric" personality.

- **Headlines:** Use heavy weights (700-800) to anchor sections.
- **Labels:** Always utilize the monospaced font, often in uppercase or with increased letter spacing to distinguish them from reading text.
- **Contrast:** High contrast between `on-surface` (white/blue-tinted) and `on-surface-variant` (muted lilac-grey) is essential for information hierarchy.

## Layout & Spacing
The system follows a **Fixed-Fluid Hybrid** grid. On desktop, content is constrained to a 1440px max-width container with generous 40px (2.5rem) side margins. On mobile, margins reduce to 16px (1rem).

- **Vertical Rhythm:** Sections are separated by large gaps (3rem/48px) to allow the "glass" panels to breathe.
- **Component Spacing:** Inside cards and sections, a consistent 1.5rem gutter is used. 
- **Aspect Ratios:** Poster cards strictly adhere to a 2:3 ratio, ensuring consistency in media catalogs.

## Elevation & Depth
Depth is expressed through **Tonal Layering** and **Glassmorphism**, rather than traditional heavy shadows.

- **Level 0 (Background):** `#0b1326` - The base canvas.
- **Level 1 (Sections/Cards):** `#171f33` - Surface containers with subtle 1px borders (`outline-variant` at 20-30% opacity).
- **Level 2 (Active/Hover):** `#2d3449` - Surface-variant highlights.
- **Overlays:** Modals and headers use `backdrop-blur-xl` (24px) combined with an 80% opacity surface color to create a sense of floating over the content.
- **Interactive Depth:** Poster cards use a 1.05x scale transform on hover accompanied by a diffused 25px shadow to "lift" them off the grid.

## Shapes
The shape language is consistently **Rounded**, leaning towards a soft-industrial aesthetic.

- **Primary Radius:** 0.5rem (8px) for standard inputs and buttons.
- **Large Radius:** 0.75rem (12px) to 1rem (16px) for cards, section containers, and modals.
- **Pill Shapes:** Used for badges (tags) and theme toggle controls to provide visual variety and signify secondary interaction.

## Components
- **Buttons:**
  - *Primary:* Solid `primary-container` background with high-contrast text. Features a 1.05x scale on hover and 0.95x on active click.
  - *Secondary:* Outlined with `outline` color, subtle hover background fill.
- **Inputs:** Dark backgrounds (`surface`) with `outline-variant` borders. Focus state uses a `primary` ring with 0px border-gap.
- **Poster Cards:** The signature component. 2:3 ratio, overflow hidden, featuring a bottom-aligned gradient overlay (from black to transparent) that reveals metadata on hover.
- **Badges:** Pill-shaped, small padding (px-3 py-1), using the `label-md` monospaced font.
- **Skeleton Loaders:** Uses a `shimmer` animation from left to right, utilizing a white/5% gradient over a `surface-variant` background.
- **Switches:** iOS-style toggle with a clear `primary-container` active state and a smooth sliding animation.