# Rx Upload — Unified Medical Document Analyzer Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Single upload for any medical document — one OCR pass returns pharmacy, lab, and safety analysis in a clinical dashboard.

**Architecture:** New `rx-upload` case in handler.php with a comprehensive AI prompt that classifies the document type and runs all relevant analyses in one call. New HTML page with sidebar dashboard layout. Reuses existing centralized OCR pipeline and `_upload.js`.

**Tech Stack:** PHP 8.1 (handler.php), Vanilla JS, Gemini 1.5 Flash / Groq Llama-3.3-70B / OpenRouter fallback chain

---

### Task 1: Add `rx-upload` case to `handler.php`

**Files:**
- Modify: `api/tools/handler.php` — insert new case before line 827 (`default:`)

**Step 1: Add the new case block**

Insert before `default:`:
```php
// RX UPLOAD — Unified Medical Document Analyzer
case 'rx-upload':
    $docText = $body['text'] ?? '';
    $context = $fileContext ? $fileContext . "User also provided text:\n$docText\n\n---\n\n" : ($docText ? "Analysis of: $docText\n\n" : '');
    if (!$context) { echo json_encode(['html'=>alert('red','Paste or upload a medical document to analyze.')]); exit; }
    $d = gemini($context . "Analyze this medical document comprehensively. First classify the document type, then analyze ALL relevant sections.

Return ONLY JSON with these sections (omit any that don't apply):
{
  \"documentType\": \"prescription|labs|both|other\",
  \"summary\": \"Overall clinical summary\",
  \"pharmacy\": {
    \"drugsFound\": [\"Drug A\", \"Drug B\"],
    \"drugDetails\": [{\"genericName\":\"str\",\"brandNames\":[\"str\"],\"drugClass\":\"str\",\"indications\":[\"str\"],\"dosageForms\":[\"str\"],\"adultDosing\":\"str\",\"contraindications\":[\"str\"],\"clinicalWarnings\":[\"str\"]}],
    \"interactions\": [{\"between\":[\"A\",\"B\"],\"severity\":\"high|moderate|low\",\"description\":\"str\",\"management\":\"str\"}],
    \"doseCheck\": [{\"drug\":\"str\",\"prescribedDose\":\"str\",\"assessment\":\"str\",\"concern\":\"Yes|No\"}],
    \"g6pdCheck\": [{\"drug\":\"str\",\"riskLevel\":\"Safe|Low Risk|Moderate Risk|High Risk|Contraindicated\"}],
    \"pregnancyCheck\": {\"fdaCategory\":\"A|B|C|D|X|N\",\"safety\":\"Safe|Caution|Avoid\",\"risk\":\"str\"},
    \"safetyCheck\": {\"overallSafety\":\"Safe|Caution|High Risk\",\"lasaRisk\":\"Yes|No\",\"highAlert\":\"Yes|No\",\"allergyConflict\":\"str\"}
  },
  \"labs\": {
    \"abnormalValues\": [{\"test\":\"str\",\"value\":\"str\",\"flag\":\"High|Low|Critical\",\"interpretation\":\"str\",\"normalRange\":\"str\"}],
    \"criticalValues\": [\"str\"],
    \"overallAssessment\": \"str\",
    \"recommendations\": [\"str\"],
    \"organSystemImpact\": \"str\"
  },
  \"recommendations\": [\"str\"],
  \"urgency\": \"Routine|Urgent|Emergency\"
}
");
    if (isset($d['error'])) { echo json_encode(['html'=>alert('red',h($d['error']))]); exit; }
    echo json_encode(['data' => $d]);
    break;
```

**Step 2: Verify the syntax**

Run: `php -l api/tools/handler.php` — Expected: `No syntax errors detected`

---

### Task 2: Create `tools/rx-upload/index.html`

**Files:**
- Create: `tools/rx-upload/index.html`
- Reference: `tools/lab-analyzer/index.html`, `tools/drug-search/index.html`

**Step 1: Create the page structure**

