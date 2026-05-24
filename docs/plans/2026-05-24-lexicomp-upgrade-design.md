# Lexicomp-Style Upgrade — Design Doc

## Goal
Upgrade all 23 AI medical tools with Lexicomp-level data detail and modern clinical UI.

## Design Direction
- **Style**: Clinical & clean (Lexicomp-inspired)
- **Colors**: Blue primary (#2563eb), teal accent (#059669), amber warning, red danger
- **Layout**: Single column with collapsible result sections, responsive
- **Components**: Severity badges, color-coded cards, loading spinners

## Backend Changes
- `api/tools/handler.php` — Expand prompts for all 23 tools to return 2-3x more data fields
- Each tool switch case gets updated JSON schema in its prompt

## Frontend Changes
- `tools/shared.css` — Complete redesign with modern CSS
- All 23 `tools/*/index.html` — New form + result display per tool
- `tools/index.html` — Updated listing grid

## Scope Per Tool (example: drug-search)

### Current (11 fields)
Generic Name, Brand Names, Drug Class, MoA, Indications, Dosing, Side Effects, Warnings, Contraindications, Pregnancy Category, Patient Summary

### New (18+ fields)
- Generic & Brand Names
- Drug Class & Therapeutic Category
- Mechanism of Action
- Indications & Approved Uses
- Dosage Forms & Strengths
- Adult Dosing (by indication)
- Pediatric Dosing (by weight/age)
- Renal/Hepatic Adjustment
- Administration (prep, storage)
- Adverse Reactions (by system, frequency)
- Contraindications & Warnings
- Drug Interactions (categories)
- Food/Lab Interactions
- Pregnancy & Lactation
- Monitoring Parameters
- Pharmacokinetics
- Patient Education
- Pricing & Forms

Similar expansions for all 23 tools.

## Approach
- Rewrite all at once using shared template pattern
- Iterate: deploy, test, fix

## Files Modified
- `api/tools/handler.php` (backend prompts + HTML rendering)
- `tools/shared.css` (shared styles)
- 23 `tools/*/index.html` (frontend UIs)
- `tools/index.html` (listing page)
