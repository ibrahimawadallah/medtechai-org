# Clinical Calculators Dashboard — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rebuild the Clinical Calculators page as a modern dashboard with category navigation, 20+ calculators across 8 specialties, and hybrid local/AI computation.

**Architecture:** Single HTML file with embedded CSS/JS. Dashboard layout with sidebar categories, calculator grid, and result panel. Local calculators computed client-side; complex scores optionally sent to API for AI interpretation.

**Tech Stack:** HTML5, CSS3 (grid/flexbox), vanilla JavaScript, Google Fonts (Inter, DM Sans), existing `shared.css` framework

---

### Task 1: Create HTML Structure with Dashboard Layout

**Files:**
- Modify: `tools/clinical-calculators/index.html`

**Step 1: Write the complete HTML structure**

Replace entire file with new dashboard layout:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Clinical Calculators — MedTechAI</title>
  <meta name="description" content="Validated medical calculators — BMI, GFR, CHA₂DS₂-VASc, Wells, APACHE II, SOFA, MELD, and more."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/tools/shared.css"/>
  <script src="/tools/_upload.js"></script>
</head>
<body>

<nav class="nav">
  <a href="/tools/" class="nav-back">&#8592; All Tools</a>
  <span class="nav-sep">|</span>
  <a href="/" class="nav-logo">MedTech<span>AI</span></a>
</nav>

<div class="cat-banner advanced"></div>

<div class="breadcrumb">
  <a href="/">Home</a><span>/</span>
  <a href="/tools/">Tools</a><span>/</span>
  <span>Clinical Calculators</span>
</div>

<div class="tool-header advanced">
  <div>
    <span class="tool-cat advanced">Advanced Clinical</span>
    <h1 class="tool-name">Clinical Calculators</h1>
    <p class="tool-desc">Validated medical calculators — BMI, GFR, CHA₂DS₂-VASc, Wells, APACHE II, SOFA, MELD, and more.</p>
  </div>
</div>

<div class="main dashboard-layout">
  <!-- Category Sidebar -->
  <aside class="calc-sidebar" id="calcSidebar">
    <div class="sidebar-search">
      <input type="text" id="calcSearch" placeholder="Search calculators..." />
    </div>
    <nav class="sidebar-nav">
      <button class="sidebar-btn active" data-cat="all">All Calculators</button>
      <button class="sidebar-btn" data-cat="anthropometrics">Anthropometrics</button>
      <button class="sidebar-btn" data-cat="renal">Renal</button>
      <button class="sidebar-btn" data-cat="electrolytes">Electrolytes</button>
      <button class="sidebar-btn" data-cat="cardiovascular">Cardiovascular</button>
      <button class="sidebar-btn" data-cat="critical-care">Critical Care</button>
      <button class="sidebar-btn" data-cat="hepatology">Hepatology</button>
      <button class="sidebar-btn" data-cat="neurology">Neurology</button>
      <button class="sidebar-btn" data-cat="pulmonary">Pulmonary</button>
    </nav>
  </aside>

  <!-- Calculator Grid -->
  <div class="calc-grid-container">
    <div class="calc-grid" id="calcGrid">
      <!-- Calculators injected by JS -->
    </div>

    <!-- Input Panel (expands when calculator selected) -->
    <div class="calc-input-panel" id="calcInputPanel" style="display:none">
      <div class="input-panel-header">
        <h3 id="inputPanelTitle">Calculator Name</h3>
        <button class="btn-close" id="closeInputPanel">&times;</button>
      </div>
      <div class="input-panel-body" id="calcInputs"></div>
      <div class="input-panel-footer">
        <button class="btn btn-primary" id="calcRunBtn">Calculate</button>
      </div>
    </div>
  </div>

  <!-- Result Panel -->
  <div class="result-panel" id="resultPanel">
    <div id="resultBody"></div>
    <div class="btn-bar no-print">
      <button class="btn btn-outline btn-sm" onclick="window.print()">Print</button>
      <button class="btn btn-outline btn-sm" onclick="clearResult()">Clear</button>
    </div>
  </div>
