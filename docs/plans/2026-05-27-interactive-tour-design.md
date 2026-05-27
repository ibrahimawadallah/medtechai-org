# Live Interactive Tour — Design Document

**Date:** 2026-05-27
**Status:** Approved
**Approach:** Driver.js library + custom `_tour.js` wrapper

## Overview

A guided interactive walkthrough of the MedTechAI platform that highlights key tools, pre-fills sample data, and guides users through the interface — aimed at first-time visitors and pitch demos.

## Architecture

### Components

- **`tools/_tour.js`** — Core tour module (~250 lines)
  - Lazy-loads Driver.js from CDN when tour starts
  - Defines tour steps (`tourSteps` array)
  - Handles cross-page navigation (localStorage for resume)
  - Pre-fills form fields with sample data
  - Manages tour state (start, next, prev, skip, complete)
- **Driver.js CDN** — `https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.min.css` + `.iife.min.js` (~6 KB gzip)
- **localStorage** — `medtechai_tour` key stores `{step, page, tourId, completed}`

### Tour Flow (15 steps)

| # | Page | Element | Popover | Action |
|---|------|---------|---------|--------|
| 1 | Tools (/) | `.main h1` | "Welcome — explore 22 AI clinical tools. This tour will show you the highlights in 3 minutes." | — |
| 2 | Tools (/) | `#uptodate-sidebar` | "Navigate by specialty — Pharmacy, Clinical Support, Smart Reports, Advanced Clinical" | — |
| 3 | Tools → Drug Search | `#f_drug` | "Start here: enter any drug name. Try Metformin — the most prescribed diabetes drug." | Pre-fill "Metformin" |
| 4 | Drug Search | `#submitBtn` | "Click Run Tool for AI-generated insights: mechanism, dosing, warnings, interactions, and more." | — |
| 5 | Drug Search → Interaction Checker | `#drugsContainer` | "Check interactions across multiple drugs. Try Warfarin + Ibuprofen + Omeprazole — a common high-risk combo." | Pre-fill 3 drugs |
| 6 | Interaction Checker | `#submitBtn` | "Get severity-graded interaction analysis with management recommendations." | — |
| 7 | Interaction Checker → Clinical Calculators | `.calc-opt` | "Validated medical calculators — local, no API needed. Select BMI." | Select BMI |
| 8 | Clinical Calculators | `#cf_wt` | "Enter patient data for instant calculation." | Pre-fill 80 kg, 175 cm |
| 9 | Clinical Calculators | `.r-value` | "Result appears instantly — no server round trip." | — |
| 10 | Clinical Calculators → Smart Report OIC | `#f_type`, `#f_mod` | "Upload a scan or paste findings for a structured AI report with ICD-10 codes." | Pre-fill Radiology + CT |
| 11 | Smart Report OIC | `#f_text` | "Paste clinical findings — the AI generates a specialist-grade structured report." | Pre-fill "2cm enhancing lesion in right hepatic lobe, suspicious for metastasis" |
| 12 | Smart Report OIC | `#submitBtn` | "Generates study description, impression, recommendations, severity grading, and ICD-10 mapping." | — |
| 13 | Smart Report OIC → Symptom Checker | All form fields | "AI triage tool — classifies urgency as emergency, urgent, routine, or self-care." | Pre-fill "30 yo Female, sore throat, fever 38°C, swollen lymph nodes" |
| 14 | Symptom Checker → Clinical Decision Support | All form fields | "Comprehensive AI — symptoms + history → differential diagnosis, workup, management plan, guidelines." | Pre-fill chest pain case |
| 15 | Clinical Decision Support → Tools | Celebration overlay | "You've seen the key features. Explore all 22 tools at your own pace!" | Set `completed: true` |

### Cross-Page Navigation

When a step requires moving to a different tool page:
1. `_tour.js` saves `{step: nextIndex, tourId: 'highlights', page: 'drug-search'}` to localStorage
2. Sets `window.location` to the target page
3. On next page's `DOMContentLoaded`, `_tour.js` detects saved state
4. Verifies `tourId` matches, loads Driver.js, starts tour at the saved step index

### Pre-fill Helpers

Each tool step defines a `prefill()` callback that:
- Selects the correct form fields by ID
- Sets their values
- Triggers any necessary UI updates (e.g., selecting a calculator)
- Does NOT auto-submit — lets the user click Run Tool themselves

### Entry Points

1. **Tools listing page**: Floating FAB (bottom-right, teal) with "Take the Tour" text — pulsating animation on first visit
2. **Sidebar**: "🎯 Platform Tour" item at the very bottom of the specialty sidebar (all pages)
3. **First-visit auto-prompt**: If `medtechai_tour` never set, show a subtle banner "New here? Take the 3-minute tour →"

### UI / Styling

- **Driver.js theme**: Override defaults to match brand — teal accent, navy headers, DM Sans font, rounded tooltips
- **Tooltip position**: `right` for sidebar steps, `bottom` for form steps, `overlay` for full-page welcome/celebration
- **Progress**: "Step 3 of 15" in tooltip footer
- **Buttons**: Teal "Next →", outline "Skip Tour", gray "← Back"

### Success Criteria

- Tour completes without JavaScript errors on any page
- All 15 steps render correct popovers with sample data
- Cross-page navigation works (clicking next navigates to the correct tool)
- localStorage state correctly persists and resumes
- "Skip Tour" clears state and hides entry points for that session
- Mobile: tooltips reposition correctly (Driver.js handles this)

## Files to Create/Modify

### New
- `tools/_tour.js` — Core tour module

### Modified
- `tools/_upload.js` — Add `<script src="/tools/_tour.js">` injection (or include directly in tool pages)
- `tools/index.html` — Add tour FAB button + first-visit banner

## Future Enhancements (Not MVP)

- "Full Tour" mode with all 22 tools (reuse same infrastructure)
- Tour progress tracking via analytics
- Per-tool mini tours activated from individual tool pages
- Multi-language tour text
