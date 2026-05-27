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