</div>

<footer class="footer">
  &copy; 2025 Arab MedTechAI Organization
</footer>

</body>
</html>
```

**Step 2: Verify HTML structure loads**

Open in browser, confirm:
- Nav bar renders
- Sidebar shows with categories
- Empty calculator grid visible
- No console errors

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): add dashboard HTML structure"
```

---

### Task 2: Add Dashboard CSS Styles

**Files:**
- Modify: `tools/clinical-calculators/index.html` (add `<style>` block)

**Step 1: Add CSS for dashboard layout**

Insert before closing `</body>`:

```html
<style>
  /* Dashboard Layout */
  .dashboard-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    grid-template-rows: auto auto;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
  }
  .calc-sidebar {
    grid-row: 1 / 3;
    position: sticky;
    top: 20px;
    align-self: start;
  }
  .calc-grid-container {
    min-height: 400px;
  }
  .result-panel {
    grid-column: 2;
  }

  /* Sidebar */
  .sidebar-search {
    margin-bottom: 16px;
  }
  .sidebar-search input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 13px;
    font-family: var(--font);
  }
  .sidebar-search input:focus {
    outline: none;
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(56,184,174,.15);
  }
  .sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .sidebar-btn {
    display: block;
    width: 100%;
    padding: 10px 14px;
    text-align: left;
    font-size: 13px;
    font-weight: 500;
    color: var(--slate3);
    background: transparent;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: all .15s;
  }
  .sidebar-btn:hover {
    background: var(--teal-bg);
    color: var(--teal);
  }
  .sidebar-btn.active {
    background: var(--teal);
    color: #fff;
  }

  /* Calculator Grid */
  .calc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
  }
  .calc-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
  }
  .calc-card:hover {
    border-color: var(--teal);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(56,184,174,.15);
  }
  .calc-card.selected {
    border-color: var(--teal);
    background: var(--teal-bg);
  }
  .calc-card-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--slate);
    margin-bottom: 6px;
  }
  .calc-card-tag {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: 2px 8px;
    border-radius: 10px;
    display: inline-block;
  }
  .tag-anthropometrics { background: #dbeafe; color: #1e40af; }
  .tag-renal { background: #d1fae5; color: #065f46; }
  .tag-electrolytes { background: #fef3c7; color: #92400e; }
  .tag-cardiovascular { background: #fee2e2; color: #991b1b; }
  .tag-critical-care { background: #ede9fe; color: #5b21b6; }
  .tag-hepatology { background: #fce7f3; color: #9d174d; }
  .tag-neurology { background: #e0e7ff; color: #3730a3; }
  .tag-pulmonary { background: #ccfbf1; color: #115e59; }

  /* Input Panel */
  .calc-input-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-top: 20px;
    box-shadow: var(--shadow);
  }
  .input-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
  }
  .input-panel-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--slate);
  }
  .btn-close {
    background: none;
    border: none;
    font-size: 20px;
    color: var(--slate4);
    cursor: pointer;
  }
  .btn-close:hover {
    color: var(--red);
  }
  .input-panel-body {
    padding: 20px;
  }
  .input-panel-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    background: #f8fafc;
  }

  /* Form fields inside panel */
  .input-panel-body .field {
    margin-bottom: 16px;
  }
  .input-panel-body .field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--slate2);
    margin-bottom: 6px;
  }
  .input-panel-body .field input,
  .input-panel-body .field select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 14px;
    font-family: var(--font);
  }
  .input-panel-body .field input:focus,
  .input-panel-body .field select:focus {
    outline: none;
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(56,184,174,.15);
  }
  .input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  /* Result styling */
  .result-panel {
    display: none;
  }
  .result-panel.visible {
    display: block;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    box-shadow: var(--shadow);
  }
  .result-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--teal);
    margin-bottom: 8px;
  }
  .result-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--slate4);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 4px;
  }
  .result-interpretation {
    font-size: 14px;
    color: var(--slate);
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    line-height: 1.6;
  }
  .result-ref {
    font-size: 12px;
    color: var(--slate4);
    margin-top: 8px;
  }

  /* Responsive */
  @media (max-width: 900px) {
    .dashboard-layout {
      grid-template-columns: 1fr;
    }
    .calc-sidebar {
      grid-row: auto;
      position: static;
    }
    .sidebar-nav {
      flex-direction: row;
      flex-wrap: wrap;
      gap: 8px;
    }
    .sidebar-btn {
      width: auto;
    }
    .result-panel {
      grid-column: 1;
    }
    .calc-grid {
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }
  }
  @media (max-width: 500px) {
    .calc-grid {
      grid-template-columns: 1fr 1fr;
    }
    .input-row {
      grid-template-columns: 1fr;
    }
  }
</style>
```

