# Interactive Tour Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a live interactive 15-step guided tour of the MedTechAI platform using Driver.js, covering 6 highlight tools with sample data pre-fill and cross-page navigation.

**Architecture:** `_tour.js` wraps Driver.js (lazy-loaded from CDN), defines 15 tour steps across 6 tool pages, handles cross-page resume via localStorage, and pre-fills form fields with realistic sample data. Entry points: FAB on tools listing, sidebar item on all pages, first-visit auto-prompt.

**Tech Stack:** Driver.js CDN v1.3.1, vanilla JS (no build step), localStorage for state

---

### Task 1: Create `_tour.js` — Core module structure

**Files:**
- Create: `tools/_tour.js`

**Step 1: Write the file skeleton**

```javascript
// _tour.js — Live interactive platform tour
(function() {
  'use strict';

  var TOUR_KEY = 'medtechai_tour';
  var DRIVER_CSS = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.min.css';
  var DRIVER_JS  = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.min.js';

  // ── Tour state ────────────────────────────────────────────────────
  var state = {
    driver: null,
    steps: [],
    currentIndex: 0,
    tourId: 'highlights',
    running: false
  };

  // ── Step definitions (populated per-page) ─────────────────────────

  // Step 1: Tools listing — Welcome
  // Step 2: Tools listing — Sidebar highlight
  // Step 3: Drug Search — Pre-fill drug name
  // Step 4: Drug Search — Highlight Run button
  // Step 5: Interaction Checker — Pre-fill 3 drugs
  // Step 6: Interaction Checker — Highlight Run button
  // Step 7: Clinical Calculators — Select BMI
  // Step 8: Clinical Calculators — Pre-fill weight/height
  // Step 9: Clinical Calculators — Show result
  // Step 10: Smart Report OIC — Select type/modality
  // Step 11: Smart Report OIC — Pre-fill findings
  // Step 12: Smart Report OIC — Highlight Run
  // Step 13: Symptom Checker — Pre-fill all fields
  // Step 14: Clinical Decision Support — Pre-fill all fields
  // Step 15: Tools listing — Celebration

  // ── Driver.js lazy load ───────────────────────────────────────────
  function loadDriver(callback) {
    if (window.Driver) { callback(); return; }
    var link = document.createElement('link');
    link.rel = 'stylesheet'; link.href = DRIVER_CSS;
    document.head.appendChild(link);
    var script = document.createElement('script');
    script.src = DRIVER_JS;
    script.onload = callback;
    document.body.appendChild(script);
  }

  // ── State management ──────────────────────────────────────────────
  function saveState(step) {
    try { localStorage.setItem(TOUR_KEY, JSON.stringify({
      step: step, page: window.location.pathname, tourId: state.tourId
    })); } catch(e) {}
  }

  function loadState() {
    try {
      var s = JSON.parse(localStorage.getItem(TOUR_KEY));
      if (s && s.tourId === state.tourId) return s;
    } catch(e) {}
    return null;
  }

  function clearState() {
    try { localStorage.removeItem(TOUR_KEY); } catch(e) {}
  }

  // ── Core tour functions ───────────────────────────────────────────
  function startTour(startStep) {
    state.running = true;
    loadDriver(function() {
      state.driver = new Driver({
        animate: true,
        opacity: 0.6,
        padding: 6,
        allowClose: true,
        overlayClickNext: false,
        doneBtnText: 'Done',
        closeBtnText: 'Close',
        nextBtnText: 'Next \u2192',
        prevBtnText: '\u2190 Back',
        onNext: function(el, step) { onStepChange(step); },
        onPrev: function(el, step) { onStepChange(step); },
        onClose: function() { clearState(); state.running = false; }
      });
      state.driver.defineSteps(state.steps);
      state.driver.start(startStep || 0);
    });
  }

  function onStepChange(stepIndex) {
    state.currentIndex = stepIndex;
    saveState(stepIndex);
  }

  function resumeTour() {
    var saved = loadState();
    if (!saved) return false;
    // Only resume if we're on the correct page for the saved step
    var stepPage = getPageForStep(saved.step);
    var currentPage = window.location.pathname.replace(/\/$/,'');
    if (stepPage && currentPage !== stepPage && !currentPage.endsWith(stepPage)) {
      // Wrong page — navigate
      navigateToStep(saved.step);
      return true;
    }
    // Check if the step's page matches current page
    if (stepPage && !currentPage.endsWith(stepPage)) return false;
    var remaining = state.steps.slice(saved.step);
    if (remaining.length === 0) return false;
    startTour(saved.step);
    return true;
  }

  function getPageForStep(index) {
    var step = state.steps[index];
    if (!step) return null;
    return step.page || null;
  }

  function navigateToStep(stepIndex) {
    var page = getPageForStep(stepIndex);
    if (page) { saveState(stepIndex); window.location.href = page; }
  }

  // ── Pre-fill helpers ──────────────────────────────────────────────
  function setVal(id, val) {
    var el = document.getElementById(id);
    if (el) { el.value = val; }
  }

  function setSel(id, val) {
    var el = document.getElementById(id);
    if (el) { el.value = val; }
  }

  // ── Initialize ────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function() {
    // If a tour was saved, try to resume
    if (resumeTour()) return;
  });

  // Expose for entry points
  window.startPlatformTour = function() { startTour(0); };
})();
```

