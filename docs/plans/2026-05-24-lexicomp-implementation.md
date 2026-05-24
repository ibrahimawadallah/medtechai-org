# Lexicomp-Style Upgrade — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Upgrade all 23 AI medical tools with Lexicomp-level data detail and professional clinical UI.

**Architecture:** Single-page PHP backend (`handler.php`) with 23 tool endpoints returning HTML; 23 static HTML frontends consuming via fetch. Upgrade both backend prompts and frontend UIs together.

**Tech Stack:** PHP 8.1, Apache, Groq/Gemini/OpenRouter APIs, vanilla JS, CSS custom properties.

**Files to modify:**
- `api/tools/handler.php` — All 23 switch cases (expanded prompts + richer HTML rendering)
- `tools/shared.css` — Complete redesign of design system
- `tools/index.html` — Updated listing page
- 23 × `tools/*/index.html` — Redesigned frontend pages

---

### Task 1: Redesign shared.css (Design System)

**Files:**
- Modify: `tools/shared.css` (entire file)

**Changes:**
New Lexicomp-inspired design system:
- Colors: Primary `#2563eb`, success `#059669`, warning `#d97706`, danger `#dc2626`, info `#0891b2`
- Cards: White bg, shadow, rounded-2xl with colored top border per severity
- Badges: Pill-shaped, colored bg, bold text
- Form inputs: Clean border, focus ring, floating labels
- Results: Collapsible sections with color-coded headers
- Buttons: Solid primary, outline secondary, small/large variants
- Animations: Smooth transitions for collapsible panels
- Responsive: Mobile-first, stacks on small screens
- Typography: Inter font, clean hierarchy

---

### Task 2: Expand Drug Search (handler.php + frontend)

**Files:**
- Modify: `api/tools/handler.php` (drug-search case, lines ~125-139)
- Modify: `tools/drug-search/index.html` (entire file)

**Backend changes:**
Expand prompt to return: genericName, brandNames, drugClass, therapeuticCategory, mechanismOfAction, indications, dosageForms, adultDosing, pediatricDosing, renalAdjustment, hepaticAdjustment, administration, adverseReactions (array of {system, reactions, frequency}), contraindications, warnings, drugInteractions (array), foodInteractions, pregnancyCategory, lactationSafety, monitoringParameters, pharmacokinetics, patientEducation, pricingForms

Rich HTML rendering with collapsible sections, severity badges, color-coded cards.

**Frontend changes:**
- Full-page Lexicomp-style layout
- Input form (drug name, optional filters)
- Results panel with collapsible sections
- Severity badges, section toggles
- Loading spinner, print button, clear button

---

### Task 3: Expand Interaction Checker (handler.php + frontend)

**Files:**
- Modify: `api/tools/handler.php` (interaction-checker case, lines ~142-155)
- Modify: `tools/interaction-checker/index.html`

**Backend:** Expand prompt: interactions array with mechanism, severity, onset, management, evidence level. Add foodInteractions (array), labInteractions (array), overallRisk.

**Frontend:** Drug selector (2-5 drugs), results grid, severity badges, mechanism explanations, management steps.

---

### Task 4: Expand Dose Calculator (handler.php + frontend)

**Files:**
- Modify: `api/tools/handler.php` (dose-calculator case, lines ~158-167)
- Modify: `tools/dose-calculator/index.html`

**Backend:** Expand prompt: recommendedDose, frequency, route, duration, maxDose, pediatricDosing, bsaDosing, renalAdjustment, hepaticAdjustment, titrationSchedule, administrationInstructions, warnings, monitoringParameters.

**Frontend:** Drug name, weight, age, indication, renal function, hepatic function, BSA checkbox. Results with dose card (large emphasis), adjustment alerts, warnings.

---

### Task 5: Expand Pregnancy Safety + G6PD Checker

**Files:**
- Modify: `api/tools/handler.php` (pregnancy-safety + g6pd-checker cases)
- Modify: `tools/pregnancy-safety/index.html`
- Modify: `tools/g6pd-checker/index.html`

**Backend:** Pregnancy: fdaCategory, safety summary, trimester details, lactation, male fertility, alternatives. G6PD: risk level, classification, mechanism, alternatives, references.

**Frontend:** Drug input, FDA category display (large badge), trimester tabs, alternatives list.

---

### Task 6: Expand Drug Comparison (handler.php + frontend)

**Files:**
- Modify: `api/tools/handler.php` (drug-comparison case)
- Modify: `tools/drug-comparison/index.html`