**Step 2: Verify styles render**

Open in browser, confirm:
- Sidebar displays vertically on left
- Calculator grid is empty but layout correct
- Responsive breakpoints work

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): add dashboard CSS styles"
```

---

### Task 3: Define Calculator Data and Render Grid

**Files:**
- Modify: `tools/clinical-calculators/index.html` (add `<script>` block)

**Step 1: Add JavaScript to define calculators and render grid**

Insert before closing `</body>`:

```html
<script>
const CALCULATORS = [
  // Anthropometrics
  { id: 'bmi', name: 'BMI', category: 'anthropometrics', local: true },
  { id: 'bsa', name: 'BSA', category: 'anthropometrics', local: true },
  { id: 'ibw', name: 'Ideal Body Weight', category: 'anthropometrics', local: true },
  { id: 'adj-bw', name: 'Adjusted Body Weight', category: 'anthropometrics', local: true },

  // Renal
  { id: 'gfr', name: 'GFR (CKD-EPI)', category: 'renal', local: true },
  { id: 'crcl', name: 'Creatinine Clearance', category: 'renal', local: true },
  { id: 'fena', name: 'FENa', category: 'renal', local: true },
  { id: 'bun-cr', name: 'BUN/Cr Ratio', category: 'renal', local: true },

  // Electrolytes
  { id: 'corrected-ca', name: 'Corrected Ca²⁺', category: 'electrolytes', local: true },
  { id: 'anion-gap', name: 'Anion Gap', category: 'electrolytes', local: true },
  { id: 'na-correction', name: 'Na Correction', category: 'electrolytes', local: true },
  { id: 'osm-gap', name: 'Osmolality Gap', category: 'electrolytes', local: true },
  { id: 'delta-delta', name: 'Delta-Delta', category: 'electrolytes', local: true },

  // Cardiovascular
  { id: 'chads2-vasc', name: 'CHA₂DS₂-VASc', category: 'cardiovascular', local: true },
  { id: 'wells-dvt', name: 'Wells DVT', category: 'cardiovascular', local: true },
  { id: 'wells-pe', name: 'Wells PE', category: 'cardiovascular', local: true },
  { id: 'timi', name: 'TIMI Score', category: 'cardiovascular', api: true },
  { id: 'grace', name: 'GRACE Score', category: 'cardiovascular', api: true },
  { id: 'heart', name: 'HEART Score', category: 'cardiovascular', local: true },

  // Critical Care
  { id: 'apache-ii', name: 'APACHE II', category: 'critical-care', api: true },
  { id: 'qsofa', name: 'qSOFA', category: 'critical-care', local: true },
  { id: 'sofa', name: 'SOFA', category: 'critical-care', api: true },
  { id: 'curb-65', name: 'CURB-65', category: 'critical-care', local: true },
  { id: 'gcs', name: 'Glasgow Coma Scale', category: 'critical-care', local: true },

  // Hepatology
  { id: 'meld', name: 'MELD', category: 'hepatology', local: true },
  { id: 'meld-na', name: 'MELD-Na', category: 'hepatology', local: true },
  { id: 'child-pugh', name: 'Child-Pugh', category: 'hepatology', local: true },
  { id: 'fib4', name: 'FIB-4', category: 'hepatology', local: true },
  { id: 'apri', name: 'APRI', category: 'hepatology', local: true },

  // Neurology
  { id: 'gcs-neuro', name: 'Glasgow Coma Scale', category: 'neurology', local: true },
  { id: 'alvarado', name: 'Alvarado Score', category: 'neurology', local: true },

  // Pulmonary
  { id: 'wells-pe-pulm', name: 'Wells PE', category: 'pulmonary', local: true },
  { id: 'perc', name: 'PERC Rule', category: 'pulmonary', local: true },
  { id: 'geneva', name: 'Geneva Score', category: 'pulmonary', local: true },
  { id: 'curb-65-pulm', name: 'CURB-65', category: 'pulmonary', local: true },
];