**Step 2: Verify no syntax errors**

Run: `node -e "require('fs').readFileSync('tools/_tour.js','utf8').split('\n').forEach(function(l,i){try{new Function(l)}catch(e){console.log('Line '+(i+1)+': '+e.message)}}); console.log('OK')"` (Note: this is approximate; just check browser console later)

Alternatively: Open `tools/index.html` in browser after all tasks and check console for errors.

**Step 3: Commit**

```bash
git add tools/_tour.js
git commit -m "feat: add _tour.js skeleton with Driver.js lazy load and state management"
```

---

### Task 2: Define all 15 tour steps

**Files:**
- Modify: `tools/_tour.js`

**Step 1: Add step definitions array**

Replace the comment block "Step definitions (populated per-page)" with the full step array. Each step has `{element, popover, page, onNext}`.

Steps 1-2 are on the tools listing page (`/tools/`).
Steps 3-4 are on drug-search (`/tools/drug-search/`).
Steps 5-6 are on interaction-checker (`/tools/interaction-checker/`).
Steps 7-9 are on clinical-calculators (`/tools/clinical-calculators/`).
Steps 10-12 are on smart-report-oic (`/tools/smart-report-oic/`).
Step 13 is on symptom-checker (`/tools/symptom-checker/`).
Step 14 is on clinical-decision-support (`/tools/clinical-decision-support/`).
Step 15 is on tools listing again (`/tools/`).

