# Clinical Calculators Dashboard — Design

**Date**: 2026-05-28  
**Status**: Approved  
**Page**: `tools/clinical-calculators/index.html`

## Overview

Full redesign of the Clinical Calculators page as a modern dashboard with category navigation, comprehensive calculator coverage, and hybrid local/AI computation.

## Design Goals

1. **Dashboard layout** — Category sidebar, search, calculator grid
2. **Full clinical suite** — 8 specialty categories with 20+ calculators
3. **Hybrid approach** — Local client-side for simple calcs, API for complex interpretations

## Layout Structure

### Navigation
- Dark sidebar (desktop) with category icons
- Mobile: horizontal filter dropdown
- Quick search bar to filter calculators by name

### Calculator Grid
- Responsive grid: 3 columns (desktop), 2 columns (tablet), 1 column (mobile)
- Each calculator shown as compact card with name + category tag
- Selected calculator expands to show input fields inline

### Result Panel
- Right side on desktop, below on mobile
- Shows calculation results with interpretation
- Print and Clear buttons

## Calculator Categories

### Anthropometrics
- BMI (Body Mass Index)
- BSA (Mosteller formula)
- Ideal Body Weight (IBW)
- Adjusted Body Weight

### Renal
- GFR (CKD-EPI)
- Creatinine Clearance (Cockcroft-Gault)
- FENa (Fractional Excretion of Sodium)
- BUN/Creatinine Ratio

### Electrolytes
- Corrected Calcium (Albumin-corrected)
- Anion Gap
- Sodium Correction (for hyperglycemia)
- Osmolality Gap
- Delta-Delta (Delta Ratio)

### Cardiovascular
- CHA₂DS₂-VASc (Stroke risk in AF)
- Wells DVT Score
- Wells PE Score
- TIMI Risk Score (ACS)
- GRACE Score (ACS)
- HEART Score (Chest Pain)

### Critical Care
- APACHE II
- qSOFA (Quick SOFA)
- SOFA (Sequential Organ Failure Assessment)
- CURB-65 (Pneumonia severity)
- Glasgow Coma Scale

### Hepatology
- MELD (Model for End-Stage Liver Disease)
- MELD-Na
- Child-Pugh Score
- FIB-4 (Fibrosis index)
- APRI (AST to Platelet Ratio Index)

### Neurology
- Glasgow Coma Scale
- Alvarado Score (Appendicitis)
- NIH Stroke Scale (reference)

### Pulmonary
- Wells PE Score
- PERC Rule (Pulmonary Embolism)
- Geneva Score
- CURB-65

## Hybrid Computation

### Local (Client-Side)
Simple formulas computed instantly in browser:
- BMI, BSA, IBW
- GFR, CrCl, FENa
- Corrected Ca²⁺, Anion Gap, Na Correction
- CHA₂DS₂-VASc, Wells DVT/PE
- CURB-65, GCS
- MELD, Child-Pugh

### AI-Assisted (API)
Complex multi-factor interpretations sent to `/api/tools/clinical-calculators`:
- APACHE II (12 acute variables)
- SOFA (6 organ systems)
- TIMI, GRACE (complex weighting)
- Full clinical interpretation for any calculator

### Fallback
- All local calcs work offline
- AI calcs show raw result + "Get AI Interpretation" button
- Graceful error handling if API unavailable

## Visual Design

### Color Scheme
- Purple accent (Advanced Clinical category)
- Category-specific colors for tags
- Dark navy sidebar (#1a2d3a)

### Typography
- DM Sans for headings
- Inter for body text
- 12-14px for compact cards

### Components
- Category sidebar with icons
- Calculator cards with hover effects
- Expandable input panels
- Sticky result panel

## Medical Safety

- All calculators include formula citations
- Reference ranges shown with results
- Disclaimers for clinical decision support
- HIPAA-compliant: no patient data stored