let selectedCalc = null;

function renderGrid(filter = 'all', search = '') {
  const grid = document.getElementById('calcGrid');
  grid.innerHTML = '';
  const filtered = CALCULATORS.filter(c => {
    const catMatch = filter === 'all' || c.category === filter;
    const searchMatch = !search || c.name.toLowerCase().includes(search.toLowerCase());
    return catMatch && searchMatch;
  });
  filtered.forEach(calc => {
    const card = document.createElement('div');
    card.className = 'calc-card' + (selectedCalc?.id === calc.id ? ' selected' : '');
    card.dataset.id = calc.id;
    card.innerHTML = `
      <div class="calc-card-name">${calc.name}</div>
      <span class="calc-card-tag tag-${calc.category}">${calc.category.replace('-', ' ')}</span>
    `;
    card.addEventListener('click', () => selectCalculator(calc));
    grid.appendChild(card);
  });
}

function selectCalculator(calc) {
  selectedCalc = calc;
  renderGrid(currentFilter, document.getElementById('calcSearch').value);
  showInputPanel(calc);
}

let currentFilter = 'all';
document.querySelectorAll('.sidebar-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentFilter = btn.dataset.cat;
    renderGrid(currentFilter, document.getElementById('calcSearch').value);
  });
});

document.getElementById('calcSearch').addEventListener('input', (e) => {
  renderGrid(currentFilter, e.target.value);
});

renderGrid();
</script>
```

**Step 2: Verify grid renders with all calculators**

Open in browser, confirm:
- All 35+ calculators appear in grid
- Category filter buttons work
- Search filters by name
- Clicking a card highlights it

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): add calculator data and render grid"
```

---

### Task 4: Implement Local Calculator Formulas

**Files:**
- Modify: `tools/clinical-calculators/index.html` (add to `<script>` block)

**Step 1: Add local calculator definitions**