```javascript
  // ── Step definitions ──────────────────────────────────────────────
  var PAGE_TOOLS = '/tools/';
  var PAGE_DRUG = '/tools/drug-search/';
  var PAGE_INTERACTION = '/tools/interaction-checker/';
  var PAGE_CALC = '/tools/clinical-calculators/';
  var PAGE_REPORT = '/tools/smart-report-oic/';
  var PAGE_SX = '/tools/symptom-checker/';
  var PAGE_CDS = '/tools/clinical-decision-support/';

  var STEPS = [
    // ── Step 1: Welcome (Tools listing) ─────────────────────────────
    {
      element: '.main h1',
      page: PAGE_TOOLS,
      popover: {
        title: 'Welcome to MedTechAI',
        description: 'Explore 22 AI-powered clinical tools. This 3-minute tour covers the highlights — drug search, interaction checks, calculators, smart reports, symptom triage, and clinical decision support.',
        position: 'bottom'
      }
    },
    // ── Step 2: Sidebar highlight (Tools listing) ───────────────────
    {
      element: '#uptodate-sidebar',
      page: PAGE_TOOLS,
      popover: {
        title: 'Navigate by Specialty',
        description: 'Use the sidebar to filter tools by category — Pharmacy, Clinical Support, Smart Reports, and Advanced Clinical.',
        position: 'right'
      }
    },
    // ── Step 3: Drug Search — Pre-fill ──────────────────────────────
    {
      element: '#f_drug',
      page: PAGE_DRUG,
      popover: {
        title: 'Drug Search & Insight',
        description: 'Enter any drug name for AI-generated insights: mechanism, dosing, indications, warnings, and more. Try Metformin — the most prescribed diabetes drug worldwide.',
        position: 'bottom'
      },
      onNext: function() {
        setVal('f_drug', 'Metformin');
        saveState(3);
        navigateToStep(4);
      }
    },
    // ── Step 4: Drug Search — Run button ────────────────────────────
    {
      element: '#submitBtn',
      page: PAGE_DRUG,
      popover: {
        title: 'Click to Run',
        description: 'Click "Run Tool" to see the full AI analysis — or continue the tour and come back later.',
        position: 'top'
      },
      onNext: function() {
        saveState(4);
        navigateToStep(5);
      }
    },
    // ── Step 5: Interaction Checker — Pre-fill ──────────────────────
    {
      element: '#f_d1',
      page: PAGE_INTERACTION,
      popover: {
        title: 'Interaction Checker',
        description: 'Check drug-drug interactions across multiple medications. This example shows Warfarin + Ibuprofen + Omeprazole — a common high-risk combination.',
        position: 'bottom'
      },
      onNext: function() {
        setVal('f_d1', 'Warfarin');
        setVal('f_d2', 'Ibuprofen');
        setVal('f_d3', 'Omeprazole');
        saveState(5);
        navigateToStep(6);
      }
    },
    // ── Step 6: Interaction Checker — Run button ────────────────────
    {
      element: '#submitBtn',
      page: PAGE_INTERACTION,
      popover: {
        title: 'Severity-Graded Analysis',
        description: 'Click "Run Tool" to see severity ratings, mechanisms, management recommendations for each interaction pair.',
        position: 'top'
      },
      onNext: function() {
        saveState(6);
        navigateToStep(7);
      }
    },
    // ── Step 7: Clinical Calculators — Select BMI ───────────────────
    {
      element: document.querySelector('.calc-opt') ? '.calc-opt' : ('.calc-grid'),
      page: PAGE_CALC,
      popover: {
        title: 'Clinical Calculators',
        description: 'Validated medical calculators — no API needed. Results compute instantly in your browser. Select BMI to start.',
        position: 'bottom'
      },
      onNext: function() {
        // Click the BMI calculator option
        var bmi = document.querySelector('.calc-opt');
        if (bmi && window.selectCalc) {
          bmi.click();
          // Give the DOM time to create dynamic fields
          setTimeout(function() {
            setVal('cf_wt', '80');
            setVal('cf_ht', '175');
            saveState(7);
            navigateToStep(8);
          }, 100);
        } else {
          saveState(7);
          navigateToStep(8);
        }
      }
    },
    // ── Step 8: Clinical Calculators — Pre-fill values ──────────────
    {
      element: '#cf_wt',
      page: PAGE_CALC,
      popover: {
        title: 'Enter Patient Data',
        description: 'Weight and height are already filled. Click "Run Tool" to see the instant result.',
        position: 'bottom'
      },
      onNext: function() {
        saveState(8);
        navigateToStep(9);
      }
    },
    // ── Step 9: Clinical Calculators — Result ───────────────────────
    {
      element: '.result-panel',
      page: PAGE_CALC,
      popover: {
        title: 'Instant Result',
        description: 'The calculator runs locally in your browser — no server call, no waiting. All validated formulas are built in.',
        position: 'top'
      },
      onNext: function() {
        saveState(9);
        navigateToStep(10);
      }
    },
    // ── Step 10: Smart Report — Select type/modality ────────────────
    {
      element: '#f_type',
      page: PAGE_REPORT,
      popover: {
        title: 'Smart Report (OIC)',
        description: 'Upload a medical scan or paste findings for a structured specialist-grade report with ICD-10 codes and severity grading.',
        position: 'bottom'
      },
      onNext: function() {
        setSel('f_type', 'Radiology');
        setSel('f_mod', 'CT Scan');
        saveState(10);
        navigateToStep(11);
      }
    },
    // ── Step 11: Smart Report — Pre-fill findings ───────────────────
    {
      element: '#f_text',
      page: PAGE_REPORT,
      popover: {
        title: 'Paste Clinical Findings',
        description: 'Describe the scan or paste existing report text. The AI generates a complete structured report with impression, recommendations, and ICD-10 mapping.',
        position: 'bottom'
      },
      onNext: function() {
        setVal('f_text', '2cm enhancing lesion in right hepatic lobe, suspicious for metastasis. Cirrhotic liver morphology noted. No additional focal lesions. Portal vein patent.');
        saveState(11);
        navigateToStep(12);
      }
    },
    // ── Step 12: Smart Report — Run button ──────────────────────────
    {
      element: '#submitBtn',
      page: PAGE_REPORT,
      popover: {
        title: 'Generate Report',
        description: 'Click "Run Tool" to get a structured report with study description, clinical findings, impression, severity grade, urgency, and ICD-10 codes.',
        position: 'top'
      },
      onNext: function() {
        saveState(12);
        navigateToStep(13);
      }
    },
    // ── Step 13: Symptom Checker ────────────────────────────────────
    {
      element: '#f_sx',
      page: PAGE_SX,
      popover: {
        title: 'Symptom Checker',
        description: 'AI triage tool — classifies urgency as emergency, urgent, routine, or self-care. Patient data and symptoms are pre-filled below.',
        position: 'bottom'
      },
      onNext: function() {
        setVal('f_sx', 'Sore throat for 3 days, fever 38.3°C, swollen anterior cervical lymph nodes, difficulty swallowing. No cough or rhinorrhea.');
        setVal('f_age', '30');
        setSel('f_gen', 'Female');
        setVal('f_hx', 'No significant medical history. No known allergies.');
        saveState(13);
        navigateToStep(14);
      }
    },
    // ── Step 14: Clinical Decision Support ──────────────────────────
    {
      element: '#f_sx',
      page: PAGE_CDS,
      popover: {
        title: 'Clinical Decision Support',
        description: 'Comprehensive AI analysis — enter symptoms, history, and medications. Get differential diagnosis, evidence-based workup, and management recommendations with GRADE ratings.',
        position: 'bottom'
      },
      onNext: function() {
        setVal('f_sx', '55-year-old male with acute onset substernal chest pain radiating to left arm, associated with diaphoresis and shortness of breath. Pain started 2 hours ago. Patient is a smoker with hypertension.');
        setVal('f_hx', 'Hypertension, hyperlipidemia, type 2 diabetes. Father had MI at age 58.');
        setVal('f_meds', 'Lisinopril 10mg daily, Atorvastatin 20mg daily, Metformin 500mg BID.');
        saveState(14);
        navigateToStep(15);
      }
    },
    // ── Step 15: Celebration (Tools listing) ────────────────────────
    {
      element: '.main h1',
      page: PAGE_TOOLS,
      popover: {
        title: 'Tour Complete!',
        description: 'You\'ve seen the key features. Explore all 22 tools at your own pace, or dive deeper into any tool you visited. Click Done to start using the platform.',
        position: 'centered'
      },
      onNext: function() {
        clearState();
      }
    }
  ];
```

