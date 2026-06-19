---
name: SME Commerce Elite
colors:
  surface: '#faf8ff'
  surface-dim: '#dad9e1'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f3fa'
  surface-container: '#eeedf4'
  surface-container-high: '#e9e7ef'
  surface-container-highest: '#e3e1e9'
  on-surface: '#1a1b21'
  on-surface-variant: '#444651'
  inverse-surface: '#2f3036'
  inverse-on-surface: '#f1f0f7'
  outline: '#757682'
  outline-variant: '#c5c5d3'
  surface-tint: '#4059aa'
  primary: '#00236f'
  on-primary: '#ffffff'
  primary-container: '#1e3a8a'
  on-primary-container: '#90a8ff'
  inverse-primary: '#b6c4ff'
  secondary: '#006a61'
  on-secondary: '#ffffff'
  secondary-container: '#86f2e4'
  on-secondary-container: '#006f66'
  tertiary: '#3e2400'
  on-tertiary: '#ffffff'
  tertiary-container: '#5c3800'
  on-tertiary-container: '#ef9900'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b6c4ff'
  on-primary-fixed: '#00164e'
  on-primary-fixed-variant: '#264191'
  secondary-fixed: '#89f5e7'
  secondary-fixed-dim: '#6bd8cb'
  on-secondary-fixed: '#00201d'
  on-secondary-fixed-variant: '#005049'
  tertiary-fixed: '#ffddb8'
  tertiary-fixed-dim: '#ffb95f'
  on-tertiary-fixed: '#2a1700'
  on-tertiary-fixed-variant: '#653e00'
  background: '#faf8ff'
  on-background: '#1a1b21'
  surface-variant: '#e3e1e9'
typography:
  display-xl:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  caption:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  unit-xs: 4px
  unit-sm: 8px
  unit-md: 16px
  unit-lg: 24px
  unit-xl: 40px
---

## Brand & Style

This design system is built for high-performance SME management and booking stores. It prioritizes a **Corporate Modern** aesthetic—blending the reliability of enterprise SaaS with the clean, conversion-focused minimalism of premium e-commerce.

The design should evoke a sense of **authority, efficiency, and clarity**. It leverages generous whitespace and a "content-first" hierarchy where the products and transactional data are the heroes. Drawing from the provided references, the style utilizes subtle depth through tonal layering rather than heavy shadows, ensuring a fast, lightweight feel across web and mobile platforms.

**Key visual drivers:**
- High-contrast headers for clear information architecture.
- Systematic use of a professional blue to anchor the user experience.
- Energetic teal accents to guide transactional momentum (e.g., "Add to Cart", "Book Now").

## Colors

The palette is anchored by **Deep Professional Blue**, used for primary navigation and core brand elements. **Energetic Teal** serves as the primary action color, providing a refreshing contrast that signifies growth and activity.

- **Black (#000000):** Reserved for high-impact headers, announcement bars, and primary CTAs to create a high-fashion, premium editorial feel.
- **Grays:** A scale of soft grays is used for background fills and subtle borders to maintain visual softness without sacrificing structure.
- **Surface Strategy:** Use `neutral_gray_100` for secondary containers and `background_main` for the canvas to create a sophisticated "layering" effect.

## Typography

This system uses a dual-font approach to balance precision with approachability.

- **Hanken Grotesk (Headlines/Labels):** Chosen for its sharp, contemporary geometry. It provides the "SaaS" professional edge and performs exceptionally well in bold weights for product titles and headers.
- **Plus Jakarta Sans (Body):** A slightly softer, more humanist sans-serif that ensures long-form descriptions and UI labels remain legible and welcoming.

**Implementation Notes:**
- All product names in cards should use `headline-md`.
- Price points should use `headline-md` with the Primary Blue or a high-contrast Black.
- Use `label-md` for badges like "New" or "Best Seller."

## Layout & Spacing

The layout utilizes a **12-column fluid grid** for desktop and a **4-column grid** for mobile. 

**Grid Logic:**
- **Product Grids:** Typically 4 columns on desktop, 2 columns on tablet, and 1 or 2 on mobile depending on image aspect ratio.
- **Side Drawer:** The cart drawer should occupy a fixed 400px width on desktop and 90% width on mobile.

**Spacing Rhythm:**
A strict 8px-based system ensures consistency. High-level sections (e.g., between "New Arrivals" and "Trending") should utilize `unit-xl` to maintain the minimalist breathability seen in the references.

## Elevation & Depth

To match the high-quality aesthetic of the reference images, depth is communicated through **Tonal Layers** and **Ultra-Soft Shadows**.

- **Level 0 (Canvas):** `background_main`.
- **Level 1 (Cards/Drawers):** Pure white (#FFFFFF) with a 1px border of `neutral_gray_200`.
- **Level 2 (Hover/Active):** An ambient shadow with a large blur (24px) and very low opacity (0.04) to suggest a subtle lift without appearing "heavy."

**Checkout Depth:** Use a semi-transparent backdrop blur (12px) behind the side drawer and modals to maintain context while focusing the user on the transaction.

## Shapes

The design system adopts a **Soft (0.25rem - 0.75rem)** shape language. This ensures the professional "SaaS" feel isn't lost to overly round "bubbly" aesthetics, while still feeling modern and touch-friendly.

- **Buttons:** 0.25rem (Small) to 0.5rem (Standard).
- **Product Cards:** 0.5rem.
- **Input Fields:** 0.25rem.
- **Pills/Badges:** Full rounding (999px) to distinguish them from interactive buttons.

## Components

### Product Cards
- **Structure:** Vertical stack with an image container (`neutral_gray_100` background), followed by the product name, price, and a subtle "Quick Add" button that appears on hover.
- **Badges:** Placed in the top-left of the image container using the secondary teal or high-contrast black.

### 4-Step Checkout Progress
- **Visuals:** A horizontal line with 4 numbered circles. 
- **States:** Completed steps (Teal with checkmark), Active step (Blue with bold text), and Upcoming steps (Gray).
- **Context:** Place this at the very top of the checkout view to reduce cart abandonment.

### Side Drawer Cart
- **Styling:** Slides from the right. Top section includes a "Free Shipping" progress bar using a teal fill.
- **Line Items:** Compact layout with small product thumbnails, quantity toggles (+/-), and a "Remove" link.
- **Sticky Footer:** Subtotal and "Checkout" button are fixed to the bottom of the drawer.

### Professional Footer
- **Layout:** 4-column layout (Contact, Shop, Info, Newsletter).
- **Newsletter:** High-contrast input field with a black "Subscribe" button featuring a right-arrow icon.
- **Socials:** Monochrome icons in a simple row to keep the focus on utility.

### Inputs & Buttons
- **Primary Button:** High-contrast black or Deep Blue with white text for maximum impact.
- **Secondary Button:** Outline style with 1px `neutral_gray_200` and `neutral_black` text.
- **Input Fields:** Minimalist design with floating labels and a 1px border that turns Blue on focus.