**Backend:** Expand: comparison table (efficacy, safety, cost, convenience, side effects, interactions), head-to-head data, recommendation.

**Frontend:** Two-drug selector, side-by-side comparison table, winner badges.

---

### Task 7: Expand Clinical Decision Support + Diagnostic Check + Symptom Checker

**Files:**
- Modify: `api/tools/handler.php` (3 cases)
- Modify: `tools/clinical-decision-support/index.html`
- Modify: `tools/diagnostic-check/index.html`
- Modify: `tools/symptom-checker/index.html`

**Backend:** CDS: assessment, differential diagnoses (with percentages), workup, management plan, urgency, referral, evidence level. Diagnostic: conditions list, recommended tests, red flags, next steps. Symptom: triage level, causes, care advice, when to seek care.

**Frontend:** Multi-input forms (symptoms, history, vitals), triage badges (emergency/urgent/routine), condition cards with probability bars.

---

### Task 8: Expand ICD-10 Lookup (handler.php + frontend)

**Files:**
- Modify: `api/tools/handler.php` (icd10-lookup case)
- Modify: `tools/icd10-lookup/index.html`

**Backend:** Return primary code, description, confidence, reasoning, secondary codes, category, chapter.

**Frontend:** Diagnosis input, code cards with confidence badges, secondary codes list.

---

### Task 9: Expand Smart Reports (5 tools)

**Files:**
- Modify: `api/tools/handler.php` (smart-report-oic, report-composer, lab-analyzer, imaging-reader, pathology-reader cases)
- Modify: 5 × `tools/*/index.html`

**Backend:** Each gets expanded fields for thorough clinical reporting with findings, impressions, recommendations, severity, urgency, follow-up.

**Frontend:** Report-style layout with findings, impression cards, action buttons, print-friendly styles.

---

### Task 10: Expand Discharge Summary + Clinical Notes

**Files:**
- Modify: `api/tools/handler.php` (2 cases)
- Modify: `tools/discharge-summary/index.html`
- Modify: `tools/clinical-notes/index.html`

**Backend:** Discharge: diagnoses, hospital course, procedures, meds (name/dose/frequency/duration), follow-up, instructions. Notes: SOAP format with vital signs, exam, assessment, plan, ICD-10.

**Frontend:** SOAP layout, medication table, follow-up checklist.

---

### Task 11: Expand Medication Safety + Formulary + IV Compatibility

**Files:**
- Modify: `api/tools/handler.php` (3 cases)
- Modify: `tools/medication-safety/index.html`
- Modify: `tools/formulary/index.html`
- Modify: `tools/iv-compatibility/index.html`

**Backend:** Safety: overall safety, LASA risk, high alert, allergy conflicts, interactions, monitoring. Formulary: results table with status, restrictions, alternatives, tier. IV: compatibility grid, pairs, evidence, recommendation.

**Frontend:** Safety dashboard with alert icons, formulary filterable table, IV compatibility grid (green/red/amber).

---

### Task 12: Expand Clinical Pathways + Calculators + Stewardship

**Files:**
- Modify: `api/tools/handler.php` (3 cases)
- Modify: `tools/clinical-pathways/index.html`
- Modify: `tools/clinical-calculators/index.html`
- Modify: `tools/stewardship/index.html`

**Backend:** Pathways: condition overview, assessment, workup, treatment steps (step number, action, timeframe), monitoring. Calculators: result, score, interpretation, risk category, recommendations. Stewardship: appropriateness, recommendation, justification, de-escalation, duration, monitoring, resistance risk.

**Frontend:** Step-by-step pathway display, calculator result with large score, stewardship dashboard with recommendation badges.

---

### Task 13: Update Tools Index Page

**Files:**
- Modify: `tools/index.html`

**Changes:** Updated card design matching new design system, category filters, search within tools, consistent Lexicomp style.

---

### Task 14: Apply Custom Domain on Render

**Steps:**
1. Render dashboard → Settings → Custom Domain → Add `medtechai.net`
2. At domain registrar → Add CNAME record pointing to Render's DNS target
3. Wait for SSL provisioning

---

### Execution Order

Run tasks sequentially:
1. Task 1 (shared.css — foundation for all tools)
2. Tasks 2-12 (one tool batch at a time)
3. Task 13 (update listing)

Each task: modify handler.php → modify tool HTML → run `git add` + `git commit` + `git push` → verify on Render

---

### Verification

After each task, deploy and test: `curl -X POST https://pharmgenius-production-main.onrender.com/api/tools/<tool-name> -H "Content-Type: application/json" -d '<sample-data>'`

After all tasks: full regression of all 23 tools.