```javascript
const LOCAL_CALCS = {
  bmi: {
    fields: [
      { id: 'weight', label: 'Weight (kg)', type: 'number' },
      { id: 'height', label: 'Height (cm)', type: 'number' }
    ],
    calc: (f) => {
      const bmi = f.weight / ((f.height / 100) ** 2);
      let cat = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Normal' : bmi < 30 ? 'Overweight' : bmi < 35 ? 'Obese Class I' : bmi < 40 ? 'Obese Class II' : 'Obese Class III';
      return { value: bmi.toFixed(1) + ' kg/m²', interpretation: 'Category: ' + cat, reference: 'Normal: 18.5–24.9 kg/m²' };
    }
  },
  bsa: {
    fields: [
      { id: 'weight', label: 'Weight (kg)', type: 'number' },
      { id: 'height', label: 'Height (cm)', type: 'number' }
    ],
    calc: (f) => {
      const bsa = Math.sqrt((f.weight * f.height) / 3600);
      return { value: bsa.toFixed(2) + ' m²', interpretation: 'Body Surface Area (Mosteller formula)', reference: 'Adult avg: 1.5–2.0 m²' };
    }
  },
  gfr: {
    fields: [
      { id: 'scr', label: 'Serum Creatinine (mg/dL)', type: 'number' },
      { id: 'age', label: 'Age (years)', type: 'number' },
      { id: 'sex', label: 'Sex', type: 'select', options: ['Female', 'Male'] },
      { id: 'race', label: 'Race', type: 'select', options: ['Non-Black', 'Black'] }
    ],
    calc: (f) => {
      const isFemale = f.sex === 'Female';
      const isBlack = f.race === 'Black';
      const k = isFemale ? 0.7 : 0.9;
      const alpha = isFemale ? -0.241 : -0.302;
      const scr = f.scr;
      let gfr = 142 * Math.pow(Math.min(scr / k, 1), alpha) * Math.pow(Math.max(scr / k, 1), -1.2) * Math.pow(0.9938, f.age);
      if (isFemale) gfr *= 1.012;
      if (isBlack) gfr *= 1.159;
      let stage = gfr >= 90 ? 'G1 – Normal' : gfr >= 60 ? 'G2 – Mildly decreased' : gfr >= 45 ? 'G3a – Mild-moderately decreased' : gfr >= 30 ? 'G3b – Moderately-severely decreased' : gfr >= 15 ? 'G4 – Severely decreased' : 'G5 – Kidney failure';
      return { value: Math.round(gfr) + ' mL/min/1.73m²', interpretation: 'CKD Stage: ' + stage, reference: 'Normal: ≥90 mL/min/1.73m²' };
    }
  },
  'corrected-ca': {
    fields: [
      { id: 'ca', label: 'Serum Calcium (mg/dL)', type: 'number' },
      { id: 'alb', label: 'Serum Albumin (g/dL)', type: 'number' }
    ],
    calc: (f) => {
      const cc = f.ca + 0.8 * (4 - f.alb);
      let interp = cc < 8.5 ? 'Hypocalcemia' : cc > 10.5 ? 'Hypercalcemia' : 'Normal';
      return { value: cc.toFixed(2) + ' mg/dL', interpretation: interp, reference: 'Normal: 8.5–10.5 mg/dL' };
    }
  },
  'anion-gap': {
    fields: [
      { id: 'na', label: 'Sodium (mEq/L)', type: 'number' },
      { id: 'cl', label: 'Chloride (mEq/L)', type: 'number' },
      { id: 'hco3', label: 'Bicarbonate (mEq/L)', type: 'number' }
    ],
    calc: (f) => {
      const ag = f.na - (f.cl + f.hco3);
      let interp = ag > 12 ? 'Elevated — consider MUDPILES' : 'Normal';
      return { value: ag + ' mEq/L', interpretation: interp, reference: 'Normal: 8–12 mEq/L' };
    }
  },
  'chads2-vasc': {
    fields: [
      { id: 'chf', label: 'CHF (1 pt)', type: 'check' },
      { id: 'htn', label: 'Hypertension (1 pt)', type: 'check' },
      { id: 'age75', label: 'Age ≥75 (2 pts)', type: 'check' },
      { id: 'dm', label: 'Diabetes (1 pt)', type: 'check' },
      { id: 'stroke', label: 'Prior Stroke/TIA (2 pts)', type: 'check' },
      { id: 'vascular', label: 'Vascular Disease (1 pt)', type: 'check' },
      { id: 'age65', label: 'Age 65–74 (1 pt)', type: 'check' },
      { id: 'sex', label: 'Female Sex (1 pt)', type: 'check' }
    ],
    calc: (f) => {
      const score = (f.chf ? 1 : 0) + (f.htn ? 1 : 0) + (f.age75 ? 2 : 0) + (f.dm ? 1 : 0) + (f.stroke ? 2 : 0) + (f.vascular ? 1 : 0) + (f.age65 ? 1 : 0) + (f.sex ? 1 : 0);
      let risk = score >= 9 ? 'Very High' : score >= 6 ? 'High' : score >= 3 ? 'Moderate' : score >= 1 ? 'Low-Moderate' : 'Low';
      return { value: score + ' points', interpretation: 'Stroke risk: ' + risk + ' — consider anticoagulation', reference: 'Annual stroke risk: 1.5% (score 0) to 15%+ (score ≥9)' };
    }
  }
  // ... additional local calculators follow same pattern
};
```