**Step 2: Wire STEPS into state initialization**

Replace the `state.steps = [];` with `state.steps = STEPS;`

**Step 3: Commit**

```bash
git add tools/_tour.js
git commit -m "feat: define all 15 tour steps with pre-fill and cross-page navigation"
```

---

### Task 3: Add entry points — FAB on tools listing, sidebar item

**Files:**
- Modify: `tools/_tour.js`
- Modify: `tools/index.html`

**Step 1: Add FAB injection function to `_tour.js`**

Add after the `setSel` helper:

```javascript
  // ── Entry point: FAB on tools listing ─────────────────────────────
  function injectTourFAB() {
    if (document.getElementById('tour-fab')) return;
    var fab = document.createElement('button');
    fab.id = 'tour-fab';
    fab.innerHTML = '\uD83C\uDFAF Take the Tour';
    fab.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999;background:#38b8ae;color:#fff;border:none;border-radius:40px;padding:12px 20px;font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 4px 12px rgba(56,184,174,.4);transition:all .2s;display:flex;align-items:center;gap:6px;animation:pulse-tour 2s infinite';
    fab.onmouseover = function() { this.style.transform = 'scale(1.05)'; this.style.boxShadow = '0 6px 20px rgba(56,184,174,.5)'; };
    fab.onmouseout = function() { this.style.transform = ''; this.style.boxShadow = '0 4px 12px rgba(56,184,174,.4)'; };
    fab.onclick = function() { window.startPlatformTour(); };
    document.body.appendChild(fab);
  }

  // ── Add tour item to sidebar ─────────────────────────────────────
  function injectTourSidebarItem() {
    var sidebar = document.getElementById('uptodate-sidebar');
    if (!sidebar) return;
    // Don't add if already present
    if (sidebar.querySelector('.sidebar-tour')) return;
    var item = document.createElement('a');
    item.className = 'sidebar-item sidebar-tour';
    item.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 16px;font-size:13px;color:#6b7280;text-decoration:none;cursor:pointer;border-top:1px solid var(--border);margin-top:8px';
    item.innerHTML = '\uD83C\uDFAF Platform Tour';
    item.onclick = function(e) { e.preventDefault(); window.startPlatformTour(); };
    sidebar.appendChild(item);
  }
```