Full HTML page with:
- `<head>`: meta, title, Google Fonts (DM Sans + Inter), shared.css, _upload.js
- `<body>`: nav bar, category banner (smart-report/teal), breadcrumb, dashboard layout
- Dashboard has sidebar (left, 260px) + main content (right)
- Sidebar: upload area at top, services list below, action buttons at bottom
- Main content: placeholder card + result panel with dynamic sections

**Page layout:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Medical Document Analyzer — Arab MedTechAI</title>
  <meta name="description" content="Upload a prescription or lab report for comprehensive AI analysis — drug info, interactions, safety, and lab interpretation in one place."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/tools/shared.css"/>
  <script src="/tools/_upload.js"></script>
  <style>
    /* Prevent _upload.js from injecting its own sidebar (conflicts with .rx-sidebar) */
    #uptodate-sidebar { display: none !important; }
    /* Adjust injected search bar z-index so it doesn't overlap sidebar controls */
    #uptodate-search { z-index: 1001; }
  </style>
</head>
<body>
  <!-- Prevent _upload.js sidebar injection by placing an early hidden element -->
  <div id="uptodate-sidebar" style="display:none"></div>
  <!-- Hidden file input for paste support -->
  <input type="file" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" class="hidden" onchange="handleFile(this)"/>

  <nav class="nav">...</nav>
  <div class="cat-banner smart-report"></div>
  <div class="breadcrumb">...</div>

  <div class="rx-dashboard">
    <!-- Sidebar -->
    <aside class="rx-sidebar">
      <!-- Upload Area -->
      <div class="rx-upload-section">
        <div class="rx-upload-label">Upload Document</div>
        <div class="rx-dropzone" id="upArea" onclick="document.getElementById('upFile').click()">
          <!-- reuses _upload.js IDs -->
          <input type="file" id="upFile" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" class="hidden" onchange="handleUpload(this)">
          <div id="upPlaceholder">
            <div class="rx-dropzone-icon">+</div>
            <div class="rx-dropzone-text">Click or drag a photo or PDF</div>
            <div class="rx-dropzone-hint">Prescription, lab report, or medical document</div>
          </div>
          <div id="upPreview" class="hidden">
            <img id="upImg" class="preview-image">
            <div id="upName" class="file-name"></div>
            <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();removeUpload()">Remove</button>
          </div>
        </div>
        <div class="rx-or-divider"><span>or paste text</span></div>
        <textarea id="f_text" class="rx-text-input" placeholder="Paste lab values, prescription text, or clinical notes..." oninput="autoGrow(this)"></textarea>
        <div style="font-size:11px;color:var(--slate5);text-align:center;margin-top:6px">Ctrl+Enter to analyze</div>
        <button class="rx-analyze-btn" onclick="runTool()">
          <span class="spinner" id="spinner"></span>
          <span id="btnText">Analyze Document</span>
        </button>
      </div>

      <!-- Services List (populated after analysis, icons added by JS) -->
      <div class="rx-services" id="servicesList">
        <div class="rx-services-label">Analysis Results</div>
        <div class="rx-service-item" data-section="summary">Summary</div>
        <div class="rx-service-item" data-section="pharmacy">Pharmacy</div>
        <div class="rx-service-item" data-section="interactions">Interactions</div>
        <div class="rx-service-item" data-section="safety">Safety</div>
        <div class="rx-service-item" data-section="labs">Lab Analysis</div>
      </div>

      <!-- Actions -->
      <div class="rx-actions">
        <button class="rx-action-btn" onclick="window.print()">Print Report</button>
        <button class="rx-action-btn" onclick="resetTool()">New Analysis</button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="rx-content">
      <div class="card" id="placeholderCard">
        <div class="placeholder-card">
          <h3>Medical Document Analyzer</h3>
          <p>Upload a prescription or lab report for comprehensive analysis. Or paste clinical text above.</p>
        </div>
      </div>
      <div class="result-panel" id="resultPanel">
        <div id="resultBody"></div>
      </div>
    </main>
  </div>

  <footer class="footer">&copy; 2025 Arab MedTechAI Organization</footer>

  <noscript><p style="text-align:center;padding:20px;color:var(--slate5);font-size:13px">JavaScript required for document analysis.</p></noscript>

  <script src="/tools/rx-upload.js"></script>
