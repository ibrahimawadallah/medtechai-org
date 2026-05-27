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
      element: '.calc-grid',
      page: PAGE_CALC,
      popover: {
        title: 'Clinical Calculators',
        description: 'Validated medical calculators — no API needed. Results compute instantly in your browser. Select BMI to start.',
        position: 'bottom'
      },
      onNext: function() {
        var bmi = document.querySelector('.calc-opt');
        if (bmi) {
          bmi.click();
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
      element: '#resultPanel',
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
        setVal('f_type', 'Radiology');
        setVal('f_mod', 'CT Scan');
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
        setVal('f_gen', 'Female');
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

  state.steps = STEPS;

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
    if (state.running) return;
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
  var setSel = setVal;

  // ── FAB (Floating Action Button) entry point ──────────────────────
  function injectTourFAB() {
    if (document.getElementById('tour-fab')) return;
    if (!document.querySelector('.tool-srch')) return;
    var fab = document.createElement('button');
    fab.id = 'tour-fab';
    fab.textContent = '\uD83C\uDFAF Take the Tour';
    Object.assign(fab.style, {
      position:'fixed',bottom:'24px',right:'24px',zIndex:'999',background:'#38b8ae',color:'#fff',border:'none',borderRadius:'40px',padding:'12px 20px',fontSize:'14px',fontWeight:'700',fontFamily:'inherit',cursor:'pointer',boxShadow:'0 4px 12px rgba(56,184,174,.4)',transition:'all .2s',display:'flex',alignItems:'center',gap:'6px',animation:'pulse-tour 2s infinite'
    });
    fab.onmouseenter = function() { fab.style.transform = 'scale(1.05)'; fab.style.boxShadow = '0 6px 20px rgba(56,184,174,.5)'; };
    fab.onmouseleave = function() { fab.style.transform = ''; fab.style.boxShadow = ''; };
    fab.onclick = function() { window.startPlatformTour(); };
    document.body.appendChild(fab);
  }

  // ── Sidebar tour item entry point ─────────────────────────────────
  function injectTourSidebarItem() {
    var sidebar = document.getElementById('uptodate-sidebar');
    if (!sidebar) return;
    if (sidebar.querySelector('.sidebar-tour')) return;
    var a = document.createElement('a');
    a.className = 'sidebar-tour';
    a.textContent = '\uD83C\uDFAF Platform Tour';
    Object.assign(a.style, {
      display:'flex',alignItems:'center',gap:'8px',padding:'8px 16px',fontSize:'13px',color:'#6b7280',textDecoration:'none',cursor:'pointer',borderTop:'1px solid var(--border)',marginTop:'8px'
    });
    a.onclick = function() { window.startPlatformTour(); };
    sidebar.appendChild(a);
  }

  // ── Initialize ────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function() {
    if (resumeTour()) return;
    // Entry points
    if (document.querySelector('.tool-srch')) {
      injectTourFAB();
    }
    // Wait for sidebar to be injected by _upload.js
    var si = setInterval(function() {
      if (document.getElementById('uptodate-sidebar')) {
        injectTourSidebarItem();
        clearInterval(si);
      }
    }, 200);
    setTimeout(function() { clearInterval(si); }, 5000);
  });

  // Expose for entry points
  window.startPlatformTour = function() { startTour(0); };
})();