**Step 2: Verify calculators compute correctly**

Test BMI calculator:
1. Click BMI card
2. Enter weight: 70, height: 175
3. Click Calculate
4. Result should show "22.9 kg/m²" and "Category: Normal"

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): implement local calculator formulas"
```

---

### Task 5: Build Input Panel Dynamic Rendering

**Files:**
- Modify: `tools/clinical-calculators/index.html` (add to `<script>` block)

**Step 1: Add input panel rendering function**

```javascript
function showInputPanel(calc) {
  const panel = document.getElementById('calcInputPanel');
  const title = document.getElementById('inputPanelTitle');
  const inputs = document.getElementById('calcInputs');
  
  title.textContent = calc.name;
  inputs.innerHTML = '';
  
  const def = LOCAL_CALCS[calc.id];
  if (!def) {
    inputs.innerHTML = '<p style="color:var(--slate4)">This calculator requires server-side computation.</p>';
    panel.style.display = 'block';
    return;
  }
  
  def.fields.forEach(field => {
    const div = document.createElement('div');
    div.className = field.type === 'check' ? 'field-check' : 'field';
    
    if (field.type === 'check') {
      div.innerHTML = `
        <label class="check-label">
          <input type="checkbox" id="cf_${field.id}" />
          <span>${field.label}</span>
        </label>
      `;
    } else if (field.type === 'select') {
      div.innerHTML = `
        <label>${field.label}</label>
        <select id="cf_${field.id}">
          ${field.options.map(o => `<option value="${o}">${o}</option>`).join('')}
        </select>
      `;
    } else {
      div.innerHTML = `
        <label>${field.label}</label>
        <input type="number" id="cf_${field.id}" step="any" />
      `;
    }
    inputs.appendChild(div);
  });
  
  panel.style.display = 'block';
  document.getElementById('calcRunBtn').onclick = () => runLocalCalc(calc);
}

document.getElementById('closeInputPanel').addEventListener('click', () => {
  document.getElementById('calcInputPanel').style.display = 'none';
  selectedCalc = null;
  renderGrid(currentFilter, document.getElementById('calcSearch').value);
});
```

**Step 2: Add run function for local calculations**

```javascript
function runLocalCalc(calc) {
  const def = LOCAL_CALCS[calc.id];
  if (!def) return;
  
  const fields = {};
  def.fields.forEach(f => {
    const el = document.getElementById('cf_' + f.id);
    if (f.type === 'check') {
      fields[f.id] = el.checked;
    } else if (f.type === 'number') {
      fields[f.id] = parseFloat(el.value) || 0;
    } else {
      fields[f.id] = el.value;
    }
  });
  
  const result = def.calc(fields);
  showResult(calc.name, result);
}

