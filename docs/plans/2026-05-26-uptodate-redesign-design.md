# MedTechAI UpToDate Redesign — Design Document

## Goal
Transform the MedTechAI platform into a high-fidelity replica of Wolters Kluwer's UpToDate clinical decision support interface — visually, structurally, and functionally.

## Approach
**Hybrid (Approach 3):** Shared CSS redesign + JS injection of persistent search bar and specialty sidebar + evidence grading + patient handout mode. No page-level template changes needed.

## Visual Design

### Color Palette
| Token | Value | Usage |
|-------|-------|-------|
| Primary Blue | `#0066aa` | Links, focus states, UpToDate signature |
| Brand Teal | `#38b8ae` | Category accents, secondary CTAs |
| Background | `#ffffff` | Page bg, cards, surfaces |
| Sidebar BG | `#f8f9fa` | Left specialty panel |
| Text Primary | `#1a1a2e` | Headlines, body |
| Text Muted | `#6b7280` | Secondary text, captions |
| Border | `#e5e7eb` | Card borders, dividers |
| Grade A | `#059669` | Evidence 1A/1B badges |
| Grade B | `#2563eb` | Evidence 2A/2B badges |
| Grade C | `#d97706` | Evidence 2C badge |

### Typography
- Font stack: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`
- Headers: 600 weight, 15–18px
- Body: 14px, 1.6 line-height
- Evidence badges: 10px, uppercase, semibold

### Layout
```
┌──────────────────────────────────────────────┐
│ Persistent Search Bar (fixed, white)         │
├────────┬─────────────────────────────────────┤
│Specialty│  Breadcrumb > Category > Tool      │
│Sidebar  │  Tool Header (name + description)  │
│200px    │                                     │
│         │  ┌─────────────────────────────┐    │
│         │  │ Form Card (shadow, rounded) │    │
│         │  │ [Upload] [Inputs] [Submit]  │    │
│         │  └─────────────────────────────┘    │
│         │  ┌─────────────────────────────┐    │
│         │  │ Results Panel               │    │
│         │  │ • Evidence badges           │    │
│         │  │ • References                │    │
│         │  │ • [Print] [Patient Handout] │    │
│         │  └─────────────────────────────┘    │
├────────┴─────────────────────────────────────┤
│ Footer (navy)                                │
└──────────────────────────────────────────────┘
```

## Components

### 1. Persistent Search Bar
- Fixed top bar (z-index 100), white bg, subtle `box-shadow`
- Left: MedTechAI logo + "Specialties" dropdown toggle (mobile)
- Center: Search input with autocomplete (`_suggest.js`)
- Right: About / Contact links
- Injected via `_upload.js` — prepends to `<body>`

### 2. Specialty Sidebar
- Fixed 200px left column below search bar
- Background `#f8f9fa`, border-right
- Lists: Pharmacy, Clinical Support, Smart Reports, Advanced Clinical, All Tools
- Active category highlighted with teal left border + bold text
- Hidden below 768px (mobile: hamburger menu)
- Injected via `_upload.js` — reads page's category class

### 3. Evidence Badges
- Pill-shaped `<span class="grade-1a">GRADE 1A</span>`
- Colors: 1A/1B = green, 2A/2B = blue, 2C = amber
- Prompt instruction added to each API endpoint

### 4. Patient Handout Mode
- `.patient-handout` CSS class with large font, plain language
- "Print Patient Handout" button toggles handout view then calls `window.print()`
- `@media print` hides clinical UI, shows only handout content
- Prompt instruction: *"When 'Patient Handout' is requested, provide a plain-language summary at 5th-grade reading level."*

### 5. References Section
- `.references` block at bottom of result panel
- Numbered citations in `[1]` format
- CSS: small font, muted color, border-top separator

## Implementation Order

1. **`tools/shared.css`** — Full visual redesign
   - New variables (blue, grade colors, sidebar)
   - Card shadows, search bar, sidebar layout
   - Evidence badges, references, patient handout styles
   - Print styles, mobile responsive

2. **`tools/_upload.js`** — Inject persistent search bar + sidebar
   - `injectSearchBar()` — creates fixed top bar with search input
   - `injectSidebar()` — creates 200px specialty nav sidebar
   - `injectPatientHandout()` — adds handout button to result panels
   - Reads category from `.cat-banner` or `.tool-header` class

3. **`tools/_suggest.js`** — Update autocomplete endpoint
   - Include tool names, drug names, topics in suggestions
   - Support keyboard navigation in dropdown

4. **API prompts** — Add evidence grading + patient handout instructions
   - Add GRADE evidence instruction to each tool prompt
   - Add patient handout format instruction

5. **`tools/index.html`** — Update to new design
   - Include search bar, sidebar
   - Update tool listing cards to match new design

6. **Integration testing** — Verify all 21 tool pages
   - Search bar renders on every page
   - Sidebar highlights correct category
   - Evidence badges display in results
   - Patient handout print mode works
   - Mobile responsive

## Files Changed
- `tools/shared.css` — CSS redesign
- `tools/_upload.js` — Sidebar + search injection
- `tools/_suggest.js` — Enhanced autocomplete
- `tools/index.html` — Brand redesign
- `default.php` — Prompt updates (evidence grading)
- (optionally) `docker-entrypoint.sh` — Prompt updates if stored there
