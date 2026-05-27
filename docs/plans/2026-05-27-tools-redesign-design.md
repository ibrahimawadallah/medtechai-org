# Tools Pages Redesign — Design Doc

## Problems
1. **Sidebar overlays tool header** — `_upload.js` injects a fixed `top:48px` sidebar but only shifts `.main` by 220px, leaving `.breadcrumb` and `.tool-header` clipped behind it
2. **Not modern** — tool pages look utilitarian vs. the polished homepage; missing DM Sans headings, larger rounding, softer shadows, teal/mint brand colors

## Approach

**B) Build sidebar via dedicated `_sidebar.js`** — lightweight script injects sidebar properly (no overlap), brand-styled. Single source of truth for all 23 tool pages.

## Design

### 1. Sidebar (`_sidebar.js`)
- Injects `<aside class="side">` fixed left, `top:96px` (48px nav + 48px search bar), `z-index:1`
- 220px wide, full remaining height with overflow-y:auto
- **"All Tools"** link at top → `/tools/`
- Category links with colored dots: Pharmacy (teal), Clinical Support (cyan), Smart Reports (green), Advanced Clinical (purple)
- Automatically highlights active category by reading `.cat-banner` class
- Shifts `.main` + `.tool-header` + `.breadcrumb` via `margin-left: 240px` (220px + 20px gap)
- Removes old sidebar injection from `_upload.js` (lines 266-298)
- Loaded via `<script src="/tools/_sidebar.js" defer></script>` on each tool page

### 2. Visual Modernization (`shared.css`)
- **Typography**: DM Sans for `.tool-name` and section titles; Inter for body
- **Cards**: 12px radius (up from 8px), softer shadow `0 2px 12px rgba(0,0,0,.06)`, mint-white bg
- **Inputs**: 2px border, 12px radius, teal focus ring `rgba(56,184,174,.15)`
- **Buttons**: Teal primary `#38b8ae` replaces blue `#2563eb`
- **Tool header**: Larger padding, teal bottom border, bigger font size
- **Category pill**: More rounded, softer background tint
- **Sidebar**: Mint background `#f5fafa`, teal active state, rounded colored dots
- **Spacing**: More generous internal padding throughout

### 3. Sidebar Removal from `_upload.js`
- Lines 266-298 (sidebar injection) removed
- File upload state machine, search bar injection, tour injection all stay

### 4. Rollout
- Create `tools/_sidebar.js`
- Update `tools/shared.css`
- Remove sidebar code from `tools/_upload.js`
- Add sidebar script tag to all 23 tool pages