function showResult(name, result) {
  const panel = document.getElementById('resultPanel');
  const body = document.getElementById('resultBody');
  
  body.innerHTML = `
    <div class="result-label">${name}</div>
    <div class="result-value">${result.value}</div>
    <div class="result-interpretation">${result.interpretation}</div>
    ${result.reference ? `<div class="result-ref">${result.reference}</div>` : ''}
  `;
  
  panel.classList.add('visible');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearResult() {
  document.getElementById('resultPanel').classList.remove('visible');
  document.getElementById('resultBody').innerHTML = '';
}
```

**Step 3: Test full flow**

1. Click BMI card → panel expands with Weight/Height inputs
2. Enter values → click Calculate
3. Result appears with value and interpretation
4. Click Clear → result hides

**Step 4: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): add dynamic input panel and result display"
```

---

### Task 6: Add Remaining Local Calculators

**Files:**
- Modify: `tools/clinical-calculators/index.html` (expand LOCAL_CALCS object)

**Step 1: Add all remaining local calculator definitions**

Add to LOCAL_CALCS:
- crcl (Cockcroft-Gault)
- fena
- bun-cr
- ibw, adj-bw
- na-correction
- osm-gap, delta-delta
- wells-dvt, wells-pe
- heart
- qsofa
- curb-65
- gcs, gcs-neuro
- meld, meld-na, child-pugh
- fib4, apri
- alvarado
- perc, geneva

Follow same pattern as existing calculators.

**Step 2: Test each calculator category**

Test at least one calculator per category:
- Anthropometrics: BSA
- Renal: Creatinine Clearance
- Electrolytes: Na Correction
- Cardiovascular: Wells DVT
- Critical Care: qSOFA
- Hepatology: MELD
- Neurology: Alvarado
- Pulmonary: PERC Rule

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): implement all local calculator formulas"
```

---

### Task 7: Add API Fallback for Complex Calculators

**Files:**
- Modify: `tools/clinical-calculators/index.html` (add to `<script>` block)

**Step 1: Add API call handler for complex calculators**

```javascript
async function runApiCalc(calc) {
  const btn = document.getElementById('calcRunBtn');
  const inputs = document.getElementById('calcInputs');
  
  // Collect any visible inputs
  const data = { calculator: calc.id };
  inputs.querySelectorAll('input, select').forEach(el => {
    if (el.id) data[el.id.replace('cf_', '')] = el.type === 'checkbox' ? el.checked : el.value;
  });
  
  btn.disabled = true;
  btn.textContent = 'Processing...';
  
  try {
    const res = await fetch('/api/tools/clinical-calculators', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();
    
    if (result.error) {
      showResult(calc.name, { value: 'Error', interpretation: result.error, reference: '' });
    } else {
      showResult(calc.name, {
        value: result.value || result.score || 'N/A',
        interpretation: result.interpretation || result.html || '',
        reference: result.reference || ''
      });
    }
  } catch (e) {
    showResult(calc.name, { value: 'Error', interpretation: 'Could not connect to server. Please try again.', reference: '' });
  } finally {
    btn.disabled = false;
    btn.textContent = 'Calculate';
  }
}

// Modify runLocalCalc to check if API needed
function runLocalCalc(calc) {
  if (calc.api) {
    runApiCalc(calc);
    return;
  }
  // ... existing local calc logic
}
```

**Step 2: Verify API fallback works**

For calculators marked `api: true` (TIMI, GRACE, APACHE II, SOFA):
1. Click calculator card
2. Enter data
3. Click Calculate
4. Should call API and display result or graceful error

**Step 3: Commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): add API fallback for complex calculators"
```

---

### Task 8: Final Testing and Polish

**Files:**
- Modify: `tools/clinical-calculators/index.html` (if needed)

**Step 1: Cross-browser testing**

Test in:
- Chrome
- Firefox
- Safari (if available)
- Mobile viewport

**Step 2: Accessibility check**

- Tab navigation works
- Focus states visible
- ARIA labels where needed

**Step 3: Performance check**

- Page loads < 2 seconds
- No console errors
- Calculator responses < 100ms for local calcs

**Step 4: Final commit**

```bash
git add tools/clinical-calculators/index.html
git commit -m "feat(calculators): complete dashboard redesign with 35+ calculators"
```

---

## Summary

| Task | Description | Est. Time |
|------|-------------|-----------|
| 1 | HTML structure | 10 min |
| 2 | CSS styles | 15 min |
| 3 | Calculator data + grid | 15 min |
| 4 | Local formulas (core) | 20 min |
| 5 | Input panel + results | 15 min |
| 6 | Remaining calculators | 30 min |
| 7 | API fallback | 15 min |
| 8 | Testing + polish | 20 min |
| **Total** | | **~2.5 hours** |