**Step 2: Add pulse animation to shared.css**

Add at the end of `tools/shared.css`:

```css
@keyframes pulse-tour{0%{box-shadow:0 4px 12px rgba(56,184,174,.4)}50%{box-shadow:0 4px 20px rgba(56,184,174,.7)}100%{box-shadow:0 4px 12px rgba(56,184,174,.4)}}
```

**Step 3: Wire entry points into DOMContentLoaded**

In the `DOMContentLoaded` handler, after `resumeTour()`:

```javascript
    // Add entry points
    if (document.querySelector('.tool-srch')) {
      injectTourFAB();
    }
    // Wait for sidebar to be injected by _upload.js, then add tour item
    var sidebarInterval = setInterval(function() {
      var sidebar = document.getElementById('uptodate-sidebar');
      if (sidebar) {
        injectTourSidebarItem();
        clearInterval(sidebarInterval);
      }
    }, 200);
    // Stop checking after 5 seconds
    setTimeout(function() { clearInterval(sidebarInterval); }, 5000);
```

**Step 4: Commit**

```bash
git add tools/_tour.js tools/shared.css
git commit -m "feat: add tour entry points — FAB on tools listing, sidebar item on all pages"
```

---

### Task 4: Wire `_tour.js` into all tool pages

**Files:**
- Modify: `tools/_upload.js`

**Step 1: Add _tour.js script injection**

In `_upload.js`, inside the `DOMContentLoaded` handler, after `injectSearchBar()` and `injectSidebar()`:

```javascript
    // Inject tour script (lazy — only loads Driver.js when user starts tour)
    if (!document.getElementById('tour-script')) {
      var ts = document.createElement('script');
      ts.id = 'tour-script';
      ts.src = '/tools/_tour.js';
      document.body.appendChild(ts);
    }
```

**Step 2: Commit**

```bash
git add tools/_upload.js
git commit -m "feat: wire _tour.js into all tool pages via _upload.js"
```

---

### Task 5: Verify and deploy

**Step 1: Manual verification checklist**

1. Open `https://pharmgenius-production-main.onrender.com/tools/` (or local dev)
2. Verify FAB "🎯 Take the Tour" appears at bottom-right
3. Click FAB → tour starts with Step 1 (Welcome popover on "All Tools" title)
4. Click Next → Step 2 highlights sidebar
5. Click Next → navigates to Drug Search, pre-fills "Metformin"
6. Click Next → Step 4 highlights Run button
7. Click Next → navigates to Interaction Checker, pre-fills 3 drugs
8. Click Next → highlights Run button
9. Click Next → navigates to Clinical Calculators, selects BMI
10. Click Next → pre-fills 80/175
11. Click Next → Step 9 (may need to run tool to show result panel — skip if not visible)
12. Click Next → navigates to Smart Report, selects Radiology + CT
13. Click Next → pre-fills findings text
14. Click Next → highlights Run button
15. Click Next → navigates to Symptom Checker, pre-fills all fields
16. Click Next → navigates to Clinical Decision Support, pre-fills all fields
17. Click Next → navigates to Tools listing → celebration popover
18. Click Done → tour ends, state cleared

**Step 2: Check browser console** for any errors during tour steps.

**Step 3: Test Skip/Close** — click X on any step → tour closes, state cleared.

**Step 4: Push and deploy**

```bash
git add -A
git commit -m "feat: complete interactive tour — 15 steps across 6 tools with Driver.js"
git push new-origin master
```

Then manual deploy on Render.

---

### Files Changed Summary

| File | Action | Lines |
|------|--------|-------|
| `tools/_tour.js` | Create | ~350 |
| `tools/_upload.js` | Modify (+4) | Add tour script injection |
| `tools/shared.css` | Modify (+1) | Add pulse-tour keyframes |