</body>
</html>
```

**Step 2: Create the JS logic**

Create `tools/rx-upload.js` with:
- `getFormData()` — collects text + file (same pattern as lab-analyzer, but allows empty text if file present)
- `runTool()` — POST to `/api/tools/rx-upload`, handles JSON response
- `showResults(data)` — renders dynamic sections based on what's in the response
- `renderPharmacy(d)` — renders drug info, interactions, dose, G6PD, pregnancy, safety cards
- `renderLabs(d)` — renders lab analysis card with abnormal values, critical alerts
- `renderSummary(d)` — overall summary and urgency badge
- `resetTool()` — clears all results

**Step 3: Create CSS styles**

Add to `shared.css` or inline in the page:
- `.rx-dashboard` — flex/grid container for sidebar + content
- `.rx-sidebar` — fixed sidebar (260px, teal brand styling)
- `.rx-content` — flex-grow main area
- `.rx-dropzone` — teal dashed border upload area
- `.rx-service-item` — clickable service list items
- `.rx-service-item.active` — highlighted with teal left border
- `.rx-analyze-btn` — teal primary button
- `.rx-card` — result cards within the content area
- Mobile: hide sidebar, stack vertically

**Icon definitions — 16×16 inline SVGs consistent style:**

```javascript
// All icons: 16×16, viewBox="0 0 16 16", 1.5px stroke, round caps/joins, currentColor
var ICONS = {
  summary: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="3" y="2" width="10" height="12" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="6" x2="10.5" y2="6" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="8.5" x2="10.5" y2="8.5" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="11" x2="8.5" y2="11" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 2v-1h3v1" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  pharmacy: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="4.5" y="2" width="7" height="12" rx="3.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="8" y1="2" x2="8" y2="14" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 1.5"/></svg>',
  interactions: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M2 8l3-3v2h6V5l3 3-3 3v-2H5v2L2 8z" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  safety: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 1.5l6 2.5v4c0 3.5-2.5 6.5-6 7.5-3.5-1-6-4-6-7.5V4L8 1.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6 8.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  labs: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M6 14a3 3 0 01-3-3V2h6v9a3 3 0 01-3 3z" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="4" y1="5.5" x2="8" y2="5.5" stroke="currentColor" stroke-width="1.5"/></svg>',
  recommendations: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="2" y="2.5" width="12" height="11" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M5.5 7.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="11" x2="9.5" y2="11" stroke="currentColor" stroke-width="1.5"/></svg>',
  g6pd: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 2.5C6 5.5 4 8 4 10c0 2.2 1.8 4 4 4s4-1.8 4-4c0-2-2-4.5-4-7.5z" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  pregnancy: '<svg viewBox="0 0 16 16" width="16" height="16"><circle cx="8" cy="4" r="2" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M4 14c0-3 1.8-4.5 4-4.5s4 1.5 4 4.5" stroke="currentColor" fill="none" stroke-width="1.5"/><circle cx="11" cy="3" r="1.2" stroke="currentColor" fill="none" stroke-width="1.2"/></svg>',
  urgency: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 1.5c-2.5 0-4 2-4 4.5v2L2.5 10.5h11L12 8V6c0-2.5-1.5-4.5-4-4.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6.5 11.5a1.5 1.5 0 003 0" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>'
};

function icon(name) { return ICONS[name] || ''; }
```

**Helpers and tool logic:**

```javascript
function esc(s) { return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function badge(l) {
  var m = { 'emergency':'badge-red', 'urgent':'badge-amber', 'routine':'badge-blue' };
  var c = m[(l||'').toLowerCase()] || 'badge-blue';
  return '<span class="badge ' + c + '">' + esc((l||'ROUTINE').toUpperCase()) + '</span>';
}
function autoGrow(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }

var EP = '/api/tools/rx-upload';

function getFormData() {
  var t = document.getElementById('f_text').value.trim();
  if (!t && !_upFile) { alert('Upload a document or paste text to analyze.'); return null; }
  var d = { text: t };
  if (_upFile) {
    var fd = new FormData();
    for (var k in d) fd.append(k, d[k]);
    fd.append('file', _upFile);
    return fd;
  }
  return d;
}

async function runTool() {
  var btn = document.querySelector('.rx-analyze-btn');
  var sp = document.getElementById('spinner');
  var txt = document.getElementById('btnText');
  var body = getFormData();
  if (!body) return;
  btn.disabled = true; sp.classList.add('on'); txt.textContent = 'Analyzing\u2026';
  try {
    var opts = { method: 'POST' };
    if (body instanceof FormData) { opts.body = body; }
    else { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(body); }
    var res = await fetch(EP, opts);
    var data = await res.json();
    if (data.data) renderDashboard(data.data);
    else showFallback(data);
  } catch (e) {
    document.getElementById('resultBody').innerHTML = '<div class="r-error"><span class="r-error-icon">\u26A0</span><div><strong>Connection Error</strong><br>Check that the server is running.</div></div>';
    document.getElementById('placeholderCard').style.display = 'none';
    document.getElementById('resultPanel').classList.add('on');
  } finally {
    btn.disabled = false; sp.classList.remove('on'); txt.textContent = 'Analyze Document';
  }
}

function showFallback(d) {
  var h = d.html || (d.error ? '<div class="r-error">' + esc(d.error) + '</div>' : '<pre>' + esc(JSON.stringify(d, null, 2)) + '</pre>');
  document.getElementById('resultBody').innerHTML = h;
  document.getElementById('placeholderCard').style.display = 'none';
  document.getElementById('resultPanel').classList.add('on');
}

function resetTool() {
  document.getElementById('placeholderCard').style.display = '';
  document.getElementById('resultPanel').classList.remove('on');
  document.getElementById('resultBody').innerHTML = '';
  document.getElementById('f_text').value = '';
  removeUpload();
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && e.ctrlKey) runTool();
});
```

**The key rendering logic:**

```javascript
// Dynamic rendering based on AI response
function renderDashboard(data) {
  var html = '';
  var hasPharmacy = data.pharmacy && data.pharmacy.drugsFound && data.pharmacy.drugsFound.length;
  var hasLabs = data.labs && data.labs.abnormalValues && data.labs.abnormalValues.length;

  // Show/hide sidebar items + inject icons
  document.getElementById('servicesList').querySelectorAll('.rx-service-item').forEach(function(el) {
    var section = el.getAttribute('data-section');
    // Inject icon before text
    var txt = el.textContent.trim();
    el.innerHTML = icon(section) + ' ' + txt;
    if (section === 'summary') { el.style.display = ''; return; }
    if (section === 'pharmacy' || section === 'interactions' || section === 'safety') {
      el.style.display = hasPharmacy ? '' : 'none';
      return;
    }
    if (section === 'labs') {
      el.style.display = hasLabs ? '' : 'none';
      return;
    }
  });

  // Summary section (always shown)
  html += '<div class="rx-card"><div class="rx-card-header teal"><span>' + icon('summary') + ' Clinical Summary</span>';
  html += '<span>' + badge(data.urgency || 'Routine') + '</span></div>';
  html += '<div class="rx-card-body">' + esc(data.summary || 'No summary available.') + '</div></div>';

  // Pharmacy section
  if (hasPharmacy) {
    html += '<div class="rx-card"><div class="rx-card-header navy"><span>' + icon('pharmacy') + ' Pharmacy Services</span></div><div class="rx-card-body">';
    html += '<div class="rx-drug-list">';
    data.pharmacy.drugDetails.forEach(function(drug) {
      html += '<div class="rx-drug-card"><div class="rx-drug-name">' + esc(drug.genericName) + '</div>';
      html += '<div class="rx-drug-meta">' + esc(drug.drugClass) + ' · ' + esc(drug.adultDosing) + '</div>';
      html += '<div class="rx-drug-indications">' + esc(drug.indications ? drug.indications.join(', ') : '') + '</div></div>';
    });
    html += '</div></div></div>';

    // Interactions
    if (data.pharmacy.interactions && data.pharmacy.interactions.length) {
      html += '<div class="rx-card"><div class="rx-card-header amber"><span>' + icon('interactions') + ' Drug Interactions</span></div><div class="rx-card-body">';
      html += '<div class="r-list">';
      data.pharmacy.interactions.forEach(function(ix) {
        var sevClass = ix.severity === 'high' ? 'badge-red' : (ix.severity === 'moderate' ? 'badge-amber' : 'badge-green');
        html += '<div class="rx-ix-item"><span class="badge ' + sevClass + '">' + esc(ix.severity.toUpperCase()) + '</span> ';
        html += '<strong>' + esc(ix.between.join(' + ')) + '</strong><br>';
        html += '<span class="rx-ix-desc">' + esc(ix.description) + '</span></div>';
      });
      html += '</div></div></div>';
    }

    // G6PD check
    if (data.pharmacy.g6pdCheck && data.pharmacy.g6pdCheck.length) {
      html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('g6pd') + ' G6PD Check</span></div><div class="rx-card-body">';
      data.pharmacy.g6pdCheck.forEach(function(g) {
        var cls = g.riskLevel === 'Contraindicated' || g.riskLevel === 'High Risk' ? 'badge-red' : (g.riskLevel === 'Moderate Risk' ? 'badge-amber' : 'badge-green');
        html += '<div><span class="badge ' + cls + '">' + esc(g.riskLevel.toUpperCase()) + '</span> <strong>' + esc(g.drug) + '</strong></div>';
      });
      html += '</div></div>';
    }

    // Pregnancy check
    if (data.pharmacy.pregnancyCheck && data.pharmacy.pregnancyCheck.fdaCategory) {
      html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('pregnancy') + ' Pregnancy Safety</span></div><div class="rx-card-body">';
      html += '<span class="badge ' + (data.pharmacy.pregnancyCheck.safety === 'Avoid' ? 'badge-red' : 'badge-amber') + '">' + esc(data.pharmacy.pregnancyCheck.fdaCategory) + '</span> ';
      html += esc(data.pharmacy.pregnancyCheck.risk);
      html += '</div></div>';
    }
  }

  // Labs section
  if (hasLabs) {
    html += '<div class="rx-card"><div class="rx-card-header green"><span>' + icon('labs') + ' Lab Analysis</span></div><div class="rx-card-body">';
    html += '<div class="r-list">';
    data.labs.abnormalValues.forEach(function(lab) {
      var flagClass = lab.flag === 'Critical' ? 'r-alert-red' : (lab.flag === 'High' || lab.flag === 'Low' ? 'r-alert-amber' : 'r-alert-green');
      html += '<div class="r-alert ' + flagClass + '"><div><strong>' + esc(lab.test) + '</strong> ';
      html += '<span class="badge ' + (lab.flag === 'Critical' ? 'badge-red' : (lab.flag === 'High' || lab.flag === 'Low' ? 'badge-amber' : 'badge-green')) + '">' + esc(lab.flag) + '</span> ';
      html += '<span style="font-weight:700;margin-left:4px">' + esc(lab.value) + '</span><br>';
      if (lab.normalRange) html += '<span style="font-size:11px;color:var(--slate5)">NR: ' + esc(lab.normalRange) + '</span><br>';
      html += '<span style="font-size:12px">' + esc(lab.interpretation) + '</span></div></div>';
    });
    html += '</div>';
    if (data.labs.overallAssessment) html += '<div class="rx-assessment">' + esc(data.labs.overallAssessment) + '</div>';
    html += '</div></div>';
  }

  // Recommendations
  if (data.recommendations && data.recommendations.length) {
    html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('recommendations') + ' Recommendations</span></div><div class="rx-card-body">';
    html += '<ul class="r-list">';
    data.recommendations.forEach(function(r) { html += '<li>' + esc(r) + '</li>'; });
    html += '</ul></div></div>';
  }

  document.getElementById('resultBody').innerHTML = html;
  document.getElementById('placeholderCard').style.display = 'none';
  document.getElementById('resultPanel').classList.add('on');

  // Highlight sidebar item on scroll
  setupScrollSpy();
}
```

---

### Task 3: Add scroll-spy sidebar navigation

**Step 1: Add scroll-spy JS to `rx-upload.js`**

When user scrolls, highlight the current section in the sidebar. Clicking sidebar items scrolls to that section.

```javascript
function setupScrollSpy() {
  var items = document.querySelectorAll('.rx-service-item');
  var cards = document.querySelectorAll('.rx-card');
  items.forEach(function(item) {
    item.addEventListener('click', function() {
      var target = this.getAttribute('data-section');
      cards.forEach(function(c) {
        if (c.querySelector('.rx-card-header') && c.querySelector('.rx-card-header').textContent.toLowerCase().includes(target)) {
          c.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  });
}
```

---

### Task 4: Add CSS styles to `shared.css`

**Step 1: Add dashboard layout styles**

Add after `.sidebar` block (after line 54):
```css
.rx-dashboard{display:flex;min-height:calc(100vh - 48px - 56px);max-width:1200px;margin:0 auto;width:100%}
/* sticky top: 96px = 48px injected search bar + 48px .nav bar */
.rx-sidebar{width:280px;flex-shrink:0;background:var(--teal-bg);border-right:1px solid var(--border);padding:20px;display:flex;flex-direction:column;gap:20px;position:sticky;top:96px;height:calc(100vh - 96px - 56px);overflow-y:auto}
.rx-content{flex:1;padding:24px 32px;min-width:0}
.rx-upload-section{background:#fff;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow)}
.rx-upload-label{font-size:11px;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px}
.rx-dropzone{border:2px dashed var(--teal);border-radius:var(--radius);padding:24px 16px;text-align:center;cursor:pointer;transition:background .15s}
.rx-dropzone:hover{background:var(--teal-bg)}
.rx-dropzone.drag-over{background:var(--teal-bg);border-color:var(--teal-h)}
.rx-dropzone-icon{font-size:28px;color:var(--teal);font-weight:300;line-height:1;margin-bottom:4px}
.rx-dropzone-text{font-size:13px;font-weight:600;color:var(--slate3);margin-bottom:2px}
.rx-dropzone-hint{font-size:11px;color:var(--slate5)}
.rx-or-divider{text-align:center;font-size:11px;color:var(--slate5);position:relative;margin:12px 0}
.rx-or-divider::before,.rx-or-divider::after{content:'';position:absolute;top:50%;width:calc(50% - 20px);height:1px;background:var(--border)}
.rx-or-divider::before{left:0}
.rx-or-divider::after{right:0}
.rx-or-divider span{background:#fff;padding:0 8px;position:relative}
.rx-text-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:13px;font-family:monospace;color:var(--slate);background:var(--surface);outline:none;resize:vertical;min-height:80px;transition:border-color .15s}
.rx-text-input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(56,184,174,.15)}
.rx-analyze-btn{width:100%;padding:10px 16px;border:none;border-radius:var(--radius);background:var(--teal);color:#fff;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:6px}
.rx-analyze-btn:hover{background:var(--teal-h)}
.rx-analyze-btn:disabled{opacity:.5;cursor:not-allowed}
.rx-services{background:#fff;border-radius:var(--radius);padding:12px;box-shadow:var(--shadow)}
.rx-services-label{font-size:11px;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;padding:0 8px}
.rx-service-item{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;color:var(--slate3);border-left:3px solid transparent;cursor:pointer;border-radius:0 4px 4px 0;margin-bottom:2px;transition:all .15s}
.rx-service-item:hover{background:var(--teal-bg);color:var(--teal)}
.rx-service-item.active{border-left-color:var(--teal);background:var(--teal-bg);color:var(--teal);font-weight:600}
.rx-service-item svg,.rx-card-header span svg{flex-shrink:0;vertical-align:middle;margin-right:4px;display:inline-block}
.rx-actions{display:flex;flex-direction:column;gap:6px}
.rx-action-btn{width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);background:#fff;font-size:12px;font-weight:600;color:var(--slate3);cursor:pointer;transition:all .15s;font-family:inherit;text-align:center}
.rx-action-btn:hover{background:var(--teal-bg);border-color:var(--teal);color:var(--teal)}
.rx-card{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:16px;overflow:hidden;box-shadow:var(--shadow)}
.rx-card-header{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;border-bottom:1px solid var(--border)}
.rx-card-header.teal{background:var(--teal-bg);color:var(--teal);border-left:3px solid var(--teal)}
.rx-card-header.navy{background:#f0f4f8;color:var(--navy);border-left:3px solid var(--navy)}
.rx-card-header.amber{background:var(--amber-bg);color:var(--amber);border-left:3px solid var(--amber)}
.rx-card-header.green{background:var(--green-bg);color:var(--green);border-left:3px solid var(--green)}
.rx-card-header.purple{background:var(--purple-bg);color:var(--purple);border-left:3px solid var(--purple)}
.rx-card-body{padding:16px 18px;line-height:1.6;font-size:14px}
.rx-drug-list{display:flex;flex-direction:column;gap:10px}
.rx-drug-card{padding:12px;background:var(--teal-bg);border-radius:var(--radius);border-left:3px solid var(--teal)}
.rx-drug-name{font-weight:700;color:var(--navy);font-size:15px;font-family:'DM Sans',sans-serif}
.rx-drug-meta{font-size:12px;color:var(--slate4);margin:2px 0}
.rx-drug-indications{font-size:12px;color:var(--slate3)}
.rx-ix-item{padding:10px 0;border-bottom:1px solid #f1f5f9}
.rx-ix-item:last-child{border-bottom:none}
.rx-ix-desc{font-size:12px;color:var(--slate4)}
.rx-assessment{padding:12px;background:var(--green-bg);border-radius:var(--radius);margin-top:12px;font-size:13px;color:var(--slate)}
@media(max-width:900px){.rx-dashboard{flex-direction:column}.rx-sidebar{width:100%;position:static;height:auto;border-right:none;border-bottom:1px solid var(--border)}.rx-content{padding:16px}}
```

---

### Task 5: Create `tools/rx-upload/rx-upload.js` — the JavaScript logic

**Files:**
- Create: `tools/rx-upload/rx-upload.js`

The JS handles:
- `ICONS` map — 16×16 SVG icon definitions for every service (summary, pharmacy, interactions, safety, labs, g6pd, pregnancy, recommendations, urgency)
- `icon(name)` — returns SVG string for given service name
- `esc(s)` — HTML-escapes a string (null-safe)
- `badge(label)` — urgency badge HTML
- `autoGrow(el)` — auto-expands textarea on input
- `getFormData()` — validates text or file present; if both missing shows alert and returns null
- `runTool()` — disables button, shows spinner + "Analyzing…", POSTs to API, calls renderDashboard on success, restores button on error/finally
- `renderDashboard(data)` — injects SVG icons into sidebar items, renders section cards with matching icons in headers
- `resetTool()` — clear everything
- `setupScrollSpy()` — sidebar click-to-scroll, icon-injected items clickable
- Ctrl+Enter keydown listener on textarea triggers runTool

---

### Task 6: Test the full flow

**Step 1: Manual test — Upload a prescription image**

1. Open `http://localhost:XXXX/tools/rx-upload/`
2. Upload a sample prescription image (e.g., amoxicillin + metformin)
3. Verify OCR extracts text
4. Verify the dashboard shows: Clinical Summary, Pharmacy Services (with drug details + interactions), Recommendations
5. Verify sidebar highlights sections on scroll

**Step 2: Manual test — Paste lab values**

1. Paste: `WBC: 13.8 x 10³/µL (H), HGB: 9.4 g/dL (L), Creatinine: 1.8 mg/dL (H)`
2. Click Analyze
3. Verify dashboard shows: Clinical Summary, Lab Analysis (with flagged values), Recommendations
4. Verify Pharmacy section is hidden

**Step 3: Manual test — Empty validation**

1. Click Analyze without any input
2. Verify browser alert: "Upload a document or paste text to analyze."

---

### Task 7: Final review

- Ensure `shared.css` additions follow existing conventions
- Verify all PHP syntax with `php -l`
- Verify all tool ID patterns (nav, breadcrumb, footer) match other tools
- Check mobile responsive layout
